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
use ArkhamFiles\QrCode;
use ArkhamFiles\Note;
use ArkhamFiles\Markdown;
use ArkhamFiles\Strain;
use ArkhamFiles\ImageQr;
use ArkhamFiles\ImageUpload;
use ArkhamFiles\QrRenderer;
use ArkhamFiles\Http;
use ArkhamFiles\Maintenance;

$rootDir = dirname(__DIR__);
Bootstrap::init($rootDir);
Audit::maybePurge();

// =====================================================================
// MIDDLEWARE: modo manutenção
//
// Checa data/maintenance.flag em CADA request. Se o arquivo existe e:
//   - a rota não está na lista de exceções (login, healthz, assets)
//   - o usuário NÃO é admin logado (admin sempre tem bypass)
// → renderiza tela 503 de manutenção e encerra.
//
// Admins continuam acessando normalmente (e vêem banner de alerta no dashboard).
// =====================================================================
if (Maintenance::isActive()) {
    $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (!Maintenance::shouldBypass($currentUri)) {
        // Tenta detectar admin logado sem bloquear se a sessão não existir
        $bypass = false;
        try {
            Session::start();
            $current = Auth::currentUser();
            if ($current !== null && $current->isAdmin()) {
                $bypass = true;
            }
        } catch (\Throwable $e) {
            // Se algo falhar, segue com a tela de manutenção
        }
        if (!$bypass) {
            Maintenance::render($rootDir);
        }
    }
}

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
// Welcome (público) e Healthz (smoke test)
// =====================================================================
$router->get('/', function () use ($rootDir) {
    require $rootDir . '/templates/welcome.php';
});

$router->get('/healthz', function () use ($rootDir) {
    require $rootDir . '/templates/healthz.php';
});

// =====================================================================
// Public QR image endpoints  GET /p/{public_id}.svg  e  .png
//
// Geram a imagem QR (SVG vetorial ou PNG raster) que codifica a URL
// pública /p/{public_id}. Públicos, sem autenticação — mas seguem a
// MESMA regra de visibilidade do viewer:
//   - QR ativo/expirando → 200 SVG/PNG
//   - QR expirado        → 410 (não emite QR de documento arquivado)
//   - deleted/disabled   → 404
//   - não existe         → 404
//
// Query string opcional `?size=small|medium|large` (default medium)
// Query string opcional `?plain=1` força ignorar logo da categoria
//
// Cache: 1h público — QR de um documento ativo é estável durante esse
// período (apenas a categoria poderia mudar o ícone, mas isso é raro).
//
// IMPORTANTE: estes endpoints precisam estar declarados ANTES do
// /p/{public_id} genérico, senão o roteador casa o genérico primeiro.
// =====================================================================
$qrImageHandler = function (string $publicId, string $format) use ($rootDir) {
    $publicId = strtolower($publicId);

    if (!preg_match('/^[a-f0-9]{4}-[a-f0-9]{2}$/', $publicId)) {
        http_response_code(404);
        echo 'Not found';
        return;
    }

    $qr = QrCode::findByPublicId($publicId);
    if ($qr === null || $qr->isDeleted || $qr->isDisabled) {
        http_response_code(404);
        echo 'Not found';
        return;
    }
    if ($qr->isExpired()) {
        http_response_code(410);
        echo 'Gone';
        return;
    }

    // Determina tamanho (PNG só)
    $size = QrRenderer::SIZE_MEDIUM;
    $sizeParam = $_GET['size'] ?? 'medium';
    if ($sizeParam === 'small')  $size = QrRenderer::SIZE_SMALL;
    elseif ($sizeParam === 'large')   $size = QrRenderer::SIZE_LARGE;

    // Ícone da categoria como logo (a menos que ?plain=1)
    $logoPath = null;
    if (empty($_GET['plain']) && $qr->categoryId !== null) {
        $cat = Category::findById($qr->categoryId);
        $logoPath = QrRenderer::categoryIconPath($cat, $rootDir);
    }

    // URL absoluta — pega scheme + host atual
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $publicUrl = "{$scheme}://{$host}/p/{$publicId}";

    try {
        $result = QrRenderer::render($publicUrl, $format, $size, $logoPath);
    } catch (\Throwable $e) {
        http_response_code(500);
        error_log("[QR render] {$e->getMessage()}");
        echo 'Internal error';
        return;
    }

    header('Content-Type: ' . $result['mime_type']);
    header('Cache-Control: public, max-age=3600');
    header('Content-Length: ' . strlen($result['content']));
    echo $result['content'];
};

$router->get('/p/([A-Za-z0-9-]+)\.svg', function (string $publicId) use ($qrImageHandler) {
    $qrImageHandler($publicId, 'svg');
});
$router->get('/p/([A-Za-z0-9-]+)\.png', function (string $publicId) use ($qrImageHandler) {
    $qrImageHandler($publicId, 'png');
});

