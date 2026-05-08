<?php
/**
 * Welcome page — também atua como smoke test pós-instalação.
 *
 * Mostra status do sistema (PHP, DB, migrations, dirs de storage).
 * Será removida quando o roteamento real entrar nos PRs seguintes.
 */

use ArkhamFiles\Config;
use ArkhamFiles\Database;
use ArkhamFiles\Migrations;

$rootDir = dirname(__DIR__);

// Coleta status do sistema
$checks = [];

// PHP version
$phpOk = version_compare(PHP_VERSION, '8.3.0', '>=');
$checks[] = [
    'label' => 'PHP ≥ 8.3',
    'value' => PHP_VERSION,
    'ok'    => $phpOk,
];

// Extensões obrigatórias
foreach (['pdo_sqlite', 'gd', 'mbstring', 'json'] as $ext) {
    $checks[] = [
        'label' => "Extensão {$ext}",
        'value' => extension_loaded($ext) ? 'carregada' : 'AUSENTE',
        'ok'    => extension_loaded($ext),
    ];
}

// Database
try {
    $pdo = Database::pdo();
    $checks[] = [
        'label' => 'Conexão SQLite',
        'value' => 'OK',
        'ok'    => true,
    ];

    // Migrations
    $migrations = new Migrations($pdo, $rootDir . '/migrations');
    $status = $migrations->status();
    $migOk = empty($status['pending']);
    $checks[] = [
        'label' => 'Migrations',
        'value' => count($status['applied']) . ' aplicadas, '
                 . count($status['pending']) . ' pendentes',
        'ok'    => $migOk,
    ];
} catch (\Throwable $e) {
    $checks[] = [
        'label' => 'Conexão SQLite',
        'value' => 'FALHA: ' . $e->getMessage(),
        'ok'    => false,
    ];
}

// Storage paths
foreach ([
    'STORAGE_PATH' => 'Storage',
    'UPLOAD_PATH'  => 'Uploads',
] as $envKey => $label) {
    $path = Config::get($envKey);
    $writable = $path && is_dir($path) && is_writable($path);
    $checks[] = [
        'label' => "{$label} gravável",
        'value' => $writable ? 'OK' : ($path ?? 'não configurado'),
        'ok'    => $writable,
    ];
}

$allOk = array_reduce($checks, fn($carry, $c) => $carry && $c['ok'], true);

// ---------------------------------------------------------------------
// Renderização
// ---------------------------------------------------------------------
ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top: 60px; padding-bottom: 60px;">
    <div style="text-align: center;">
        <?php $logoSize = 120; require __DIR__ . '/components/logo.php'; ?>
    </div>

    <div class="af-divider" style="margin-top: 28px;"><span>◆</span></div>

    <div style="text-align: center;">
        <div class="af-display af-phosphor" style="font-size: 22px; margin-bottom: 6px;">ARKHAM FILES</div>
        <div class="af-track-3 af-mute" style="font-size: 9px;">QR DIVISION · v1.0.0</div>
    </div>

    <div class="af-divider" style="margin-top: 28px; margin-bottom: 28px;"><span>◆</span></div>

    <div style="text-align: center; margin-bottom: 32px;">
        <div class="af-editorial" style="font-size: 30px; font-weight: 500; line-height: 1.2; margin-bottom: 10px;">
            <?= $allOk ? 'Contenção iniciada' : 'Verificação pendente' ?>
        </div>
        <div class="af-track-3 <?= $allOk ? 'af-phosphor' : 'af-blood' ?>" style="font-size: 11px;">
            <?= $allOk ? '✓ TODOS OS SISTEMAS OPERACIONAIS' : '⚠ FALHAS DETECTADAS NA INSPEÇÃO' ?>
        </div>
    </div>

    <div class="af-track-4 af-gold" style="font-size: 11px; text-align: center; margin-bottom: 18px;">
        ━━ INSPEÇÃO DOS SISTEMAS ━━
    </div>

    <div style="border: 0.5px solid var(--af-border);">
        <?php foreach ($checks as $i => $c): ?>
            <div style="display: grid; grid-template-columns: 24px 1fr auto; gap: 12px; padding: 11px 14px; align-items: center; <?= $i < count($checks) - 1 ? 'border-bottom: 0.5px solid var(--af-border);' : '' ?>">
                <span class="<?= $c['ok'] ? 'af-phosphor' : 'af-blood' ?>" style="font-size: 14px; text-align: center;">
                    <?= $c['ok'] ? '✓' : '✗' ?>
                </span>
                <span class="af-track-2" style="font-size: 11px;"><?= htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="af-mono af-mute" style="font-size: 11px;"><?= htmlspecialchars((string) $c['value'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$allOk): ?>
        <div style="margin-top: 22px; padding: 14px; border: 0.5px solid var(--af-blood); background: rgba(92,26,27,0.08);">
            <div class="af-track-3 af-blood" style="font-size: 10px; margin-bottom: 8px;">▲ AÇÃO REQUERIDA</div>
            <div style="font-size: 12px; line-height: 1.6; color: var(--af-text-soft);">
                Corrija os itens marcados com <span class="af-blood">✗</span> antes de prosseguir.
                Para migrations pendentes, rode <span class="af-mono af-phosphor">php bin/migrate.php</span>.
            </div>
        </div>
    <?php endif; ?>

    <div class="af-divider" style="margin-top: 36px;"><span>◆</span></div>

    <div style="text-align: center; font-size: 9px; letter-spacing: 2px; color: var(--af-text-mute);">
        <div>FORK DE TUXXIN/QR-TRACK · LICENÇA GPL-3.0</div>
        <div style="margin-top: 6px; opacity: 0.6;">CONTAINMENT BUILD · MMXXVI</div>
    </div>
</main>
<?php
$bodyContent = ob_get_clean();
$title = 'Status';
require __DIR__ . '/layout.php';
