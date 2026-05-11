<?php
declare(strict_types=1);

/**
 * Arkham Files — front controller (PR 03).
 *
 * Adiciona:
 *   - POST /admin/login          → autenticação
 *   - POST /admin/logout         → encerra sessão
 *   - GET  /admin/forgot-password → página estática
 *   - GET  /admin/change-password → form (pós-reset ou voluntário)
 *   - POST /admin/change-password → processa
 *   - GET  /admin/profile        → dados do usuário logado
 *   - GET  /admin/users          → lista (admin only)
 *   - GET  /admin/users/new      → form de criação
 *   - POST /admin/users/new      → processa criação
 *   - GET  /admin/users/{id}/edit
 *   - POST /admin/users/{id}/edit
 *   - GET  /admin/users/{id}/reset-password
 *   - POST /admin/users/{id}/reset-password
 *   - POST /admin/users/{id}/disable
 *   - POST /admin/users/{id}/enable
 *
 * Middleware:
 *   Auth::requireAuth() em todas as /admin/* exceto /admin/login,
 *   /admin/forgot-password, e os assets estáticos.
 *   Auth::enforcePasswordChange() força redirect pra change-password
 *   se must_change_password=1.
 */

require __DIR__ . '/../src/Bootstrap.php';

use ArkhamFiles\Bootstrap;
use ArkhamFiles\Auth\Auth;
use ArkhamFiles\Auth\Audit;
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Auth\User;
use ArkhamFiles\Auth\PasswordPolicy;
use ArkhamFiles\Auth\PasswordGenerator;
use ArkhamFiles\Auth\TwoFactor;
use ArkhamFiles\Category;
use ArkhamFiles\CategoryAttributes;
use ArkhamFiles\Http;

$rootDir = dirname(__DIR__);
Bootstrap::init($rootDir);
Audit::maybePurge();

$router = new Bramus\Router\Router();

// =====================================================================
// Helper: validar CSRF em POST. Retorna true se OK; false e responde
// 400 se inválido.
// =====================================================================
$verifyCsrf = static function (): bool {
    Session::start();
    $token = $_POST['_csrf'] ?? null;
    if (Session::validateCsrf(is_string($token) ? $token : null)) {
        return true;
    }
    http_response_code(400);
    echo \ArkhamFiles\View::render('error', [
        'errorTitle'    => t('errors.auth.csrf_invalid'),
        'errorSubtitle' => 'CSRF',
        'errorCode'     => '400',
    ]);
    return false;
};

// =====================================================================
// Welcome / status / smoke test (público)
// =====================================================================
$router->get('/', function () use ($rootDir) {
    require $rootDir . '/templates/welcome.php';
});

// =====================================================================
// Public scan placeholder (público)
// =====================================================================
$router->get('/p/(.+)', function (string $publicId) use ($rootDir) {
    http_response_code(404);
    $errorTitle    = t('errors.wip.title');
    $errorSubtitle = t('errors.wip.subtitle');
    $errorCode     = 'WIP';
    require $rootDir . '/templates/error.php';
});

// =====================================================================
// Auth: /admin/login (GET form, POST submit)
// =====================================================================
$router->get('/admin/login', function () use ($rootDir) {
    Session::start();
    if (Auth::isLoggedIn()) {
        header('Location: /admin/dashboard', true, 302);
        return;
    }
    require $rootDir . '/templates/admin/login.php';
});

$router->post('/admin/login', function () use ($rootDir, $verifyCsrf) {
    Session::start();
    if (!$verifyCsrf()) return;

    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $result = Auth::login($username, $password);
    if (!$result->success) {
        $errorMessage = t($result->errorKey, $result->secondsLeft !== null
            ? ['seconds' => $result->secondsLeft]
            : []);
        $oldUsername = $username;
        require $rootDir . '/templates/admin/login.php';
        return;
    }

    // Se o user tem 2FA ativo, vai pra verify primeiro
    if ($result->pendingTwoFactor) {
        header('Location: /admin/2fa/verify', true, 302);
        return;
    }

    if ($result->mustChangePassword) {
        header('Location: /admin/change-password', true, 302);
        return;
    }

    $returnTo = Session::get('return_to');
    Session::unset('return_to');
    $target = (is_string($returnTo) && str_starts_with($returnTo, '/admin/'))
        ? $returnTo
        : '/admin/dashboard';
    header('Location: ' . $target, true, 302);
});