// =====================================================================
// Public scan endpoint  GET /p/{public_id}
//
// Fluxo:
//   1. Valida formato do public_id (anti-noise: bots, fuzzing)
//   2. Lookup; se não existe → tela "Paciente não localizado"
//   3. Registra scan (com flag was_expired)
//   4. Decide o que mostrar baseado em status:
//        - deleted/disabled → tela "Paciente não localizado" (não vazar
//                              existência de QRs removidos)
//        - expired          → tela "Caso arquivado"
//        - válido           → viewer placeholder (PR 07/08/09 substituem
//                              por viewers específicos por tipo)
// =====================================================================
$router->get('/p/([A-Za-z0-9-]+)', function (string $publicId) use ($rootDir) {
    // Normaliza pra lowercase (case-insensitive matching nos QR labels)
    $publicId = strtolower($publicId);

    // Formato esperado: XXXX-XX (4 hex + 2 hex). Validação leve aqui
    // pra evitar DB hits em URLs claramente malformadas.
    if (!preg_match('/^[a-f0-9]{4}-[a-f0-9]{2}$/', $publicId)) {
        http_response_code(404);
        $errorTitle    = t('errors.not_found.title');
        $errorSubtitle = t('errors.not_found.subtitle');
        $errorCode     = '404';
        require $rootDir . '/templates/error.php';
        return;
    }

    $qr = QrCode::findByPublicId($publicId);

    // Não existe → 404 temático
    if ($qr === null) {
        http_response_code(404);
        $errorTitle    = t('errors.not_found.title');
        $errorSubtitle = t('errors.not_found.subtitle');
        $errorCode     = '404';
        require $rootDir . '/templates/error.php';
        return;
    }

    // Registra o scan SEMPRE — mesmo de QRs expirados/disabled.
    // Útil pra auditoria forense e analytics de QRs impressos antigos.
    $qr->recordScan(
        ip:        Http::clientIp(),
        userAgent: Http::userAgent(),
        referer:   $_SERVER['HTTP_REFERER'] ?? null,
    );

    // Soft-deleted ou disabled: tratar como "não existe" pra não
    // vazar informação ("ah, esse QR existiu mas foi pausado")
    if ($qr->isDeleted || $qr->isDisabled) {
        http_response_code(404);
        $errorTitle    = t('errors.not_found.title');
        $errorSubtitle = t('errors.not_found.subtitle');
        $errorCode     = '404';
        require $rootDir . '/templates/error.php';
        return;
    }

    // Expirado: tela temática "Caso arquivado"
    if ($qr->isExpired()) {
        http_response_code(410); // Gone — semanticamente correto pra expirado
        $errorTitle    = t('errors.expired.title');
        $errorSubtitle = t('errors.expired.subtitle');
        $errorCode     = '410';
        // O template error genérico pega kicker/title/subtitle/body
        // automaticamente baseado em context — vamos usar a versão expired.
        $errorKicker      = t('errors.expired.kicker');
        $errorBody        = t('errors.expired.body');
        $errorArchivedOn  = $qr->expiresAt ? substr($qr->expiresAt, 0, 10) : '';
        $errorRetention   = t('errors.expired.retention_note');
        require $rootDir . '/templates/error.php';
        return;
    }

    // QR válido — renderiza viewer específico do tipo
    $scanCount = (int) \ArkhamFiles\Database::pdo()
        ->query('SELECT COUNT(*) FROM scans WHERE qr_id = ' . (int) $qr->id)
        ->fetchColumn();

    switch ($qr->type) {
        case 'note':
            $rawMarkdown = Note::getMarkdown($qr->id);
            $renderedHtml = Markdown::render($rawMarkdown);
            $category = $qr->categoryId ? Category::findById($qr->categoryId) : null;
            require $rootDir . '/templates/public/note-viewer.php';
            break;

        case 'strain':
            $strain = Strain::findByQrId($qr->id);
            if ($strain === null) {
                // Inconsistência: qrcode marcado como strain mas sem metadata
                http_response_code(500);
                $errorTitle    = t('errors.not_found.title');
                $errorSubtitle = 'STRAIN METADATA MISSING';
                $errorCode     = '500';
                require $rootDir . '/templates/error.php';
                return;
            }
            $category = $qr->categoryId ? Category::findById($qr->categoryId) : null;
            require $rootDir . '/templates/public/strain-viewer.php';
            break;

        case 'image':
            $img = ImageQr::findByQrId($qr->id);
            if ($img === null) {
                http_response_code(500);
                $errorTitle    = t('errors.not_found.title');
                $errorSubtitle = 'IMAGE METADATA MISSING';
                $errorCode     = '500';
                require $rootDir . '/templates/error.php';
                return;
            }
            $category = $qr->categoryId ? Category::findById($qr->categoryId) : null;
            require $rootDir . '/templates/public/image-viewer.php';
            break;

        // Outros tipos caem aqui — viewer placeholder genérico.
        default:
            require $rootDir . '/templates/public/viewer-placeholder.php';
    }
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

    $pdo = \ArkhamFiles\Database::pdo();

    // Contagens por status (apenas tipos ricos: note/strain/image)
    $now = gmdate('Y-m-d H:i:s');
    $expiringThreshold = gmdate('Y-m-d H:i:s', strtotime('+7 days'));

    $statQuery = $pdo->query("
        SELECT
          SUM(CASE WHEN is_deleted=0 AND is_disabled=0
                    AND type IN ('note','strain','image')
                    AND (expires_at IS NULL OR expires_at > '{$now}') THEN 1 ELSE 0 END) AS active,
          SUM(CASE WHEN is_deleted=0 AND is_disabled=0
                    AND type IN ('note','strain','image')
                    AND expires_at IS NOT NULL
                    AND expires_at > '{$now}'
                    AND expires_at <= '{$expiringThreshold}' THEN 1 ELSE 0 END) AS expiring,
          SUM(CASE WHEN is_deleted=0 AND is_disabled=0
                    AND type IN ('note','strain','image')
                    AND expires_at IS NOT NULL
                    AND expires_at <= '{$now}' THEN 1 ELSE 0 END) AS expired,
          SUM(CASE WHEN is_deleted=1
                    AND type IN ('note','strain','image') THEN 1 ELSE 0 END) AS archived
        FROM qrcodes
    ")->fetch(\PDO::FETCH_ASSOC) ?: [];

    $scans24hCount = (int) $pdo->query("
        SELECT COUNT(*) FROM scans
         WHERE scanned_at > datetime('now', '-1 day')
    ")->fetchColumn();

    $stats = [
        ['label' => t('admin.dashboard.stat_active'),    'value' => (string) ($statQuery['active']   ?? 0), 'tone' => 'af-phosphor'],
        ['label' => t('admin.dashboard.stat_expiring'),  'value' => (string) ($statQuery['expiring'] ?? 0), 'tone' => 'af-gold'],
        ['label' => t('admin.dashboard.stat_expired'),   'value' => (string) ($statQuery['expired']  ?? 0), 'tone' => 'af-blood'],
        ['label' => t('admin.dashboard.stat_archived'),  'value' => (string) ($statQuery['archived'] ?? 0), 'tone' => 'af-mute'],
        ['label' => t('admin.dashboard.stat_scans_24h'), 'value' => (string) $scans24hCount, 'tone' => ''],
    ];

    // Contagem por tipo (ativos não-arquivados)
    $countByTypeRaw = $pdo->query("
        SELECT type, COUNT(*) as c FROM qrcodes
         WHERE is_deleted=0 AND type IN ('note','strain','image')
         GROUP BY type
    ")->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
    $countByType = [
        'note'   => (int) ($countByTypeRaw['note']   ?? 0),
        'strain' => (int) ($countByTypeRaw['strain'] ?? 0),
        'image'  => (int) ($countByTypeRaw['image']  ?? 0),
    ];

    // Últimos 5 QRs criados (filtra por todos os tipos ricos)
    $allRecent = [];
    foreach (['note', 'strain', 'image'] as $t) {
        $items = QrCode::listWithFilters(['type' => $t, 'status' => 'all-active']);
        foreach ($items as $qr) {
            $allRecent[] = $qr;
        }
    }
    // Ordena por createdAt desc e pega top 5
    usort($allRecent, fn($a, $b) => strcmp($b->createdAt, $a->createdAt));
    $recentQrs = array_slice($allRecent, 0, 5);

    // Últimos 8 scans (com title do QR)
    $recentScans = $pdo->query("
        SELECT s.scanned_at, s.ip_address,
               q.public_id, q.title, q.type
          FROM scans s
          JOIN qrcodes q ON q.id = s.qr_id
         ORDER BY s.scanned_at DESC
         LIMIT 8
    ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    // Últimas 8 entradas relevantes do audit log
    $recentAudit = $pdo->query("
        SELECT a.event_type, a.created_at, a.ip_address,
               u.username
          FROM audit_log a
          LEFT JOIN users u ON u.id = a.user_id
         WHERE a.event_type IN (
             'login_success', 'login_failed', 'logout',
             'qrcode_created', 'qrcode_updated', 'qrcode_archived',
             'qrcode_hard_deleted', 'qrcode_restored',
             'user_created', 'user_deleted',
             'maintenance_enabled', 'maintenance_disabled'
         )
         ORDER BY a.created_at DESC
         LIMIT 8
    ")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    $maintenanceActive = Maintenance::isActive();

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
// /admin/settings/maintenance — toggle do modo manutenção (admin only)
// =====================================================================
$router->get('/admin/settings/maintenance', function () use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $isActive = Maintenance::isActive();
    $currentMessage = $isActive ? Maintenance::message() : '';
    $flashMessage = Session::get('flash');
    Session::unset('flash');
    require $rootDir . '/templates/admin/settings/maintenance.php';
});

$router->post('/admin/settings/maintenance/enable', function () use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);

    $message = trim((string) ($_POST['message'] ?? ''));
    if (mb_strlen($message) > 500) {
        $message = mb_substr($message, 0, 500);
    }

    $ok = Maintenance::enable($message);
    if ($ok) {
        Audit::log('maintenance_enabled', $admin->id, 'system', null,
            ['message' => $message !== '' ? $message : '(default)'],
            Http::clientIp(), Http::userAgent());
        Session::set('flash', t('admin.maintenance.flash_enabled'));
    } else {
        Session::set('flash', t('admin.maintenance.flash_error'));
    }
    header('Location: /admin/settings/maintenance', true, 302);
});

$router->post('/admin/settings/maintenance/disable', function () use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);

    $ok = Maintenance::disable();
    if ($ok) {
        Audit::log('maintenance_disabled', $admin->id, 'system', null,
            [], Http::clientIp(), Http::userAgent());
        Session::set('flash', t('admin.maintenance.flash_disabled'));
    } else {
        Session::set('flash', t('admin.maintenance.flash_error'));
    }
    header('Location: /admin/settings/maintenance', true, 302);
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
// /admin/{type}s/{id}/qr — Página de visualização e download do QR
//
// Funciona pros 3 tipos (notes, strains, images). Renderiza preview
// grande, metadata e 4 botões de download (SVG + 3 PNGs).
//
// Permissões: qualquer usuário logado. Não tem ação destrutiva, só
// visualização. A URL pública /p/xxxx-xx.svg já é pública mesmo.
// =====================================================================
$router->get('/admin/(notes|strains|images)/(\d+)/qr', function (string $typePlural, string $id) use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    // notes → note, strains → strain, images → image
    $type = rtrim($typePlural, 's');

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== $type || $qr->isDeleted) {
        http_response_code(404);
        $errorTitle    = t('errors.not_found.title');
        $errorSubtitle = strtoupper($type) . ' NOT FOUND';
        $errorCode     = '404';
        require $rootDir . '/templates/error.php';
        return;
    }

    // Verifica disponibilidade do ícone da categoria
    $iconAvailable = false;
    if ($qr->categoryId !== null) {
        $cat = Category::findById($qr->categoryId);
        $iconAvailable = (QrRenderer::categoryIconPath($cat, $rootDir) !== null);
    }

    // URL pública (mesmo host)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $publicUrl = "{$scheme}://{$host}/p/{$qr->publicId}";

    require $rootDir . '/templates/admin/qrcodes/qr-view.php';
});

// =====================================================================
// /admin/notes  — CRUD de notas (admin + curator)
//
// Permissões:
//   - Listagem: qualquer usuário logado (curator vê só as próprias)
//   - Criar: qualquer usuário logado
//   - Editar/arquivar: autor da nota OU admin
//   - Restaurar/hard-delete: admin only
// =====================================================================

// Helper de validação de input do form (compartilhado por new e edit)
$validateNoteInput = function (array $post) {
    $errors = [];

    $title = trim((string) ($post['title'] ?? ''));
    if ($title === '') {
        $errors[] = 'Título é obrigatório.';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = 'Título excede o limite de 200 caracteres.';
    }

    $markdown = (string) ($post['markdown'] ?? '');
    if (strlen($markdown) > Markdown::MAX_LENGTH_BYTES) {
        $kb = number_format(Markdown::MAX_LENGTH_BYTES / 1024, 0);
        $errors[] = "Conteúdo Markdown excede o limite de {$kb} KB.";
    }

    $catRaw = $post['category_id'] ?? '';
    $categoryId = ($catRaw === '' || $catRaw === '0') ? null : (int) $catRaw;
    if ($categoryId !== null && Category::findById($categoryId) === null) {
        $errors[] = 'Categoria selecionada não existe.';
        $categoryId = null;
    }

    // Expiração
    $expRadio = (string) ($post['expires_radio'] ?? 'none');
    $expiresAt = null;
    if ($expRadio === '30') {
        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+30 days'));
    } elseif ($expRadio === '90') {
        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+90 days'));
    } elseif ($expRadio === '365') {
        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+365 days'));
    } elseif ($expRadio === 'custom') {
        $customDate = trim((string) ($post['expires_custom'] ?? ''));
        if ($customDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customDate)) {
            $expiresAt = $customDate . ' 23:59:59';
        } else {
            $errors[] = 'Data de expiração customizada inválida.';
        }
    }

    return [$errors, $title, $markdown, $categoryId, $expiresAt];
};

// GET /admin/notes — listagem com filtros
$router->get('/admin/notes', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $filters = [
        'status'      => $_GET['status']      ?? 'all-active',
        'category_id' => isset($_GET['category_id']) && $_GET['category_id'] !== ''
                          ? (int) $_GET['category_id'] : null,
        'mine'        => !empty($_GET['mine']),
        'search'      => trim((string) ($_GET['q'] ?? '')),
    ];

    // Constrói query — curador SEMPRE filtra por created_by
    $queryFilters = ['type' => 'note', 'status' => $filters['status']];
    if ($filters['category_id'] !== null) {
        $queryFilters['category_id'] = $filters['category_id'];
    }
    if (!$user->isAdmin()) {
        // curador: vê só as próprias, sempre
        $queryFilters['created_by'] = $user->id;
    } elseif ($filters['mine']) {
        // admin com "apenas minhas" marcado
        $queryFilters['created_by'] = $user->id;
    }
    if ($filters['search'] !== '') {
        $queryFilters['search'] = $filters['search'];
    }

    $notes = QrCode::listWithFilters($queryFilters);
    $totalCount = count($notes);
    $categories = Category::listAll();
    $flashMessage = Session::get('flash');
    Session::unset('flash');
    require $rootDir . '/templates/admin/qrcodes/notes/index.php';
});

