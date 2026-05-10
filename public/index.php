<?php
declare(strict_types=1);

/**
 * Arkham Files — front controller.
 */

require __DIR__ . '/../src/Bootstrap.php';

use ArkhamFiles\Bootstrap;

$rootDir = dirname(__DIR__);
Bootstrap::init($rootDir);

$router = new Bramus\Router\Router();

// =====================================================================
// Welcome / status / smoke test
// =====================================================================
$router->get('/', function () use ($rootDir) {
    require $rootDir . '/templates/welcome.php';
});

// =====================================================================
// Admin (placeholders visuais — funcionalidade vem nos PRs seguintes)
// =====================================================================
$router->get('/admin/?', function () use ($rootDir) {
    header('Location: /admin/dashboard', true, 302);
    exit;
});
$router->get('/admin/login', function () use ($rootDir) {
    require $rootDir . '/templates/admin/login.php';
});
$router->get('/admin/dashboard', function () use ($rootDir) {
    require $rootDir . '/templates/admin/dashboard.php';
});
$router->get('/admin/settings', function () use ($rootDir) {
    require $rootDir . '/templates/admin/settings.php';
});

// =====================================================================
// Public scan placeholder (implementado em PRs posteriores)
// =====================================================================
$router->get('/p/(.+)', function (string $publicId) use ($rootDir) {
    http_response_code(404);
    $errorTitle    = t('errors.wip.title');
    $errorSubtitle = t('errors.wip.subtitle');
    $errorCode     = 'WIP';
    require $rootDir . '/templates/error.php';
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
