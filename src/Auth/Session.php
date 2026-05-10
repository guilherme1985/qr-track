<?php
declare(strict_types=1);

namespace ArkhamFiles\Auth;

use ArkhamFiles\Config;

/**
 * Wrapper sobre sessions PHP. Centraliza a configuração de cookie
 * (HttpOnly, Secure conditionally, SameSite=Lax) e a geração/validação
 * do token CSRF.
 *
 * O cookie 'Secure' só liga se o request veio via HTTPS — caso contrário
 * o cookie nunca é enviado pelo browser e a sessão fica quebrada em
 * acesso LAN HTTP direto. A detecção considera tanto $_SERVER['HTTPS']
 * quanto X-Forwarded-Proto (vindo do gateway nginx).
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // Apenas em request HTTP (CLI não faz session)
        if (PHP_SAPI === 'cli') {
            return;
        }

        $isHttps = self::isHttps();
        $lifetime = (int) Config::get('SESSION_LIFETIME', '86400');
        $cookieName = (string) Config::get('SESSION_NAME', 'arkham_session');

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name($cookieName);
        session_start();
        self::$started = true;
    }

    /**
     * Detecta se o request original era HTTPS, considerando que o gateway
     * nginx pode estar fazendo terminação TLS via Cloudflare tunnel.
     */
    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (strtolower($xfp) === 'https') {
            return true;
        }
        return false;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function unset(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Regenera ID da sessão. Chamar após login pra prevenir session fixation.
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Destrói completamente a sessão (logout).
     */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        self::$started = false;
    }

    // ------------------------------------------------------------------
    // CSRF
    // ------------------------------------------------------------------

    public static function csrfToken(): string
    {
        $token = self::get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(16));
            self::set('_csrf', $token);
        }
        return $token;
    }

    public static function validateCsrf(?string $submitted): bool
    {
        if ($submitted === null || $submitted === '') {
            return false;
        }
        $expected = self::get('_csrf');
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        return hash_equals($expected, $submitted);
    }

    /**
     * HTML helper pra incluir o token em forms.
     * Uso: <?= Session::csrfField() ?>
     */
    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
