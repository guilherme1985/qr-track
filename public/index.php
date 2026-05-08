<?php
declare(strict_types=1);

/**
 * Arkham Files — front controller.
 *
 * All HTTP requests land here. Routes defined below.
 */

require __DIR__ . '/../src/Bootstrap.php';

use ArkhamFiles\Bootstrap;

$rootDir = dirname(__DIR__);
Bootstrap::init($rootDir);

$router = new Bramus\Router\Router();

// =====================================================================
// Welcome / health page (será substituída em PR posteriores)
// =====================================================================
$router->get('/', function () use ($rootDir) {
    require $rootDir . '/templates/welcome.php';
});

// =====================================================================
// Public routes (placeholders — implementados em PRs posteriores)
// =====================================================================
$router->get('/p/(.+)', function (string $publicId) use ($rootDir) {
    http_response_code(404);
    $errorTitle = 'Em breve';
    $errorSubtitle = 'Visualizador de QR ainda não implementado';
    $errorCode = 'WIP';
    require $rootDir . '/templates/error.php';
});

// =====================================================================
// Admin routes (placeholders — implementados em PRs posteriores)
// =====================================================================
$router->get('/admin/?', function () use ($rootDir) {
    http_response_code(503);
    $errorTitle = 'Acesso restrito';
    $errorSubtitle = 'Área administrativa em construção';
    $errorCode = 'WIP';
    require $rootDir . '/templates/error.php';
});

// =====================================================================
// 404 handler
// =====================================================================
$router->set404(function () use ($rootDir) {
    http_response_code(404);
    $errorTitle = 'Paciente não localizado';
    $errorSubtitle = 'Transferido para o Bloco H';
    $errorCode = '404';
    require $rootDir . '/templates/error.php';
});

$router->run();
