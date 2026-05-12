<?php
declare(strict_types=1);

namespace ArkhamFiles;

use PDO;

/**
 * Model de QR code.
 *
 * Estados (derivados dinâmicamente de expires_at + is_disabled + is_deleted):
 *
 *   - active   → válido, sem expiração (expires_at = NULL) ou expires_at no futuro
 *   - expiring → expira nos próximos 7 dias (sub-estado de active, pra warning UI)
 *   - expired  → expires_at passou; conteúdo público fica indisponível
 *   - disabled → admin pausou manualmente (is_disabled = 1)
 *   - deleted  → soft-delete (is_deleted = 1)
 *
 * Decisões arquiteturais:
 *
 * 1. Expiração é **on-access**: a verificação acontece toda vez que alguém
 *    visita /p/{public_id}. Não há cron, worker ou job periódico.
 *    Vantagem: zero infra recorrente, sem race conditions. Trade-off:
 *    QRs nunca scaneados ficam pra sempre no banco como "expirados em silêncio",
 *    mas isso é OK porque audit log compensa (cada scan é registrado).
 *
 * 2. Expiração é **soft**: o registro permanece no banco. Só o público
 *    perde acesso. Admin pode desexpirar (push expires_at adiante).
 *    Hard delete só via ação explícita do admin (PR 07+).
 *
 * 3. Janela "expiring" = 7 dias. Útil pra UI mostrar warning "expira em 3 dias".
 *
 * 4. `public_id` é gerado por código no momento de criação (PR 07+).
 *    Formato sugerido: 4+2 hex (a4f8-2d). Curto o suficiente pra QR.
 */
final class QrCode
{
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_EXPIRING = 'expiring';
    public const STATUS_EXPIRED  = 'expired';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_DELETED  = 'deleted';

    /** Janela de "expirando em breve" (UI warning) */
    public const EXPIRING_WINDOW_DAYS = 7;