// GET /admin/notes/new — form de criação
$router->get('/admin/notes/new', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $isEdit = false;
    $qr = null;
    $categories = Category::listFlat();
    $categories = array_map(fn($n) => $n['category'], $categories);
    require $rootDir . '/templates/admin/qrcodes/notes/form.php';
});

// POST /admin/notes/new — submete criação
$router->post('/admin/notes/new', function () use ($rootDir, $verifyCsrf, $validateNoteInput) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    [$errors, $title, $markdown, $categoryId, $expiresAt] = $validateNoteInput($_POST);

    if ($errors === []) {
        try {
            $result = Note::create(
                title:           $title,
                markdownContent: $markdown,
                categoryId:      $categoryId,
                createdBy:       $user->id,
                expiresAt:       $expiresAt,
            );
            Audit::log('qrcode_created', $user->id, 'qrcode', $result['id'],
                ['type' => 'note', 'public_id' => $result['public_id'], 'title' => $title],
                Http::clientIp(), Http::userAgent());
            Session::set('flash', t('admin.notes.flash_created', [
                'title'      => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                'dossier_id' => htmlspecialchars($result['public_id'], ENT_QUOTES, 'UTF-8'),
            ]));
            header('Location: /admin/notes', true, 302);
            return;
        } catch (\DomainException $e) {
            $errors[] = $e->getMessage();
        }
    }

    // re-render form com erros
    $isEdit = false;
    $qr = null;
    $oldTitle      = $title;
    $oldMarkdown   = $markdown;
    $oldCategoryId = $categoryId;
    $oldExpiresAt  = $expiresAt;
    $categories = Category::listFlat();
    $categories = array_map(fn($n) => $n['category'], $categories);
    require $rootDir . '/templates/admin/qrcodes/notes/form.php';
});

