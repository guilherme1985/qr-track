<?php
declare(strict_types=1);

namespace ArkhamFiles\Auth;

use ArkhamFiles\Database;
use PDO;

/**
 * Modelo de usuário. Encapsula CRUD e normaliza o tratamento de status
 * (ativo / desabilitado) pra que o resto do sistema não tenha que lembrar
 * de filtrar `disabled_at IS NULL` toda hora.
 *
 * Roles válidas: 'admin' (gerencia tudo) e 'curator' (CRUD nos próprios
 * recursos). Validação acontece no nível do banco (CHECK constraint).
 */
final class User
{
    public const ROLE_ADMIN   = 'admin';
    public const ROLE_CURATOR = 'curator';

    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly ?string $email,
        public readonly string $passwordHash,
        public readonly string $role,
        public readonly bool $mustChangePassword,
        public readonly ?string $disabledAt,
        public readonly ?string $lastLoginAt,
        public readonly ?string $passwordChangedAt,
        public readonly string $createdAt,
        public readonly bool $totpEnabled,
        public readonly ?string $totpSecret = null,
        public readonly ?string $recoveryCodes = null,
    ) {}

    public function isAdmin():    bool { return $this->role === self::ROLE_ADMIN; }
    public function isCurator():  bool { return $this->role === self::ROLE_CURATOR; }
    public function isDisabled(): bool { return $this->disabledAt !== null; }

    /** Iniciais para o avatar do header (max 2 caracteres) */
    public function initials(): string
    {
        $u = mb_strtoupper($this->username);
        return mb_substr($u, 0, 2);
    }

    public function roleLabel(): string
    {
        return $this->isAdmin() ? 'ADMIN' : 'CURADOR';
    }

    // ---------------------------------------------------------------
    // Lookups
    // ---------------------------------------------------------------

    public static function findById(int $id): ?self
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromRow($row) : null;
    }

    public static function findByUsername(string $username): ?self
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM users WHERE username = :u LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::fromRow($row) : null;
    }

    /**
     * Lista todos os usuários, opcionalmente incluindo desabilitados.
     * @return self[]
     */
    public static function listAll(bool $includeDisabled = true): array
    {
        $sql = 'SELECT * FROM users';
        if (!$includeDisabled) {
            $sql .= ' WHERE disabled_at IS NULL';
        }
        $sql .= ' ORDER BY username COLLATE NOCASE ASC';

        $stmt = Database::pdo()->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(self::fromRow(...), $rows);
    }

    public static function countActive(): int
    {
        $stmt = Database::pdo()->query(
            'SELECT COUNT(*) FROM users WHERE disabled_at IS NULL'
        );
        return (int) $stmt->fetchColumn();
    }

    // ---------------------------------------------------------------
    // Mutations
    // ---------------------------------------------------------------

    /**
     * Cria um novo usuário e devolve o ID.
     * Não valida força da senha — assume que o caller já validou via
     * PasswordPolicy::validate(). O hash é Argon2id.
     */
    public static function create(
        string $username,
        ?string $email,
        string $plainPassword,
        string $role,
        bool $mustChangePassword = false,
    ): int {
        if (!in_array($role, [self::ROLE_ADMIN, self::ROLE_CURATOR], true)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }

        $hash = password_hash($plainPassword, PASSWORD_ARGON2ID);
        if ($hash === false) {
            throw new \RuntimeException('password_hash failed');
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('
            INSERT INTO users (username, email, password_hash, role, must_change_password)
            VALUES (:u, :e, :h, :r, :mcp)
        ');
        $stmt->execute([
            ':u'   => $username,
            ':e'   => $email,
            ':h'   => $hash,
            ':r'   => $role,
            ':mcp' => $mustChangePassword ? 1 : 0,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Atualiza email e/ou role. Cada parâmetro nulo é ignorado.
     */
    public static function update(
        int $id,
        ?string $email = null,
        ?string $role  = null,
    ): void {
        $sets = [];
        $params = [':id' => $id];

        if ($email !== null) {
            $sets[] = 'email = :e';
            $params[':e'] = $email;
        }
        if ($role !== null) {
            if (!in_array($role, [self::ROLE_ADMIN, self::ROLE_CURATOR], true)) {
                throw new \InvalidArgumentException("Invalid role: {$role}");
            }
            $sets[] = 'role = :r';
            $params[':r'] = $role;
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        Database::pdo()->prepare($sql)->execute($params);
    }

    /**
     * Define nova senha. Limpa flags de "deve trocar" e "tentativas falhas".
     */
    public static function setPassword(int $id, string $plainPassword): void
    {
        $hash = password_hash($plainPassword, PASSWORD_ARGON2ID);
        if ($hash === false) {
            throw new \RuntimeException('password_hash failed');
        }

        Database::pdo()->prepare('
            UPDATE users
               SET password_hash         = :h,
                   must_change_password  = 0,
                   password_changed_at   = CURRENT_TIMESTAMP,
                   failed_attempts       = 0,
                   locked_until          = NULL
             WHERE id = :id
        ')->execute([':h' => $hash, ':id' => $id]);
    }

    /**
     * Define senha e marca como "deve trocar" (uso: reset por admin).
     */
    public static function setTemporaryPassword(int $id, string $plainPassword): void
    {
        $hash = password_hash($plainPassword, PASSWORD_ARGON2ID);
        if ($hash === false) {
            throw new \RuntimeException('password_hash failed');
        }

        Database::pdo()->prepare('
            UPDATE users
               SET password_hash         = :h,
                   must_change_password  = 1,
                   password_changed_at   = CURRENT_TIMESTAMP,
                   failed_attempts       = 0,
                   locked_until          = NULL
             WHERE id = :id
        ')->execute([':h' => $hash, ':id' => $id]);
    }

    public static function recordLogin(int $id): void
    {
        Database::pdo()->prepare('
            UPDATE users
               SET last_login_at   = CURRENT_TIMESTAMP,
                   failed_attempts = 0,
                   locked_until    = NULL
             WHERE id = :id
        ')->execute([':id' => $id]);
    }

    public static function disable(int $id): void
    {
        Database::pdo()->prepare(
            'UPDATE users SET disabled_at = CURRENT_TIMESTAMP WHERE id = :id'
        )->execute([':id' => $id]);
    }

    public static function enable(int $id): void
    {
        Database::pdo()->prepare(
            'UPDATE users SET disabled_at = NULL WHERE id = :id'
        )->execute([':id' => $id]);
    }

    /** Hard delete — cuidado. Auditoria sobrevive (FK ON DELETE SET NULL). */
    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM users WHERE id = :id')
            ->execute([':id' => $id]);
    }

    public static function usernameExists(string $username, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM users WHERE username = :u';
        $params = [':u' => $username];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    // ---------------------------------------------------------------
    // Hydration
    // ---------------------------------------------------------------

    /** @param array<string,mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            id:                 (int) $row['id'],
            username:           (string) $row['username'],
            email:              isset($row['email']) ? (string) $row['email'] : null,
            passwordHash:       (string) $row['password_hash'],
            role:               (string) ($row['role'] ?? self::ROLE_CURATOR),
            mustChangePassword: (bool) ($row['must_change_password'] ?? 0),
            disabledAt:         isset($row['disabled_at']) ? (string) $row['disabled_at'] : null,
            lastLoginAt:        isset($row['last_login_at']) ? (string) $row['last_login_at'] : null,
            passwordChangedAt:  isset($row['password_changed_at']) ? (string) $row['password_changed_at'] : null,
            createdAt:          (string) $row['created_at'],
            totpEnabled:        (bool) ($row['totp_enabled'] ?? 0),
            totpSecret:         isset($row['totp_secret']) ? (string) $row['totp_secret'] : null,
            recoveryCodes:      isset($row['recovery_codes']) ? (string) $row['recovery_codes'] : null,
        );
    }
}
