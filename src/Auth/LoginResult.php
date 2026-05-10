<?php
declare(strict_types=1);

namespace ArkhamFiles\Auth;

/**
 * Resultado do tentativa de login.
 *
 * - success=true:                user autenticado, sessão criada
 * - success=true + mustChange:   precisa redirecionar pra /admin/change-password
 * - success=false + errorKey:    chave I18n com motivo do erro
 *                                ($context pode ter dados extras: seconds_left, etc)
 */
final class LoginResult
{
    private function __construct(
        public readonly bool $success,
        public readonly bool $mustChangePassword,
        public readonly ?string $errorKey,
        public readonly ?int $secondsLeft,
    ) {}

    public static function success(bool $mustChangePassword): self
    {
        return new self(
            success:            true,
            mustChangePassword: $mustChangePassword,
            errorKey:           null,
            secondsLeft:        null,
        );
    }

    public static function error(string $key, ?int $secondsLeft = null): self
    {
        return new self(
            success:            false,
            mustChangePassword: false,
            errorKey:           $key,
            secondsLeft:        $secondsLeft,
        );
    }
}
