<?php
declare(strict_types=1);

namespace ArkhamFiles\Auth;

use ArkhamFiles\Http;

/**
 * Facade do módulo de autenticação. Concentra o flow de login e
 * acesso ao usuário corrente.
 *
 * Responsabilidades:
 *   - login()  → orquestra rate limit, verifica password, registra audit
 *   - logout() → limpa sessão + audit
 *   - currentUser() / currentUserId() / requireAuth() / requireRole()
 */
final class Auth
{
    /**
     * Representa o resultado do login. Lê o `error` quando `success`
     * é false. Se `mustChangePassword` é true, redirecionar pra tela
     * de troca forçada antes de qualquer outra coisa.
     */
    public static function login(string $username, string $password): LoginResult
    {
        $ip = Http::clientIp();
        $username = trim($username);

        // Rate limit (IP × username)
        $secondsLeft = RateLimit::checkIp($ip, $username);
        if ($secondsLeft !== null) {
            Audit::log('login_rate_limited', null, null, null,
                ['username' => $username, 'seconds_left' => $secondsLeft],
                $ip, $_SERVER['HTTP_USER_AGENT'] ?? null);
            return LoginResult::error('errors.auth.rate_limited', $secondsLeft);
        }

        $user = User::findByUsername($username);

        // Usuário não existe — registra falha (ainda assim) pra evitar
        // user enumeration via timing/contagem
        if ($user === null) {
            // Faz uma operação cara fictícia pra equilibrar timing
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$' . str_repeat('A', 22) . '$' . str_repeat('B', 43));
            RateLimit::record($ip, $username, false);
            Audit::log('login_failure', null, null, null,
                ['username' => $username, 'reason' => 'unknown_user'],
                $ip, $_SERVER['HTTP_USER_AGENT'] ?? null);
            return LoginResult::error('errors.auth.invalid_credentials');
        }

        // Usuário desabilitado
        if ($user->isDisabled()) {
            RateLimit::record($ip, $username, false);
            Audit::log('login_failure', $user->id, null, null,
                ['reason' => 'disabled'],
                $ip, $_SERVER['HTTP_USER_AGENT'] ?? null);
            return LoginResult::error('errors.auth.account_disabled');
        }

        // Lockout do usuário (independente de IP)
        $lockSecs = RateLimit::checkUser($user->id);
        if ($lockSecs !== null) {
            Audit::log('login_user_locked', $user->id, null, null,
                ['seconds_left' => $lockSecs],
                $ip, $_SERVER['HTTP_USER_AGENT'] ?? null);
            return LoginResult::error('errors.auth.account_locked', $lockSecs);
        }

        // Verifica senha
        if (!password_verify($password, $user->passwordHash)) {
            RateLimit::record($ip, $username, false);
            RateLimit::recordUserFailure($user->id);
            Audit::log('login_failure', $user->id, null, null,
                ['reason' => 'bad_password'],
                $ip, $_SERVER['HTTP_USER_AGENT'] ?? null);
            return LoginResult::error('errors.auth.invalid_credentials');
        }

        // Sucesso — re-hash se a config do Argon2 mudou
        if (password_needs_rehash($user->passwordHash, PASSWORD_ARGON2ID)) {
            $newHash = password_hash($password, PASSWORD_ARGON2ID);
            if ($newHash !== false) {
                \ArkhamFiles\Database::pdo()
                    ->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                    ->execute([':h' => $newHash, ':id' => $user->id]);
            }
        }

        // Limpa contadores e marca login
        RateLimit::record($ip, $username, true);
        User::recordLogin($user->id);

        // Inicia sessão de forma segura
        Session::start();
        Session::regenerate();

        // Se o usuário tem 2FA ativo, NÃO cria a sessão completa ainda.
        // Apenas marca o user_id como "pendente de 2FA" — o request
        // seguinte vai redirecionar pra /admin/2fa/verify, e só após
        // validar o código TOTP é que user_id real será setado.
        if ($user->totpEnabled) {
            Session::set('pending_2fa_user_id', $user->id);
            Session::set('pending_2fa_started_at', time());
            // Login parcial — não loga ainda no audit como success
            return LoginResult::pendingTwoFactor();
        }

        // Login normal (sem 2FA)
        Session::set('user_id', $user->id);
        Session::set('login_at', time());

        Audit::log('login_success', $user->id, null, null,
            ['role' => $user->role],
            $ip, $_SERVER['HTTP_USER_AGENT'] ?? null);

        return LoginResult::success($user->mustChangePassword);
    }

    public static function logout(): void
    {
        Session::start();
        $userId = self::currentUserId();
        if ($userId !== null) {
            Audit::log('logout', $userId, null, null, [],
                Http::clientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? null);
        }
        Session::destroy();
    }