// =====================================================================
// Auth: /admin/logout
// =====================================================================
$router->post('/admin/logout', function () use ($verifyCsrf) {
    Session::start();
    if (!$verifyCsrf()) return;
    Auth::logout();
    header('Location: /admin/login', true, 302);
});

// =====================================================================
// Auth: /admin/forgot-password (estática, público)
// =====================================================================
$router->get('/admin/forgot-password', function () use ($rootDir) {
    require $rootDir . '/templates/admin/forgot-password.php';
});

// =====================================================================
// Auth: /admin/change-password
// =====================================================================
$router->get('/admin/change-password', function () use ($rootDir) {
    $user = Auth::requireAuth();
    $forced = $user->mustChangePassword;
    require $rootDir . '/templates/admin/change-password.php';
});

$router->post('/admin/change-password', function () use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    $forced = $user->mustChangePassword;

    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password']     ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $errors  = [];

    if (!$forced) {
        if (!password_verify($current, $user->passwordHash)) {
            $errors[] = t('errors.auth.wrong_current_password');
        }
    }
    if ($new !== $confirm) {
        $errors[] = t('errors.auth.passwords_dont_match');
    }
    foreach (PasswordPolicy::validate($new, $user->username) as $key) {
        $errors[] = t($key);
    }

    if ($errors !== []) {
        require $rootDir . '/templates/admin/change-password.php';
        return;
    }

    User::setPassword($user->id, $new);
    Audit::log('password_changed', $user->id, null, null,
        ['forced' => $forced ? 1 : 0],
        Http::clientIp(),
        Http::userAgent());

    Session::regenerate();
    header('Location: /admin/dashboard', true, 302);
});

// =====================================================================
// /admin/profile
// =====================================================================
$router->get('/admin/profile', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;
    require $rootDir . '/templates/admin/profile.php';
});

// =====================================================================
// /admin/dashboard
// =====================================================================
$router->get('/admin', function () {
    header('Location: /admin/dashboard', true, 302);
    exit;
});
$router->get('/admin/', function () {
    header('Location: /admin/dashboard', true, 302);
    exit;
});

$router->get('/admin/dashboard', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;
    require $rootDir . '/templates/admin/dashboard.php';
});

$router->get('/admin/settings', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;
    require $rootDir . '/templates/admin/settings.php';
});

// =====================================================================
// /admin/users (admin only)
// =====================================================================
$router->get('/admin/users', function () use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;
    $users = User::listAll();
    $flashMessage = Session::get('flash');
    Session::unset('flash');
    require $rootDir . '/templates/admin/users/index.php';
});

$router->get('/admin/users/new', function () use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;
    require $rootDir . '/templates/admin/users/new.php';
});

$router->post('/admin/users/new', function () use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $oldUsername = trim((string) ($_POST['username'] ?? ''));
    $oldEmail    = trim((string) ($_POST['email']    ?? ''));
    $oldRole     = (string) ($_POST['role'] ?? User::ROLE_CURATOR);
    $errors = [];

    if ($oldUsername === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $oldUsername)) {
        $errors[] = t('errors.users.invalid_username');
    } elseif (User::usernameExists($oldUsername)) {
        $errors[] = t('errors.users.username_taken');
    }
    if ($oldEmail !== '' && !filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('errors.users.invalid_email');
    }
    if (!in_array($oldRole, [User::ROLE_ADMIN, User::ROLE_CURATOR], true)) {
        $errors[] = t('errors.users.invalid_role');
    }

    if ($errors !== []) {
        require $rootDir . '/templates/admin/users/new.php';
        return;
    }

    $temporaryPassword = PasswordGenerator::generate(16);
    $newId = User::create(
        username: $oldUsername,
        email:    $oldEmail !== '' ? $oldEmail : null,
        plainPassword: $temporaryPassword,
        role:     $oldRole,
        mustChangePassword: true,
    );

    Audit::log('user_created', $admin->id, 'user', $newId,
        ['username' => $oldUsername, 'role' => $oldRole],
        Http::clientIp(), Http::userAgent());

    $user = User::findById($newId);
    require $rootDir . '/templates/admin/users/created.php';
});

