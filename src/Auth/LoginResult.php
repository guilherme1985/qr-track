<?php
declare(strict_types=1);

namespace ArkhamFiles\Auth;

/**
 * Resultado da tentativa de login.
 *
 *  - success=true, mustChange=false, pending2fa=false  → logado, vai pro dashboard
 *  - success=true, mustChange=true                     → redireciona pra change-password
 *  - success=true, pending2fa=true                     → redireciona pra /admin/2fa/verify
 *  - success=false + errorKey                          → erro (rate limit, credenciais, etc)
 */
final class LoginResult
{
    private function __construct(
        public readonly bool $success,
        public readonly bool $mustChangePassword,
        public readonly bool $pendingTwoFactor,
        public readonly ?string $errorKey,
        public readonly ?int $secondsLeft,
    ) {}

    public static function success(bool $mustChangePassword): self
    {
        return new self(
            success:            true,
            mustChangePassword: $mustChangePassword,
            pendingTwoFactor:   false,
            errorKey:           null,
            secondsLeft:        null,
        );
    }

    public static function pendingTwoFactor(): self
    {
        return new self(
            success:            true,
            mustChangePassword: false,
            pendingTwoFactor:   true,
            errorKey:           null,
            secondsLeft:        null,
        );
    }

    public static function error(string $key, ?int $secondsLeft = null): self
    {
        return new self(
            success:            false,
            mustChangePassword: false,
            pendingTwoFactor:   false,
            errorKey:           $key,
            secondsLeft:        $secondsLeft,
        );
    }
}