// GET /admin/notes/{id}/edit — form de edição
$router->get('/admin/notes/(\d+)/edit', function (string $id) use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'note' || $qr->isDeleted) {
        http_response_code(404);
        $errorTitle    = t('errors.not_found.title');
        $errorSubtitle = 'NOTE NOT FOUND';
        $errorCode     = '404';
        require $rootDir . '/templates/error.php';
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        $errorTitle    = 'Acesso negado';
        $errorSubtitle = 'PERMISSION DENIED';
        $errorCode     = '403';
        $errorBody     = t('admin.notes.permission_denied');
        require $rootDir . '/templates/error.php';
        return;
    }

    $isEdit = true;
    $categories = Category::listFlat();
    $categories = array_map(fn($n) => $n['category'], $categories);
    require $rootDir . '/templates/admin/qrcodes/notes/form.php';
});

// POST /admin/notes/{id}/edit — submete edição
$router->post('/admin/notes/(\d+)/edit', function (string $id) use ($rootDir, $verifyCsrf, $validateNoteInput) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'note' || $qr->isDeleted) {
        http_response_code(404);
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        return;
    }

    [$errors, $title, $markdown, $categoryId, $expiresAt] = $validateNoteInput($_POST);

    if ($errors === []) {
        try {
            Note::update(
                qrId:            $qr->id,
                title:           $title,
                markdownContent: $markdown,
                categoryId:      $categoryId,
                expiresAt:       $expiresAt,
            );
            Audit::log('qrcode_updated', $user->id, 'qrcode', $qr->id,
                ['type' => 'note', 'title' => $title],
                Http::clientIp(), Http::userAgent());
            Session::set('flash', t('admin.notes.flash_updated', [
                'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            ]));
            header('Location: /admin/notes', true, 302);
            return;
        } catch (\DomainException $e) {
            $errors[] = $e->getMessage();
        }
    }

    $isEdit = true;
    $oldTitle      = $title;
    $oldMarkdown   = $markdown;
    $oldCategoryId = $categoryId;
    $oldExpiresAt  = $expiresAt;
    $categories = Category::listFlat();
    $categories = array_map(fn($n) => $n['category'], $categories);
    require $rootDir . '/templates/admin/qrcodes/notes/form.php';
});

// GET /admin/notes/{id}/delete — confirmação de arquivamento (soft)
$router->get('/admin/notes/(\d+)/delete', function (string $id) use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'note' || $qr->isDeleted) {
        http_response_code(404);
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        return;
    }
    require $rootDir . '/templates/admin/qrcodes/notes/delete.php';
});

// POST /admin/notes/{id}/delete — executa arquivamento (soft-delete)
$router->post('/admin/notes/(\d+)/delete', function (string $id) use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'note' || $qr->isDeleted) {
        http_response_code(404);
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        return;
    }

    QrCode::softDelete($qr->id);
    Audit::log('qrcode_archived', $user->id, 'qrcode', $qr->id,
        ['type' => 'note', 'title' => $qr->title, 'public_id' => $qr->publicId],
        Http::clientIp(), Http::userAgent());
    Session::set('flash', t('admin.notes.flash_archived', [
        'title' => htmlspecialchars($qr->title, ENT_QUOTES, 'UTF-8'),
    ]));
    header('Location: /admin/notes', true, 302);
});

// POST /admin/notes/{id}/restore — restaura (admin only)
$router->post('/admin/notes/(\d+)/restore', function (string $id) use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'note') {
        http_response_code(404);
        return;
    }
    QrCode::restore($qr->id);
    Audit::log('qrcode_restored', $admin->id, 'qrcode', $qr->id,
        ['type' => 'note', 'title' => $qr->title],
        Http::clientIp(), Http::userAgent());
    Session::set('flash', t('admin.notes.flash_restored', [
        'title' => htmlspecialchars($qr->title, ENT_QUOTES, 'UTF-8'),
    ]));
    header('Location: /admin/notes?status=deleted', true, 302);
});

// GET /admin/notes/{id}/restore — fallback pra link na listagem (re-rota pro POST)
$router->get('/admin/notes/(\d+)/restore', function (string $id) {
    Auth::requireRole(User::ROLE_ADMIN);
    // Sem CSRF em GET — pra preservar segurança, redireciona pra
    // listagem e força o admin a usar o botão POST (a partir do
    // sistema de flash messages com formulário inline depois).
    Session::set('flash', 'Use o botão de ação na listagem para restaurar.');
    header('Location: /admin/notes?status=deleted', true, 302);
});