$router->get('/admin/users/(\d+)/edit', function (string $id) use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;
    $user = User::findById((int) $id);
    if ($user === null) {
        http_response_code(404);
        echo \ArkhamFiles\View::render('error', [
            'errorTitle'    => t('errors.users.not_found'),
            'errorSubtitle' => 'USER',
            'errorCode'     => '404',
        ]);
        return;
    }
    require $rootDir . '/templates/admin/users/edit.php';
});

$router->post('/admin/users/(\d+)/edit', function (string $id) use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $userId = (int) $id;
    $user = User::findById($userId);
    if ($user === null) {
        http_response_code(404);
        return;
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $role  = (string) ($_POST['role']  ?? $user->role);
    $errors = [];

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('errors.users.invalid_email');
    }
    // Self não pode mudar papel próprio (form já desabilita, mas valida server-side)
    if ($user->id === $admin->id) {
        $role = $user->role;
    }
    if (!in_array($role, [User::ROLE_ADMIN, User::ROLE_CURATOR], true)) {
        $errors[] = t('errors.users.invalid_role');
    }

    // Não pode demover o último admin
    if ($user->isAdmin() && $role !== User::ROLE_ADMIN) {
        $admins = array_filter(User::listAll(false), fn(User $u) => $u->isAdmin());
        if (count($admins) <= 1) {
            $errors[] = t('errors.users.cant_demote_last_admin');
        }
    }

    if ($errors !== []) {
        require $rootDir . '/templates/admin/users/edit.php';
        return;
    }

    $changes = [];
    if ($email !== ($user->email ?? '')) {
        $changes['email'] = ['from' => $user->email, 'to' => $email];
    }
    if ($role !== $user->role) {
        $changes['role'] = ['from' => $user->role, 'to' => $role];
    }

    User::update($userId,
        email: $email !== '' ? $email : null,
        role:  $role,
    );

    if ($changes !== []) {
        Audit::log('user_updated', $admin->id, 'user', $userId, $changes,
            Http::clientIp(), Http::userAgent());
    }

    Session::set('flash', t('admin.users.flash_updated', ['user' => htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8')]));
    header('Location: /admin/users', true, 302);
});

$router->get('/admin/users/(\d+)/reset-password', function (string $id) use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;
    $user = User::findById((int) $id);
    if ($user === null) {
        http_response_code(404);
        return;
    }
    $temporaryPassword = null;
    require $rootDir . '/templates/admin/users/reset-password.php';
});

$router->post('/admin/users/(\d+)/reset-password', function (string $id) use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;
    $user = User::findById((int) $id);
    if ($user === null) {
        http_response_code(404);
        return;
    }

    $temporaryPassword = PasswordGenerator::generate(16);
    User::setTemporaryPassword($user->id, $temporaryPassword);
    Audit::log('password_reset_by_admin', $admin->id, 'user', $user->id,
        ['target_username' => $user->username],
        Http::clientIp(), Http::userAgent());

    require $rootDir . '/templates/admin/users/reset-password.php';
});

