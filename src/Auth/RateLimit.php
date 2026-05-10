<?php
declare(strict_types=1);

namespace ArkhamFiles\Auth;

use ArkhamFiles\Database;
use PDO;

/**
 * Rate limiting de login.
 *
 * Estratégia: 2 camadas defensivas.
 *
 * 1. **Por (IP, username)** — tabela `login_attempts`. Limita brute-force
 *    de uma origem específica. Janela de 15 minutos, 5 tentativas falhas.
 *
 * 2. **Por usuário** — colunas `failed_attempts` + `locked_until` em
 *    `users`. Lockout exponencial pra dificultar credential stuffing
 *    distribuído (atacante usando muitos IPs com a mesma lista de senhas).
 *
 * O lockout do usuário é resetado em qualquer login bem-sucedido OU
 * quando o admin reseta a senha (User::setTemporaryPassword limpa).
 */
final class RateLimit
{
    public const WINDOW_MINUTES   = 15;
    public const MAX_ATTEMPTS_IP  = 5;
    public const MAX_ATTEMPTS_USR = 10;   // mais permissivo: protege user, não IP

    /**
     * Verifica se um par (IP, username) está bloqueado.
     * Retorna null se OK, ou segundos até desbloqueio se bloqueado.
     */
    public static function checkIp(string $ip, string $username): ?int
    {
        $stmt = Database::pdo()->prepare('
            SELECT COUNT(*) AS n
              FROM login_attempts
             WHERE ip_address = :ip
               AND username_attempted = :u
               AND success = 0
               AND attempted_at >= datetime("now", :window)
        ');
        $stmt->execute([
            ':ip'     => $ip,
            ':u'      => $username,
            ':window' => '-' . self::WINDOW_MINUTES . ' minutes',
        ]);
        $count = (int) $stmt->fetchColumn();

        if ($count < self::MAX_ATTEMPTS_IP) {
            return null;
        }

        // Quando desbloqueia? Quando a tentativa mais antiga dentro da
        // janela "vencer". Buscamos a tentativa mais antiga e somamos
        // a janela.
        $stmt = Database::pdo()->prepare('
            SELECT strftime("%s", attempted_at, :window_forward) - strftime("%s", "now") AS s
              FROM login_attempts
             WHERE ip_address = :ip
               AND username_attempted = :u
               AND success = 0
               AND attempted_at >= datetime("now", :window)
             ORDER BY attempted_at ASC
             LIMIT 1
        ');
        $stmt->execute([
            ':ip'             => $ip,
            ':u'              => $username,
            ':window'         => '-' . self::WINDOW_MINUTES . ' minutes',
            ':window_forward' => '+' . self::WINDOW_MINUTES . ' minutes',
        ]);
        $secondsLeft = (int) $stmt->fetchColumn();
        return max($secondsLeft, 1);
    }

    /**
     * Lockout do usuário (independe de IP). Retorna null se OK ou
     * segundos restantes se travado.
     */
    public static function checkUser(int $userId): ?int
    {
        $stmt = Database::pdo()->prepare('
            SELECT locked_until,
                   strftime("%s", locked_until) - strftime("%s", "now") AS seconds_left
              FROM users
             WHERE id = :id
        ');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['locked_until'] === null) {
            return null;
        }
        $left = (int) $row['seconds_left'];
        return $left > 0 ? $left : null;
    }

    /**
     * Registra uma tentativa (sucesso ou falha).
     */
    public static function record(string $ip, string $username, bool $success): void
    {
        Database::pdo()->prepare('
            INSERT INTO login_attempts (ip_address, username_attempted, success)
            VALUES (:ip, :u, :s)
        ')->execute([
            ':ip' => $ip,
            ':u'  => $username,
            ':s'  => $success ? 1 : 0,
        ]);
    }

    /**
     * Atualiza contador de falhas no user. Aplica lockout se exceder.
     * Lockout escalado: 1min → 5min → 15min → 1h.
     */
    public static function recordUserFailure(int $userId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT failed_attempts FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $userId]);
        $current = (int) $stmt->fetchColumn();
        $newCount = $current + 1;

        $lockedUntil = null;
        if ($newCount >= self::MAX_ATTEMPTS_USR) {
            // 4 níveis: 1min, 5min, 15min, 1h
            $extra = $newCount - self::MAX_ATTEMPTS_USR;
            $minutes = match (true) {
                $extra === 0 => 1,
                $extra === 1 => 5,
                $extra === 2 => 15,
                default      => 60,
            };
            $lockedUntil = '+' . $minutes . ' minutes';
        }

        if ($lockedUntil) {
            $pdo->prepare('
                UPDATE users
                   SET failed_attempts = :n,
                       locked_until    = datetime("now", :lu)
                 WHERE id = :id
            ')->execute([':n' => $newCount, ':lu' => $lockedUntil, ':id' => $userId]);
        } else {
            $pdo->prepare(
                'UPDATE users SET failed_attempts = :n WHERE id = :id'
            )->execute([':n' => $newCount, ':id' => $userId]);
        }
    }

    public static function resetUser(int $userId): void
    {
        Database::pdo()->prepare(
            'UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = :id'
        )->execute([':id' => $userId]);
    }

    /**
     * Limpa registros antigos. Pra rodar via cron eventual ou no bootstrap.
     */
    public static function purgeOldAttempts(int $keepMinutes = 60): int
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM login_attempts WHERE attempted_at < datetime("now", :cutoff)'
        );
        $stmt->execute([':cutoff' => '-' . $keepMinutes . ' minutes']);
        return $stmt->rowCount();
    }
}