// GET /admin/notes/{id}/delete-hard — confirmação de exclusão definitiva (admin only)
$router->get('/admin/notes/(\d+)/delete-hard', function (string $id) use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'note') {
        http_response_code(404);
        return;
    }
    if (!$qr->isDeleted) {
        // Só permite hard-delete de notas já arquivadas (defesa contra cliques acidentais)
        Session::set('flash', 'Apenas notas arquivadas podem ser excluídas permanentemente.');
        header('Location: /admin/notes/' . $qr->id . '/edit', true, 302);
        return;
    }
    $scanCount = $qr->scanCount();
    require $rootDir . '/templates/admin/qrcodes/notes/delete-hard.php';
});

// POST /admin/notes/{id}/delete-hard — executa hard-delete (admin only)
$router->post('/admin/notes/(\d+)/delete-hard', function (string $id) use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'note') {
        http_response_code(404);
        return;
    }
    if (!$qr->isDeleted) {
        http_response_code(400);
        return;
    }

    $confirmTitle = trim((string) ($_POST['confirm_title'] ?? ''));
    if ($confirmTitle !== $qr->title) {
        $errors = [t('admin.notes.hard_delete_mismatch')];
        $scanCount = $qr->scanCount();
        require $rootDir . '/templates/admin/qrcodes/notes/delete-hard.php';
        return;
    }

    $originalTitle = $qr->title;
    $originalPublicId = $qr->publicId;
    $scanCount = $qr->scanCount();

    QrCode::hardDelete($qr->id);
    Audit::log('qrcode_hard_deleted', $admin->id, 'qrcode', $qr->id,
        ['type' => 'note', 'title' => $originalTitle,
         'public_id' => $originalPublicId, 'scans_deleted' => $scanCount],
        Http::clientIp(), Http::userAgent());
    Session::set('flash', t('admin.notes.flash_hard_deleted'));
    header('Location: /admin/notes', true, 302);
});

// =====================================================================
// /admin/strains  — CRUD de dossiês botânicos
//
// Mesma regra de permissão das notas:
//   - Listar: qualquer usuário logado (curator vê só os próprios)
//   - Criar: qualquer usuário logado
//   - Editar/arquivar: autor OU admin
//   - Restaurar/hard-delete: admin only
// =====================================================================

// Helper de validação de input do form
$validateStrainInput = function (array $post): array {
    $errors = [];

    $title = trim((string) ($post['title'] ?? ''));
    if ($title === '') {
        $errors[] = 'Identificação do dossiê é obrigatória.';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = 'Identificação excede 200 caracteres.';
    }

    $strainData = [
        'strain_name'    => trim((string) ($post['strain_name'] ?? '')),
        'source'         => (string) ($post['source']   ?? 'semente'),
        'genetics'       => (string) ($post['genetics'] ?? 'hibrida'),
        'seed_type'      => (string) ($post['seed_type'] ?? ''),
        'planting_date'  => trim((string) ($post['planting_date']  ?? '')) ?: null,
        'flowering_date' => trim((string) ($post['flowering_date'] ?? '')) ?: null,
        'harvest_date'   => trim((string) ($post['harvest_date']   ?? '')) ?: null,
    ];

    $catRaw = $post['category_id'] ?? '';
    $categoryId = ($catRaw === '' || $catRaw === '0') ? null : (int) $catRaw;
    if ($categoryId !== null && Category::findById($categoryId) === null) {
        $errors[] = 'Categoria selecionada não existe.';
        $categoryId = null;
    }

    // Expiração (mesma lógica do PR 07)
    $expRadio = (string) ($post['expires_radio'] ?? 'none');
    $expiresAt = null;
    if ($expRadio === '30')       $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+30 days'));
    elseif ($expRadio === '90')   $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+90 days'));
    elseif ($expRadio === '365')  $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+365 days'));
    elseif ($expRadio === 'custom') {
        $cd = trim((string) ($post['expires_custom'] ?? ''));
        if ($cd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cd)) {
            $expiresAt = $cd . ' 23:59:59';
        } else {
            $errors[] = 'Data de expiração customizada inválida.';
        }
    }

    return [$errors, $title, $strainData, $categoryId, $expiresAt];
};

// GET /admin/strains — listagem
$router->get('/admin/strains', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $filters = [
        'genetics' => $_GET['genetics'] ?? '',
        'phase'    => $_GET['phase']    ?? '',
        'mine'     => !empty($_GET['mine']),
        'search'   => trim((string) ($_GET['q'] ?? '')),
    ];

    $queryFilters = ['type' => 'strain'];
    if (!$user->isAdmin()) {
        $queryFilters['created_by'] = $user->id;
    } elseif ($filters['mine']) {
        $queryFilters['created_by'] = $user->id;
    }
    if ($filters['search'] !== '') {
        $queryFilters['search'] = $filters['search'];
    }

    $all = QrCode::listWithFilters($queryFilters);

    // Filtros pós-query (genetics e phase precisam do strain_metadata)
    $strains = [];
    foreach ($all as $qr) {
        $sm = Strain::findByQrId($qr->id);
        if (!$sm) continue;
        if ($filters['genetics'] !== '' && $sm->genetics !== $filters['genetics']) continue;
        if ($filters['phase'] !== '' && $sm->lifecyclePhase() !== $filters['phase']) continue;
        $strains[] = $qr;
    }

    $totalCount = count($strains);
    $categories = Category::listAll();
    $flashMessage = Session::get('flash');
    Session::unset('flash');
    require $rootDir . '/templates/admin/qrcodes/strains/index.php';
});

// GET /admin/strains/new — form
$router->get('/admin/strains/new', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $isEdit = false;
    $qr = null;
    $strain = null;
    $categories = array_map(fn($n) => $n['category'], Category::listFlat());
    require $rootDir . '/templates/admin/qrcodes/strains/form.php';
});