    public static function currentUserId(): ?int
    {
        Session::start();
        $id = Session::get('user_id');
        return is_int($id) ? $id : null;
    }

    /** Cache para evitar query duplicada por request */
    private static ?User $cachedUser = null;
    private static ?int  $cachedUserId = null;

    public static function currentUser(): ?User
    {
        $id = self::currentUserId();
        if ($id === null) {
            return null;
        }
        if (self::$cachedUserId === $id && self::$cachedUser !== null) {
            return self::$cachedUser;
        }
        $user = User::findById($id);
        if ($user === null || $user->isDisabled()) {
            // Sessão pendurada de user que não existe mais ou foi desabilitado
            Session::destroy();
            return null;
        }
        self::$cachedUserId = $id;
        self::$cachedUser   = $user;
        return $user;
    }

    public static function isLoggedIn(): bool
    {
        return self::currentUserId() !== null;
    }

    /**
     * Retorna o user ou redireciona pra /admin/login se não autenticado.
     * Usar no início de handlers protegidos.
     */
    public static function requireAuth(): User
    {
        $user = self::currentUser();
        if ($user === null) {
            // Salva o destino pra redirect pós-login
            $requestedUri = $_SERVER['REQUEST_URI'] ?? '/admin/dashboard';
            if (!str_starts_with($requestedUri, '/admin/login')) {
                Session::start();
                Session::set('return_to', $requestedUri);
            }
            header('Location: /admin/login', true, 302);
            exit;
        }
        return $user;
    }

    /**
     * Como requireAuth, mas exige um role específico. Devolve 403 se não bate.
     */
    public static function requireRole(string $role): User
    {
        $user = self::requireAuth();
        if ($user->role !== $role) {
            http_response_code(403);
            echo \ArkhamFiles\View::render('error', [
                'errorTitle'    => t('errors.forbidden.title'),
                'errorSubtitle' => t('errors.forbidden.subtitle'),
                'errorCode'     => '403',
            ]);
            exit;
        }
        return $user;
    }

    /**
     * Verifica must_change_password. Se for true, redireciona pra tela de
     * troca. Use depois de requireAuth().
     */
    public static function enforcePasswordChange(User $user): void
    {
        if (!$user->mustChangePassword) {
            return;
        }
        // Permite acesso à própria tela de change-password e ao logout
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (
            str_starts_with($uri, '/admin/change-password')
            || str_starts_with($uri, '/admin/logout')
        ) {
            return;
        }
        header('Location: /admin/change-password', true, 302);
        exit;
    }

    /**
     * Força admins (role=admin) sem 2FA ativo a configurarem antes de
     * acessar qualquer outra rota. Curadores não são forçados — podem
     * ativar opcionalmente via /admin/profile.
     */
    public static function enforceTwoFactorSetup(User $user): void
    {
        if (!$user->isAdmin() || $user->totpEnabled) {
            return;
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        // Permite acesso à própria tela de setup e ao logout
        if (
            str_starts_with($uri, '/admin/2fa/setup')
            || str_starts_with($uri, '/admin/logout')
        ) {
            return;
        }
        header('Location: /admin/2fa/setup', true, 302);
        exit;
    }

    /**
     * ID do usuário em estado de "esperando código 2FA". Diferente
     * de currentUserId(): esse só existe entre password OK e código OK.
     */
    public static function pendingTwoFactorUserId(): ?int
    {
        Session::start();
        $id = Session::get('pending_2fa_user_id');
        if (!is_int($id)) {
            return null;
        }
        // Sessão de pending 2FA expira em 10 minutos (anti-stall)
        $startedAt = Session::get('pending_2fa_started_at');
        if (!is_int($startedAt) || (time() - $startedAt) > 600) {
            Session::unset('pending_2fa_user_id');
            Session::unset('pending_2fa_started_at');
            return null;
        }
        return $id;
    }

    /**
     * Completa o login após validação do código TOTP (ou recovery).
     * Promove pending_2fa_user_id → user_id, regenera sessão de novo,
     * e registra audit de login_success.
     */
    public static function completeTwoFactorLogin(User $user, bool $usedRecovery = false): void
    {
        Session::start();
        Session::regenerate();
        Session::unset('pending_2fa_user_id');
        Session::unset('pending_2fa_started_at');
        Session::set('user_id', $user->id);
        Session::set('login_at', time());

        Audit::log('login_success', $user->id, null, null,
            ['role' => $user->role, 'second_factor' => $usedRecovery ? 'recovery' : 'totp'],
            \ArkhamFiles\Http::clientIp(),
            $_SERVER['HTTP_USER_AGENT'] ?? null);
    }
}
