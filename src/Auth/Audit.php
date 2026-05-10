<?php
declare(strict_types=1);

namespace ArkhamFiles\Auth;

use ArkhamFiles\Database;
use PDO;

/**
 * Audit log — trilha de eventos administrativos.
 *
 * Eventos típicos:
 *   - login_success            metadata: {role}
 *   - login_failure            metadata: {reason: 'bad_password'|'unknown_user'|'disabled'}
 *   - login_rate_limited       metadata: {seconds_left}
 *   - login_user_locked        metadata: {seconds_left, failed_attempts}
 *   - logout
 *   - password_changed
 *   - password_reset_by_admin  target_id = victim user_id
 *   - user_created             target_id = new user id, metadata: {username, role}
 *   - user_disabled            target_id = victim user_id
 *   - user_enabled             target_id = victim user_id
 *   - user_role_changed        target_id = victim, metadata: {from, to}
 *
 * Retenção: 30 dias (purge automática chamada do bootstrap, com baixa
 * frequência via probabilidade pra não sobrecarregar requests normais).
 */
final class Audit
{
    public const RETENTION_DAYS = 30;

    /**
     * @param array<string,mixed> $metadata Dados extra (serializados JSON)
     */
    public static function log(
        string $eventType,
        ?int $userId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        array $metadata = [],
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        $stmt = Database::pdo()->prepare('
            INSERT INTO audit_log
                (user_id, event_type, target_type, target_id, ip_address, user_agent, metadata)
            VALUES
                (:uid, :et, :tt, :ti, :ip, :ua, :md)
        ');
        $stmt->execute([
            ':uid' => $userId,
            ':et'  => $eventType,
            ':tt'  => $targetType,
            ':ti'  => $targetId,
            ':ip'  => $ip,
            ':ua'  => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            ':md'  => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Lista eventos paginados. Sem filtro = todos os eventos visíveis.
     * Filtro por user_id = só esse usuário.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function recent(?int $userId = null, int $limit = 100): array
    {
        $sql = '
            SELECT a.*, u.username
              FROM audit_log a
              LEFT JOIN users u ON u.id = a.user_id
        ';
        $params = [];
        if ($userId !== null) {
            $sql .= ' WHERE a.user_id = :uid';
            $params[':uid'] = $userId;
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT :lim';

        $stmt = Database::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Apaga registros mais antigos que RETENTION_DAYS. Chamado
     * probabilisticamente do bootstrap (sem cron).
     */
    public static function purgeOld(): int
    {
        $stmt = Database::pdo()->prepare('
            DELETE FROM audit_log
             WHERE created_at < datetime("now", :cutoff)
        ');
        $stmt->execute([
            ':cutoff' => '-' . self::RETENTION_DAYS . ' days'
        ]);
        return $stmt->rowCount();
    }

    /**
     * Roda purgeOld() com probabilidade 1/200 de cada chamada — em média
     * 1 vez a cada 200 requests autenticados. Chamado do bootstrap.
     */
    public static function maybePurge(): void
    {
        if (random_int(1, 200) === 1) {
            try {
                self::purgeOld();
                self::purgeOldAttempts();
            } catch (\Throwable) {
                // Falha em housekeeping não pode quebrar request
            }
        }
    }

    private static function purgeOldAttempts(): void
    {
        // login_attempts é purgado mais agressivamente (1h)
        Database::pdo()->prepare('
            DELETE FROM login_attempts
             WHERE attempted_at < datetime("now", "-1 hours")
        ')->execute();
    }
}
