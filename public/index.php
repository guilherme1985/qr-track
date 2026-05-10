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
    $currentUser = $user;
    require $rootDir . '/templates/admin/dashboard.php';
});

$router->get('/admin/settings', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    $currentUser = $user;
    require $rootDir . '/templates/admin/settings.php';
});

// =====================================================================
// /admin/users (admin only)
// =====================================================================
$router->get('/admin/users', function () use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    $currentUser = $admin;
    $users = User::listAll();
    $flashMessage = Session::get('flash');
    Session::unset('flash');
    require $rootDir . '/templates/admin/users/index.php';
});

$router->get('/admin/users/new', function () use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    $currentUser = $admin;
    require $rootDir . '/templates/admin/users/new.php';
});

$router->post('/admin/users/new', function () use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
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