    public function __construct(
        public readonly int $id,
        public readonly string $publicId,
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $payload,
        public readonly ?int $categoryId,
        public readonly ?string $logoPath,
        public readonly bool $isDisabled,
        public readonly bool $isDeleted,
        public readonly ?string $expiresAt,
        public readonly ?int $createdBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    // ------------------------------------------------------------------
    // Lookups
    // ------------------------------------------------------------------

    public static function findById(int $id): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM qrcodes WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Lookup pelo public_id (URL pública /p/{public_id}).
     * Retorna o QR mesmo que esteja expirado, disabled, ou deleted — quem
     * chama decide o que fazer com cada estado (endpoint público tem
     * comportamentos diferentes pra cada um).
     */
    public static function findByPublicId(string $publicId): ?self
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM qrcodes WHERE public_id = :p');
        $stmt->execute([':p' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromRow($row) : null;
    }

    // ------------------------------------------------------------------
    // Status
    // ------------------------------------------------------------------

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        // Comparação string ISO: SQLite armazena 'YYYY-MM-DD HH:MM:SS' (UTC)
        return $this->expiresAt < gmdate('Y-m-d H:i:s');
    }

    public function isExpiring(): bool
    {
        if ($this->expiresAt === null || $this->isExpired()) {
            return false;
        }
        $diffSeconds = strtotime($this->expiresAt) - time();
        return $diffSeconds <= self::EXPIRING_WINDOW_DAYS * 86400;
    }

    /**
     * Estado público "consolidado" do QR. Cascata: deleted > disabled >
     * expired > expiring > active.
     */
    public function status(): string
    {
        if ($this->isDeleted)   return self::STATUS_DELETED;
        if ($this->isDisabled)  return self::STATUS_DISABLED;
        if ($this->isExpired()) return self::STATUS_EXPIRED;
        if ($this->isExpiring())return self::STATUS_EXPIRING;
        return self::STATUS_ACTIVE;
    }

    /**
     * Indica se o público deve ter acesso ao conteúdo. False bloqueia
     * com tela "Caso arquivado" / "Paciente não localizado" / etc.
     */
    public function isPubliclyAccessible(): bool
    {
        return !$this->isDeleted && !$this->isDisabled && !$this->isExpired();
    }

    /**
     * Dias até expirar (positivo = no futuro, 0 = expira hoje, negativo = já expirou).
     * Retorna null se não tem expiração.
     */
    public function daysUntilExpiration(): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }
        $diffSeconds = strtotime($this->expiresAt) - time();
        return (int) floor($diffSeconds / 86400);
    }

    /**
     * Label legível pra UI (PT-BR), retorna chave de I18n com placeholders.
     *
     * Exemplos de retorno (chave => params):
     *   ['common.expires_never', []]
     *   ['common.expires_today', []]
     *   ['common.expires_in_days', ['days' => 3]]
     *   ['common.expired_on',     ['date' => '14.MAR.26']]
     *
     * Caller faz t($key, $params) pra renderizar.
     *
     * @return array{0:string, 1:array<string,string|int>}
     */
    public function expirationLabel(): array
    {
        if ($this->expiresAt === null) {
            return ['common.expires_never', []];
        }
        $days = $this->daysUntilExpiration();
        if ($days === null) {
            return ['common.expires_never', []];
        }
        if ($days < 0) {
            // Já expirou — formato "14.MAR.26"
            $date = strtotime($this->expiresAt);
            $months = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
            $formatted = date('d', $date) . '.' . $months[(int) date('n', $date) - 1] . '.' . date('y', $date);
            return ['common.expired_on', ['date' => $formatted]];
        }
        if ($days === 0) {
            return ['common.expires_today', []];
        }
        return ['common.expires_in_days', ['days' => $days]];
    }

    // ------------------------------------------------------------------
    // Scan recording (audit + analytics)
    // ------------------------------------------------------------------

    /**
     * Registra um acesso público. Chamado do handler /p/{public_id}
     * imediatamente após o lookup, ANTES de decidir o que renderizar.
     *
     * `was_expired` flag marca scans em QRs já caducados — útil pra
     * detectar QRs antigos ainda circulando em papel/impressos.
     */
    public function recordScan(?string $ip, ?string $userAgent, ?string $referer): void
    {
        $stmt = Database::pdo()->prepare('
            INSERT INTO scans (qr_id, ip_address, user_agent, referer, was_expired)
            VALUES (:q, :ip, :ua, :r, :exp)
        ');
        $stmt->execute([
            ':q'   => $this->id,
            ':ip'  => $ip,
            ':ua'  => $userAgent !== null ? mb_substr($userAgent, 0, 500) : null,
            ':r'   => $referer   !== null ? mb_substr($referer,   0, 500) : null,
            ':exp' => $this->isExpired() ? 1 : 0,
        ]);
    }

    // ------------------------------------------------------------------
    // Public-ID generation (usado pelo CRUD de criação no PR 07+)
    // ------------------------------------------------------------------

    /**
     * Gera um public_id único no formato XXXX-XX (4+2 hex lowercase).
     * Tenta até 50× — colisão é extremamente improvável (16⁶ = 16.7M
     * combinações).
     */
    public static function generatePublicId(): string
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT 1 FROM qrcodes WHERE public_id = :p');
        for ($i = 0; $i < 50; $i++) {
            $hex = bin2hex(random_bytes(3)); // 6 hex chars
            $candidate = substr($hex, 0, 4) . '-' . substr($hex, 4, 2);
            $stmt->execute([':p' => $candidate]);
            if ($stmt->fetchColumn() === false) {
                return $candidate;
            }
        }
        throw new \RuntimeException('Não foi possível gerar public_id único após 50 tentativas');
    }

    // ------------------------------------------------------------------
    // Hydration
    // ------------------------------------------------------------------

    /** @param array<string,mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            id:          (int) $row['id'],
            publicId:    (string) $row['public_id'],
            type:        (string) $row['type'],
            title:       (string) $row['title'],
            payload:     isset($row['payload']) ? (string) $row['payload'] : null,
            categoryId:  isset($row['category_id']) ? (int) $row['category_id'] : null,
            logoPath:    isset($row['logo_path']) ? (string) $row['logo_path'] : null,
            isDisabled:  (bool) ($row['is_disabled'] ?? 0),
            isDeleted:   (bool) ($row['is_deleted']  ?? 0),
            expiresAt:   isset($row['expires_at']) ? (string) $row['expires_at'] : null,
            createdBy:   isset($row['created_by'])  ? (int)    $row['created_by']  : null,
            createdAt:   (string) $row['created_at'],
            updatedAt:   (string) ($row['updated_at'] ?? $row['created_at']),
        );
    }
}