$router->post('/admin/users/(\d+)/disable', function (string $id) use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $userId = (int) $id;

    if ($userId === $admin->id) {
        Session::set('flash', '⚠ ' . t('errors.users.cant_disable_self'));
        header('Location: /admin/users', true, 302);
        return;
    }

    $user = User::findById($userId);
    if ($user === null) {
        http_response_code(404);
        return;
    }

    User::disable($userId);
    Audit::log('user_disabled', $admin->id, 'user', $userId,
        ['target_username' => $user->username],
        Http::clientIp(), Http::userAgent());

    Session::set('flash', t('admin.users.flash_disabled', ['user' => htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8')]));
    header('Location: /admin/users', true, 302);
});

$router->post('/admin/users/(\d+)/enable', function (string $id) use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $userId = (int) $id;

    $user = User::findById($userId);
    if ($user === null) {
        http_response_code(404);
        return;
    }

    User::enable($userId);
    Audit::log('user_enabled', $admin->id, 'user', $userId,
        ['target_username' => $user->username],
        Http::clientIp(), Http::userAgent());

    Session::set('flash', t('admin.users.flash_enabled', ['user' => htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8')]));
    header('Location: /admin/users', true, 302);
});

// =====================================================================
// /admin/users/{id}/delete  (admin only) — exclusão permanente
// =====================================================================
$router->get('/admin/users/(\d+)/delete', function (string $id) use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $userId = (int) $id;
    if ($userId === $admin->id) {
        Session::set('flash', '⚠ ' . t('errors.users.cant_delete_self'));
        header('Location: /admin/users', true, 302);
        return;
    }
    $user = User::findById($userId);
    if ($user === null) {
        http_response_code(404);
        echo \ArkhamFiles\View::render('error', [
            'errorTitle'    => t('errors.users.not_found'),
            'errorSubtitle' => 'USER',
            'errorCode'     => '404',
        ]);
        return;
    }

    // Coleta estatísticas pra mostrar antes do delete.
    // (QRs/notas/strains/imagens são 0 hoje porque CRUD não existe ainda.)
    $pdo = \ArkhamFiles\Database::pdo();

    $archives = 0;
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM qrcodes WHERE created_by = :id');
        $stmt->execute([':id' => $userId]);
        $archives = (int) $stmt->fetchColumn();
    } catch (\Throwable) { /* tabela pode não ter created_by ainda */ }

    $scans = 0; // Tabela scans não tem coluna user_id; conta por qrcodes do user
    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) FROM scans s
            INNER JOIN qrcodes q ON q.id = s.qrcode_id
            WHERE q.created_by = :id
        ');
        $stmt->execute([':id' => $userId]);
        $scans = (int) $stmt->fetchColumn();
    } catch (\Throwable) { /* idem */ }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM audit_log WHERE user_id = :id');
    $stmt->execute([':id' => $userId]);
    $auditEvents = (int) $stmt->fetchColumn();

    $stats = [
        'archives'      => $archives,
        'scans'         => $scans,
        'audit_events'  => $auditEvents,
    ];
    require $rootDir . '/templates/admin/users/delete.php';
});

$router->post('/admin/users/(\d+)/delete', function (string $id) use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $userId = (int) $id;

    // Proteções
    if ($userId === $admin->id) {
        Session::set('flash', '⚠ ' . t('errors.users.cant_delete_self'));
        header('Location: /admin/users', true, 302);
        return;
    }
    $user = User::findById($userId);
    if ($user === null) {
        http_response_code(404);
        return;
    }
    if ($user->isAdmin()) {
        // Pode estar tentando deletar o único admin
        $admins = array_filter(User::listAll(), fn(User $u) => $u->isAdmin() && !$u->isDisabled());
        if (count($admins) <= 1) {
            $errors = [t('errors.users.cant_delete_last_admin')];
            $stats = ['archives' => 0, 'scans' => 0, 'audit_events' => 0];
            require $rootDir . '/templates/admin/users/delete.php';
            return;
        }
    }

    $typed = trim((string) ($_POST['confirm_username'] ?? ''));
    if ($typed !== $user->username) {
        $errors = [t('errors.users.delete_confirmation_mismatch')];
        $stats = ['archives' => 0, 'scans' => 0, 'audit_events' => 0];
        require $rootDir . '/templates/admin/users/delete.php';
        return;
    }

    // Audit ANTES de deletar (depois o user some)
    $originalUsername = $user->username;
    Audit::log('user_deleted', $admin->id, 'user', $userId,
        ['target_username' => $originalUsername, 'target_role' => $user->role],
        Http::clientIp(), Http::userAgent());

    // Hard delete. Audit_log.user_id vira NULL via FK (ON DELETE SET NULL),
    // preservando o histórico mesmo após exclusão.
    User::delete($userId);

    Session::set('flash', t('admin.users.flash_deleted', ['user' => htmlspecialchars($originalUsername, ENT_QUOTES, 'UTF-8')]));
    header('Location: /admin/users', true, 302);
});

// =====================================================================
// /admin/2fa/setup  (qualquer user autenticado)
// =====================================================================
$router->get('/admin/2fa/setup', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    $currentUser = $user;

    if ($user->totpEnabled) {
        // Já está ativo — vai direto pro profile
        header('Location: /admin/profile', true, 302);
        return;
    }

    // Gera segredo na primeira visita e mantém na sessão até confirmar.
    // Se o user recarregar, mantém o mesmo segredo até completar setup.
    Session::start();
    $secret = Session::get('totp_setup_secret');
    if (!is_string($secret) || $secret === '') {
        $secret = TwoFactor::generateSecret();
        Session::set('totp_setup_secret', $secret);
    }

    $uri = TwoFactor::provisioningUri($user->username, $secret);
    $qrSvg = TwoFactor::qrCodeSvg($uri, 240);
    $manualKey = $secret;
    require $rootDir . '/templates/admin/2fa/setup.php';
});

