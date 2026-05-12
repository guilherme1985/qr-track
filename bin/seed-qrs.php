<?php
declare(strict_types=1);

/**
 * CLI · Cria 3 QRs de demonstração pra testar visualmente o fluxo público
 * sem precisar do CRUD de criação (que vem nos PRs 07/08/09).
 *
 * Os QRs criados:
 *   1. "Catálogo de exemplo"       → permanente (sem expires_at)
 *   2. "Lista de tarefas Q4"       → expira em 3 dias
 *   3. "Promoção verão passado"    → expirou há 30 dias
 *
 * Uso:
 *   php bin/seed-qrs.php           # cria todos
 *   php bin/seed-qrs.php --clear   # apaga os seeds e recria
 *
 * Sai com lista dos public_ids gerados — use eles pra testar:
 *   curl http://192.168.15.50/p/{public_id}
 *
 * Todos os QRs são do tipo 'url' (mais simples) e ficam associados ao
 * primeiro admin do sistema. Não usa categoria.
 */

require __DIR__ . '/../src/Bootstrap.php';

use ArkhamFiles\Bootstrap;
use ArkhamFiles\Database;
use ArkhamFiles\QrCode;
use ArkhamFiles\Auth\User;

$rootDir = dirname(__DIR__);
Bootstrap::init($rootDir);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Esse script é apenas pra linha de comando.\n");
    exit(1);
}

$opts = getopt('', ['clear']);
$shouldClear = isset($opts['clear']);

$pdo = Database::pdo();

if ($shouldClear) {
    // Apaga só os seeds (identificados por title que começa com [seed]).
    $stmt = $pdo->prepare("DELETE FROM qrcodes WHERE title LIKE '[seed]%'");
    $stmt->execute();
    fwrite(STDOUT, "✓ Apagados " . $stmt->rowCount() . " seed(s) anterior(es)\n\n");
}

// Pega o primeiro admin (created_by)
$admin = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND disabled_at IS NULL ORDER BY id LIMIT 1")
    ->fetchColumn();
if ($admin === false) {
    fwrite(STDERR, "✗ Nenhum admin encontrado. Crie um via bin/create-user.php primeiro.\n");
    exit(1);
}

$seeds = [
    [
        'title'      => '[seed] Catálogo permanente',
        'expires_at' => null,
        'payload'    => json_encode(['url' => 'https://example.com/catalog']),
    ],
    [
        'title'      => '[seed] Lista de tarefas Q4',
        'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+3 days')),
        'payload'    => json_encode(['url' => 'https://example.com/q4-tasks']),
    ],
    [
        'title'      => '[seed] Promoção verão (expirado)',
        'expires_at' => gmdate('Y-m-d H:i:s', strtotime('-30 days')),
        'payload'    => json_encode(['url' => 'https://example.com/summer-promo']),
    ],
];

$insert = $pdo->prepare('
    INSERT INTO qrcodes (public_id, type, title, payload, expires_at, created_by)
    VALUES (:p, :t, :ti, :pl, :exp, :cb)
');

fwrite(STDOUT, "Criando QRs de demonstração...\n\n");

foreach ($seeds as $s) {
    $publicId = QrCode::generatePublicId();
    $insert->execute([
        ':p'   => $publicId,
        ':t'   => 'url',
        ':ti'  => $s['title'],
        ':pl'  => $s['payload'],
        ':exp' => $s['expires_at'],
        ':cb'  => $admin,
    ]);

    $expLabel = $s['expires_at'] === null
        ? 'permanente'
        : ($s['expires_at'] < gmdate('Y-m-d H:i:s')
            ? 'expirado em ' . substr($s['expires_at'], 0, 10)
            : 'expira em ' . substr($s['expires_at'], 0, 10));

    fwrite(STDOUT, sprintf("  ✓ %-45s → /p/%s  [%s]\n",
        $s['title'], $publicId, $expLabel));
}

fwrite(STDOUT, "\nUse esses URLs pra testar:\n");
fwrite(STDOUT, "  http://192.168.15.50/p/{public_id}\n");
fwrite(STDOUT, "\nPara limpar depois:\n");
fwrite(STDOUT, "  php bin/seed-qrs.php --clear\n");