// POST /admin/strains/new — submete criação
$router->post('/admin/strains/new', function () use ($rootDir, $verifyCsrf, $validateStrainInput) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    [$errors, $title, $strainData, $categoryId, $expiresAt] = $validateStrainInput($_POST);

    if ($errors === []) {
        try {
            $result = Strain::create(
                title:       $title,
                strainData:  $strainData,
                categoryId:  $categoryId,
                createdBy:   $user->id,
                expiresAt:   $expiresAt,
            );
            Audit::log('qrcode_created', $user->id, 'qrcode', $result['id'],
                ['type' => 'strain', 'public_id' => $result['public_id'],
                 'title' => $title, 'strain_name' => $strainData['strain_name']],
                Http::clientIp(), Http::userAgent());
            Session::set('flash', t('admin.strains.flash_created', [
                'title'      => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                'dossier_id' => htmlspecialchars($result['public_id'], ENT_QUOTES, 'UTF-8'),
            ]));
            header('Location: /admin/strains', true, 302);
            return;
        } catch (\DomainException $e) {
            $errors[] = $e->getMessage();
        }
    }

    // re-render
    $isEdit = false;
    $qr = null;
    $strain = null;
    $oldTitle      = $title;
    $oldCategoryId = $categoryId;
    $oldExpiresAt  = $expiresAt;
    $oldStrainName = $strainData['strain_name'];
    $oldSource     = $strainData['source'];
    $oldGenetics   = $strainData['genetics'];
    $oldSeedType   = $strainData['seed_type'];
    $oldPlanting   = $strainData['planting_date'];
    $oldFlowering  = $strainData['flowering_date'];
    $oldHarvest    = $strainData['harvest_date'];
    $categories    = array_map(fn($n) => $n['category'], Category::listFlat());
    require $rootDir . '/templates/admin/qrcodes/strains/form.php';
});

// GET /admin/strains/{id}/edit — form
$router->get('/admin/strains/(\d+)/edit', function (string $id) use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'strain' || $qr->isDeleted) {
        http_response_code(404);
        $errorTitle    = t('errors.not_found.title');
        $errorSubtitle = 'STRAIN NOT FOUND';
        $errorCode     = '404';
        require $rootDir . '/templates/error.php';
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        $errorTitle    = 'Acesso negado';
        $errorSubtitle = 'PERMISSION DENIED';
        $errorCode     = '403';
        $errorBody     = t('admin.strains.permission_denied');
        require $rootDir . '/templates/error.php';
        return;
    }
    $isEdit = true;
    $strain = Strain::findByQrId($qr->id);
    $categories = array_map(fn($n) => $n['category'], Category::listFlat());
    require $rootDir . '/templates/admin/qrcodes/strains/form.php';
});

// POST /admin/strains/{id}/edit
$router->post('/admin/strains/(\d+)/edit', function (string $id) use ($rootDir, $verifyCsrf, $validateStrainInput) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'strain' || $qr->isDeleted) {
        http_response_code(404);
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        return;
    }

    [$errors, $title, $strainData, $categoryId, $expiresAt] = $validateStrainInput($_POST);

    if ($errors === []) {
        try {
            Strain::update(
                qrId:        $qr->id,
                title:       $title,
                strainData:  $strainData,
                categoryId:  $categoryId,
                expiresAt:   $expiresAt,
            );
            Audit::log('qrcode_updated', $user->id, 'qrcode', $qr->id,
                ['type' => 'strain', 'title' => $title],
                Http::clientIp(), Http::userAgent());
            Session::set('flash', t('admin.strains.flash_updated', [
                'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            ]));
            header('Location: /admin/strains', true, 302);
            return;
        } catch (\DomainException $e) {
            $errors[] = $e->getMessage();
        }
    }

    $isEdit = true;
    $strain = Strain::findByQrId($qr->id);
    $oldTitle      = $title;
    $oldCategoryId = $categoryId;
    $oldExpiresAt  = $expiresAt;
    $oldStrainName = $strainData['strain_name'];
    $oldSource     = $strainData['source'];
    $oldGenetics   = $strainData['genetics'];
    $oldSeedType   = $strainData['seed_type'];
    $oldPlanting   = $strainData['planting_date'];
    $oldFlowering  = $strainData['flowering_date'];
    $oldHarvest    = $strainData['harvest_date'];
    $categories    = array_map(fn($n) => $n['category'], Category::listFlat());
    require $rootDir . '/templates/admin/qrcodes/strains/form.php';
});

// GET /admin/strains/{id}/delete — confirmação soft archive
$router->get('/admin/strains/(\d+)/delete', function (string $id) use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'strain' || $qr->isDeleted) {
        http_response_code(404);
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        return;
    }
    $strain = Strain::findByQrId($qr->id);
    require $rootDir . '/templates/admin/qrcodes/strains/delete.php';
});

// POST /admin/strains/{id}/delete — soft delete
$router->post('/admin/strains/(\d+)/delete', function (string $id) use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'strain' || $qr->isDeleted) {
        http_response_code(404);
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        return;
    }

    QrCode::softDelete($qr->id);
    Audit::log('qrcode_archived', $user->id, 'qrcode', $qr->id,
        ['type' => 'strain', 'title' => $qr->title, 'public_id' => $qr->publicId],
        Http::clientIp(), Http::userAgent());
    Session::set('flash', t('admin.strains.flash_archived', [
        'title' => htmlspecialchars($qr->title, ENT_QUOTES, 'UTF-8'),
    ]));
    header('Location: /admin/strains', true, 302);
});

// POST /admin/strains/{id}/restore — admin only
$router->post('/admin/strains/(\d+)/restore', function (string $id) use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'strain') {
        http_response_code(404);
        return;
    }
    QrCode::restore($qr->id);
    Audit::log('qrcode_restored', $admin->id, 'qrcode', $qr->id,
        ['type' => 'strain', 'title' => $qr->title],
        Http::clientIp(), Http::userAgent());
    Session::set('flash', t('admin.strains.flash_restored', [
        'title' => htmlspecialchars($qr->title, ENT_QUOTES, 'UTF-8'),
    ]));
    header('Location: /admin/strains', true, 302);
});

// GET /admin/strains/{id}/delete-hard — confirmação hard delete
$router->get('/admin/strains/(\d+)/delete-hard', function (string $id) use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'strain') {
        http_response_code(404);
        return;
    }
    if (!$qr->isDeleted) {
        Session::set('flash', 'Apenas dossiês arquivados podem ser excluídos permanentemente.');
        header('Location: /admin/strains/' . $qr->id . '/edit', true, 302);
        return;
    }
    $strain = Strain::findByQrId($qr->id);
    $scanCount = $qr->scanCount();
    require $rootDir . '/templates/admin/qrcodes/strains/delete-hard.php';
});

// POST /admin/strains/{id}/delete-hard — executa hard delete
$router->post('/admin/strains/(\d+)/delete-hard', function (string $id) use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'strain') {
        http_response_code(404);
        return;
    }
    if (!$qr->isDeleted) {
        http_response_code(400);
        return;
    }

    $confirmTitle = trim((string) ($_POST['confirm_title'] ?? ''));
    if ($confirmTitle !== $qr->title) {
        $errors = [t('admin.strains.hard_delete_mismatch')];
        $strain = Strain::findByQrId($qr->id);
        $scanCount = $qr->scanCount();
        require $rootDir . '/templates/admin/qrcodes/strains/delete-hard.php';
        return;
    }

    $originalTitle = $qr->title;
    $originalPublicId = $qr->publicId;
    $scanCount = $qr->scanCount();

    QrCode::hardDelete($qr->id);
    Audit::log('qrcode_hard_deleted', $admin->id, 'qrcode', $qr->id,
        ['type' => 'strain', 'title' => $originalTitle,
         'public_id' => $originalPublicId, 'scans_deleted' => $scanCount],
        Http::clientIp(), Http::userAgent());
    Session::set('flash', t('admin.strains.flash_hard_deleted'));
    header('Location: /admin/strains', true, 302);
});