$router->post('/admin/2fa/setup', function () use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    $currentUser = $user;

    if ($user->totpEnabled) {
        header('Location: /admin/profile', true, 302);
        return;
    }

    Session::start();
    $secret = Session::get('totp_setup_secret');
    $code = (string) ($_POST['code'] ?? '');

    if (!is_string($secret) || $secret === '' || !TwoFactor::verifyCode($secret, $code)) {
        $errors = [t('errors.auth.totp_invalid')];
        $uri = TwoFactor::provisioningUri($user->username, (string) $secret);
        $qrSvg = TwoFactor::qrCodeSvg($uri, 240);
        $manualKey = (string) $secret;
        require $rootDir . '/templates/admin/2fa/setup.php';
        return;
    }

    // Ativa
    TwoFactor::activate($user->id, $secret);

    // Gera e armazena recovery codes (mostra na próxima request)
    $codes = TwoFactor::generateRecoveryCodes();
    TwoFactor::saveRecoveryCodes($user->id, $codes);
    Session::set('show_recovery_codes', $codes);
    Session::unset('totp_setup_secret');

    Audit::log('2fa_enabled', $user->id, null, null,
        ['role' => $user->role],
        Http::clientIp(), Http::userAgent());

    header('Location: /admin/2fa/recovery-codes', true, 302);
});

// Exibe os recovery codes (única vez). Se acessado fora desse fluxo, redirect.
$router->get('/admin/2fa/recovery-codes', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    $currentUser = $user;

    Session::start();
    $codes = Session::get('show_recovery_codes');
    if (!is_array($codes) || $codes === []) {
        header('Location: /admin/profile', true, 302);
        return;
    }
    require $rootDir . '/templates/admin/2fa/recovery-codes.php';
});

// Usuário confirma que anotou os códigos — apaga da sessão pra que não
// possam mais ser exibidos.
$router->post('/admin/2fa/recovery-codes/confirm', function () use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Session::start();
    Session::unset('show_recovery_codes');
    header('Location: /admin/dashboard', true, 302);
});

// =====================================================================
// /admin/2fa/verify  (após login, se totp_enabled)
// =====================================================================
$router->get('/admin/2fa/verify', function () use ($rootDir) {
    Session::start();
    $pendingId = Auth::pendingTwoFactorUserId();
    if ($pendingId === null) {
        header('Location: /admin/login', true, 302);
        return;
    }
    $useRecovery = isset($_GET['recovery']);
    require $rootDir . '/templates/admin/2fa/verify.php';
});

$router->post('/admin/2fa/verify', function () use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    Session::start();
    $pendingId = Auth::pendingTwoFactorUserId();
    if ($pendingId === null) {
        $errorMessage = t('errors.auth.pending_2fa_expired');
        require $rootDir . '/templates/admin/login.php';
        return;
    }

    $user = User::findById($pendingId);
    if ($user === null || $user->isDisabled() || !$user->totpEnabled) {
        Session::unset('pending_2fa_user_id');
        Session::unset('pending_2fa_started_at');
        header('Location: /admin/login', true, 302);
        return;
    }

    $code = (string) ($_POST['code'] ?? '');
    $isRecovery = isset($_GET['recovery']) || ($_POST['mode'] ?? '') === 'recovery';
    $useRecovery = $isRecovery;

    if ($isRecovery) {
        if (!TwoFactor::consumeRecoveryCode($user->id, $code)) {
            $errors = [t('errors.auth.recovery_code_invalid')];
            Audit::log('2fa_verify_failure', $user->id, null, null,
                ['mode' => 'recovery'],
                Http::clientIp(), Http::userAgent());
            require $rootDir . '/templates/admin/2fa/verify.php';
            return;
        }
        Audit::log('2fa_recovery_used', $user->id, null, null,
            ['remaining' => TwoFactor::remainingRecoveryCodes(User::findById($user->id))],
            Http::clientIp(), Http::userAgent());
    } else {
        $secret = TwoFactor::getSecret($user);
        if ($secret === null || !TwoFactor::verifyCode($secret, $code)) {
            $errors = [t('errors.auth.totp_invalid')];
            Audit::log('2fa_verify_failure', $user->id, null, null,
                ['mode' => 'totp'],
                Http::clientIp(), Http::userAgent());
            require $rootDir . '/templates/admin/2fa/verify.php';
            return;
        }
    }

    // 2FA OK — completa o login
    Auth::completeTwoFactorLogin($user, $isRecovery);

    if ($user->mustChangePassword) {
        header('Location: /admin/change-password', true, 302);
        return;
    }
    $returnTo = Session::get('return_to');
    Session::unset('return_to');
    $target = (is_string($returnTo) && str_starts_with($returnTo, '/admin/'))
        ? $returnTo
        : '/admin/dashboard';
    header('Location: ' . $target, true, 302);
});