// =====================================================================
// /admin/images  — CRUD de imagens
//
// Mesma regra de permissão das notas e strains:
//   - Listar: qualquer usuário logado (curator vê só as próprias)
//   - Criar: qualquer usuário logado (upload multipart)
//   - Editar/arquivar: autor OU admin
//   - Restaurar/hard-delete: admin only
// =====================================================================

// Helper de validação dos metadados do form (sem o arquivo — esse é
// validado em ImageUpload::process).
$validateImageInput = function (array $post): array {
    $errors = [];

    $title = trim((string) ($post['title'] ?? ''));
    if ($title === '') {
        $errors[] = 'Título é obrigatório.';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = 'Título excede 200 caracteres.';
    }

    $catRaw = $post['category_id'] ?? '';
    $categoryId = ($catRaw === '' || $catRaw === '0') ? null : (int) $catRaw;
    if ($categoryId !== null && Category::findById($categoryId) === null) {
        $errors[] = 'Categoria selecionada não existe.';
        $categoryId = null;
    }

    // Expiração
    $expRadio = (string) ($post['expires_radio'] ?? 'none');
    $expiresAt = null;
    if ($expRadio === '30')       $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+30 days'));
    elseif ($expRadio === '90')   $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+90 days'));
    elseif ($expRadio === '365')  $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+365 days'));
    elseif ($expRadio === 'custom') {
        $cd = trim((string) ($post['expires_custom'] ?? ''));
        if ($cd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cd)) {
            $expiresAt = $cd . ' 23:59:59';
        } else {
            $errors[] = 'Data de expiração customizada inválida.';
        }
    }

    return [$errors, $title, $categoryId, $expiresAt];
};

// GET /admin/images — listagem
$router->get('/admin/images', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $filters = [
        'status' => $_GET['status'] ?? 'all-active',
        'mine'   => !empty($_GET['mine']),
        'search' => trim((string) ($_GET['q'] ?? '')),
    ];

    $queryFilters = ['type' => 'image', 'status' => $filters['status']];
    if (!$user->isAdmin()) {
        $queryFilters['created_by'] = $user->id;
    } elseif ($filters['mine']) {
        $queryFilters['created_by'] = $user->id;
    }
    if ($filters['search'] !== '') {
        $queryFilters['search'] = $filters['search'];
    }

    $images = QrCode::listWithFilters($queryFilters);
    $totalCount = count($images);
    $categories = Category::listAll();
    $flashMessage = Session::get('flash');
    Session::unset('flash');
    require $rootDir . '/templates/admin/qrcodes/images/index.php';
});

// GET /admin/images/new — form de criação
$router->get('/admin/images/new', function () use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $categories = array_map(fn($n) => $n['category'], Category::listFlat());
    require $rootDir . '/templates/admin/qrcodes/images/new.php';
});

// POST /admin/images/new — processa upload + criação
$router->post('/admin/images/new', function () use ($rootDir, $verifyCsrf, $validateImageInput) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    [$errors, $title, $categoryId, $expiresAt] = $validateImageInput($_POST);

    // Processa o upload se a validação dos metadados passou
    $imageData = null;
    if ($errors === []) {
        try {
            $imageData = ImageUpload::process(
                file:        $_FILES['image_file'] ?? [],
                uploadsDir:  $rootDir . '/uploads',
            );
        } catch (\DomainException $e) {
            $errors[] = $e->getMessage();
        } catch (\RuntimeException $e) {
            $errors[] = 'Erro no servidor ao processar imagem: ' . $e->getMessage();
        }
    }

    if ($errors === [] && $imageData !== null) {
        try {
            $result = ImageQr::create(
                title:      $title,
                imageData:  $imageData,
                categoryId: $categoryId,
                createdBy:  $user->id,
                expiresAt:  $expiresAt,
            );
            Audit::log('qrcode_created', $user->id, 'qrcode', $result['id'],
                ['type' => 'image', 'public_id' => $result['public_id'],
                 'title' => $title, 'file_size' => $imageData['file_size'],
                 'mime_type' => $imageData['mime_type']],
                Http::clientIp(), Http::userAgent());
            Session::set('flash', t('admin.images.flash_created', [
                'title'      => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                'dossier_id' => htmlspecialchars($result['public_id'], ENT_QUOTES, 'UTF-8'),
            ]));
            header('Location: /admin/images', true, 302);
            return;
        } catch (\Throwable $e) {
            // Limpa o arquivo que foi salvo no disco se a transação falhou
            ImageUpload::deleteFiles(
                $rootDir . '/uploads',
                $imageData['file_path'],
                $imageData['thumbnail_path']
            );
            $errors[] = 'Erro ao registrar imagem: ' . $e->getMessage();
        }
    }

    // re-render form com erros
    $oldTitle      = $title;
    $oldCategoryId = $categoryId;
    $oldExpRadio   = (string) ($_POST['expires_radio'] ?? 'none');
    $oldCustomDate = (string) ($_POST['expires_custom'] ?? '');
    $categories    = array_map(fn($n) => $n['category'], Category::listFlat());
    require $rootDir . '/templates/admin/qrcodes/images/new.php';
});

// GET /admin/images/{id}/edit — form de edição (metadados apenas)
$router->get('/admin/images/(\d+)/edit', function (string $id) use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'image' || $qr->isDeleted) {
        http_response_code(404);
        $errorTitle    = t('errors.not_found.title');
        $errorSubtitle = 'IMAGE NOT FOUND';
        $errorCode     = '404';
        require $rootDir . '/templates/error.php';
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        $errorTitle    = 'Acesso negado';
        $errorSubtitle = 'PERMISSION DENIED';
        $errorCode     = '403';
        $errorBody     = t('admin.images.permission_denied');
        require $rootDir . '/templates/error.php';
        return;
    }
    $img = ImageQr::findByQrId($qr->id);
    $categories = array_map(fn($n) => $n['category'], Category::listFlat());
    require $rootDir . '/templates/admin/qrcodes/images/edit.php';
});