// =====================================================================
// /admin/2fa/disable  (POST) — só pra curators; admins não podem
// =====================================================================
$router->post('/admin/2fa/disable', function () use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);

    if ($user->isAdmin()) {
        // Admin não pode desativar 2FA via UI (precisa do CLI)
        Session::set('flash', '⚠ Admin não pode desativar 2FA via interface. Use bin/disable-2fa.php se necessário.');
        header('Location: /admin/profile', true, 302);
        return;
    }
    if (!$user->totpEnabled) {
        header('Location: /admin/profile', true, 302);
        return;
    }

    TwoFactor::deactivate($user->id);
    Audit::log('2fa_disabled', $user->id, null, null,
        ['by' => 'self'],
        Http::clientIp(), Http::userAgent());

    Session::set('flash', '2FA desativado.');
    header('Location: /admin/profile', true, 302);
});

// =====================================================================
// /admin/categories  (admin only)
// =====================================================================
$router->get('/admin/categories', function () use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;
    $flatList = Category::listFlat();
    $flashMessage = Session::get('flash');
    Session::unset('flash');
    require $rootDir . '/templates/admin/categories/index.php';
});

$router->get('/admin/categories/new', function () use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $isEdit = false;
    $category = null;
    // Só categorias com depth < MAX podem ser parent
    $parents = array_filter(Category::listAll(), fn(Category $c) => $c->canHaveChildren());
    $oldParentId = isset($_GET['parent']) && ctype_digit((string) $_GET['parent'])
        ? (int) $_GET['parent']
        : null;
    require $rootDir . '/templates/admin/categories/form.php';
});

$router->post('/admin/categories/new', function () use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $isEdit  = false;
    $category = null;
    $oldName       = trim((string) ($_POST['name'] ?? ''));
    $oldSlug       = trim((string) ($_POST['slug'] ?? ''));
    $oldIcon       = (string) CategoryAttributes::normalizeIcon((string) ($_POST['icon'] ?? '')) ?: '';
    $oldColor      = (string) CategoryAttributes::normalizeColor((string) ($_POST['color'] ?? '')) ?: '';
    $oldSortOrder  = (int) ($_POST['sort_order'] ?? 0);
    $rawParent     = $_POST['parent_id'] ?? '';
    $oldParentId   = ($rawParent === '' || $rawParent === '0') ? null : (int) $rawParent;

    $errors = [];
    if ($oldName === '') {
        $errors[] = t('errors.categories.name_required');
    }
    if ($oldSlug !== '' && !preg_match('/^[a-z0-9-]+$/', Category::slugify($oldSlug))) {
        $errors[] = t('errors.categories.invalid_slug');
    }

    if ($errors === []) {
        try {
            $newId = Category::create(
                name:          $oldName,
                parentId:      $oldParentId,
                requestedSlug: $oldSlug !== '' ? $oldSlug : null,
                icon:          $oldIcon !== '' ? $oldIcon : null,
                color:         $oldColor !== '' ? $oldColor : null,
                sortOrder:     $oldSortOrder,
            );
            Audit::log('category_created', $admin->id, 'category', $newId,
                ['name' => $oldName, 'parent_id' => $oldParentId],
                Http::clientIp(), Http::userAgent());
            Session::set('flash', t('admin.categories.flash_created', [
                'name' => htmlspecialchars($oldName, ENT_QUOTES, 'UTF-8'),
            ]));
            header('Location: /admin/categories', true, 302);
            return;
        } catch (\DomainException $e) {
            $errors[] = $e->getMessage();
        }
    }

    $parents = array_filter(Category::listAll(), fn(Category $c) => $c->canHaveChildren());
    require $rootDir . '/templates/admin/categories/form.php';
});

$router->get('/admin/categories/(\d+)/edit', function (string $id) use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $category = Category::findById((int) $id);
    if ($category === null) {
        http_response_code(404);
        echo \ArkhamFiles\View::render('error', [
            'errorTitle'    => t('errors.categories.not_found'),
            'errorSubtitle' => 'CATEGORY',
            'errorCode'     => '404',
        ]);
        return;
    }
    $isEdit = true;
    $parents = [];  // não usado em edit (parent é imutável)
    require $rootDir . '/templates/admin/categories/form.php';
});

$router->post('/admin/categories/(\d+)/edit', function (string $id) use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $category = Category::findById((int) $id);
    if ($category === null) {
        http_response_code(404);
        return;
    }

    $isEdit = true;
    $oldName      = trim((string) ($_POST['name'] ?? ''));
    $oldSlug      = trim((string) ($_POST['slug'] ?? ''));
    $oldIcon      = (string) CategoryAttributes::normalizeIcon((string) ($_POST['icon'] ?? '')) ?: '';
    $oldColor     = (string) CategoryAttributes::normalizeColor((string) ($_POST['color'] ?? '')) ?: '';
    $oldSortOrder = (int) ($_POST['sort_order'] ?? 0);
    $oldParentId  = $category->parentId; // imutável

    $errors = [];
    if ($oldName === '') {
        $errors[] = t('errors.categories.name_required');
    }
    if ($oldSlug !== '' && !preg_match('/^[a-z0-9-]+$/', Category::slugify($oldSlug))) {
        $errors[] = t('errors.categories.invalid_slug');
    }

    if ($errors === []) {
        try {
            Category::update(
                id:            $category->id,
                name:          $oldName,
                requestedSlug: $oldSlug !== '' ? $oldSlug : null,
                icon:          $oldIcon !== '' ? $oldIcon : null,
                color:         $oldColor !== '' ? $oldColor : null,
                sortOrder:     $oldSortOrder,
            );
            Audit::log('category_updated', $admin->id, 'category', $category->id,
                ['name' => $oldName],
                Http::clientIp(), Http::userAgent());
            Session::set('flash', t('admin.categories.flash_updated', [
                'name' => htmlspecialchars($oldName, ENT_QUOTES, 'UTF-8'),
            ]));
            header('Location: /admin/categories', true, 302);
            return;
        } catch (\DomainException $e) {
            $errors[] = $e->getMessage();
        }
    }

    $parents = [];
    require $rootDir . '/templates/admin/categories/form.php';
});

$router->get('/admin/categories/(\d+)/delete', function (string $id) use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $category = Category::findById((int) $id);
    if ($category === null) {
        http_response_code(404);
        echo \ArkhamFiles\View::render('error', [
            'errorTitle'    => t('errors.categories.not_found'),
            'errorSubtitle' => 'CATEGORY',
            'errorCode'     => '404',
        ]);
        return;
    }
    $childCount = Category::childCount($category->id);
    $qrCount    = Category::qrCount($category->id);
    require $rootDir . '/templates/admin/categories/delete.php';
});

$router->post('/admin/categories/(\d+)/delete', function (string $id) use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $category = Category::findById((int) $id);
    if ($category === null) {
        http_response_code(404);
        return;
    }

    try {
        $originalName = $category->name;
        Category::delete($category->id);
        Audit::log('category_deleted', $admin->id, 'category', $category->id,
            ['name' => $originalName, 'slug' => $category->slug, 'depth' => $category->depth],
            Http::clientIp(), Http::userAgent());
        Session::set('flash', t('admin.categories.flash_deleted', [
            'name' => htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8'),
        ]));
        header('Location: /admin/categories', true, 302);
    } catch (\DomainException $e) {
        $errors = [$e->getMessage()];
        $childCount = Category::childCount($category->id);
        $qrCount    = Category::qrCount($category->id);
        require $rootDir . '/templates/admin/categories/delete.php';
    }
});

// =====================================================================
// 404
// =====================================================================
$router->set404(function () use ($rootDir) {
    http_response_code(404);
    $errorTitle    = t('errors.not_found.title');
    $errorSubtitle = t('errors.not_found.subtitle');
    $errorCode     = '404';
    require $rootDir . '/templates/error.php';
});

$router->run();