// POST /admin/images/{id}/edit — atualiza metadados (não o arquivo)
$router->post('/admin/images/(\d+)/edit', function (string $id) use ($rootDir, $verifyCsrf, $validateImageInput) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'image' || $qr->isDeleted) {
        http_response_code(404);
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        return;
    }

    [$errors, $title, $categoryId, $expiresAt] = $validateImageInput($_POST);

    if ($errors === []) {
        try {
            ImageQr::updateMetadata(
                qrId:       $qr->id,
                title:      $title,
                categoryId: $categoryId,
                expiresAt:  $expiresAt,
            );
            Audit::log('qrcode_updated', $user->id, 'qrcode', $qr->id,
                ['type' => 'image', 'title' => $title],
                Http::clientIp(), Http::userAgent());
            Session::set('flash', t('admin.images.flash_updated', [
                'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            ]));
            header('Location: /admin/images', true, 302);
            return;
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    $img = ImageQr::findByQrId($qr->id);
    $oldTitle      = $title;
    $oldCategoryId = $categoryId;
    $oldExpiresAt  = $expiresAt;
    $categories    = array_map(fn($n) => $n['category'], Category::listFlat());
    require $rootDir . '/templates/admin/qrcodes/images/edit.php';
});

// GET /admin/images/{id}/delete — confirmação soft archive
$router->get('/admin/images/(\d+)/delete', function (string $id) use ($rootDir) {
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);
    $currentUser = $user;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'image' || $qr->isDeleted) {
        http_response_code(404);
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        return;
    }
    $img = ImageQr::findByQrId($qr->id);
    require $rootDir . '/templates/admin/qrcodes/images/delete.php';
});

// POST /admin/images/{id}/delete — soft delete
$router->post('/admin/images/(\d+)/delete', function (string $id) use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $user = Auth::requireAuth();
    Auth::enforcePasswordChange($user);
    Auth::enforceTwoFactorSetup($user);

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'image' || $qr->isDeleted) {
        http_response_code(404);
        return;
    }
    if (!$qr->canBeEditedBy($user)) {
        http_response_code(403);
        return;
    }

    QrCode::softDelete($qr->id);
    Audit::log('qrcode_archived', $user->id, 'qrcode', $qr->id,
        ['type' => 'image', 'title' => $qr->title, 'public_id' => $qr->publicId],
        Http::clientIp(), Http::userAgent());
    Session::set('flash', t('admin.images.flash_archived', [
        'title' => htmlspecialchars($qr->title, ENT_QUOTES, 'UTF-8'),
    ]));
    header('Location: /admin/images', true, 302);
});

// POST /admin/images/{id}/restore — admin only
$router->post('/admin/images/(\d+)/restore', function (string $id) use ($verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'image') {
        http_response_code(404);
        return;
    }
    QrCode::restore($qr->id);
    Audit::log('qrcode_restored', $admin->id, 'qrcode', $qr->id,
        ['type' => 'image', 'title' => $qr->title],
        Http::clientIp(), Http::userAgent());
    Session::set('flash', t('admin.images.flash_restored', [
        'title' => htmlspecialchars($qr->title, ENT_QUOTES, 'UTF-8'),
    ]));
    header('Location: /admin/images', true, 302);
});

// GET /admin/images/{id}/delete-hard — confirmação hard delete (admin only)
$router->get('/admin/images/(\d+)/delete-hard', function (string $id) use ($rootDir) {
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'image') {
        http_response_code(404);
        return;
    }
    if (!$qr->isDeleted) {
        Session::set('flash', 'Apenas imagens arquivadas podem ser excluídas permanentemente.');
        header('Location: /admin/images/' . $qr->id . '/edit', true, 302);
        return;
    }
    $img = ImageQr::findByQrId($qr->id);
    $scanCount = $qr->scanCount();
    require $rootDir . '/templates/admin/qrcodes/images/delete-hard.php';
});

// POST /admin/images/{id}/delete-hard — executa hard delete + apaga arquivos
$router->post('/admin/images/(\d+)/delete-hard', function (string $id) use ($rootDir, $verifyCsrf) {
    if (!$verifyCsrf()) return;
    $admin = Auth::requireRole(User::ROLE_ADMIN);
    Auth::enforcePasswordChange($admin);
    Auth::enforceTwoFactorSetup($admin);
    $currentUser = $admin;

    $qr = QrCode::findById((int) $id);
    if ($qr === null || $qr->type !== 'image') {
        http_response_code(404);
        return;
    }
    if (!$qr->isDeleted) {
        http_response_code(400);
        return;
    }

    $confirmTitle = trim((string) ($_POST['confirm_title'] ?? ''));
    if ($confirmTitle !== $qr->title) {
        $errors = [t('admin.images.hard_delete_mismatch')];
        $img = ImageQr::findByQrId($qr->id);
        $scanCount = $qr->scanCount();
        require $rootDir . '/templates/admin/qrcodes/images/delete-hard.php';
        return;
    }

    $originalTitle = $qr->title;
    $originalPublicId = $qr->publicId;
    $scanCount = $qr->scanCount();
    $imgRecord = ImageQr::findByQrId($qr->id);
    $fileSize = $imgRecord?->fileSize ?? 0;

    // Apaga DB + arquivos físicos
    ImageQr::hardDeleteWithFiles($qr->id, $rootDir . '/uploads');

    Audit::log('qrcode_hard_deleted', $admin->id, 'qrcode', $qr->id,
        ['type' => 'image', 'title' => $originalTitle,
         'public_id' => $originalPublicId, 'scans_deleted' => $scanCount,
         'file_size' => $fileSize],
        Http::clientIp(), Http::userAgent());
    Session::set('flash', t('admin.images.flash_hard_deleted'));
    header('Location: /admin/images', true, 302);
});

// =====================================================================
// 404 + 500 handlers
// =====================================================================
$router->set404(function () use ($rootDir) {
    http_response_code(404);
    $errorTitle    = t('errors.not_found.title');
    $errorSubtitle = t('errors.not_found.subtitle');
    $errorCode     = '404';
    require $rootDir . '/templates/error.php';
});

// Global error handler — captura exceções não tratadas e renderiza 500
// temática. Em dev, mostra detalhes; em prod, mensagem genérica.
set_exception_handler(function (\Throwable $e) use ($rootDir) {
    error_log('[uncaught] ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $errorTitle    = t('errors.server.title');
    $errorSubtitle = t('errors.server.subtitle');
    $errorCode     = '500';
    $errorBody     = t('errors.server.body');
    require $rootDir . '/templates/error.php';
});

try {
    $router->run();
} catch (\Throwable $e) {
    // Fallback caso set_exception_handler não pegue (raro)
    error_log('[router] ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $errorTitle    = t('errors.server.title');
    $errorSubtitle = t('errors.server.subtitle');
    $errorCode     = '500';
    $errorBody     = t('errors.server.body');
    require $rootDir . '/templates/error.php';
}
