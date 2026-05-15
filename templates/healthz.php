<?php
/**
 * Welcome page — também atua como smoke test pós-instalação.
 *
 * Mostra status do sistema (PHP, DB, migrations, dirs de storage).
 */

use ArkhamFiles\Config;
use ArkhamFiles\Database;
use ArkhamFiles\Migrations;

$rootDir = \ArkhamFiles\Bootstrap::rootDir();

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

// Database + Migrations
try {
    $pdo = Database::pdo();
    $checks[] = [
        'label' => 'Conexão SQLite',
        'value' => 'OK',
        'ok'    => true,
    ];

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

ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top:60px;padding-bottom:60px">
    <div class="af-text-c">
        <?php $logoSize = 120; require __DIR__ . '/components/logo.php'; ?>
    </div>

    <div class="af-divider af-mt-6"><span>◆</span></div>

    <div class="af-text-c">
        <div class="af-display af-phosphor af-fs-24 af-track-5"><?= e(mb_strtoupper(t('common.app_name'))) ?></div>
        <div class="af-track-3 af-mute af-fs-9 af-mt-2"><?= e(mb_strtoupper(t('common.app_subtitle'))) ?> · v1.0.0</div>
    </div>

    <div class="af-divider af-mt-6 af-mb-6"><span>◆</span></div>

    <div class="af-text-c af-mb-8">
        <div class="af-editorial af-fs-32 af-w-500" style="line-height:1.2;margin-bottom:10px">
            <?= $allOk ? 'Contenção iniciada' : 'Verificação pendente' ?>
        </div>
        <div class="af-track-3 af-fs-11 <?= $allOk ? 'af-phosphor' : 'af-blood' ?>">
            <?= $allOk ? '✓ TODOS OS SISTEMAS OPERACIONAIS' : '⚠ FALHAS DETECTADAS NA INSPEÇÃO' ?>
        </div>
    </div>

    <div class="af-track-4 af-gold af-fs-11 af-text-c af-mb-4">
        ━━ INSPEÇÃO DOS SISTEMAS ━━
    </div>

    <div style="border:0.5px solid var(--af-border)">
        <?php foreach ($checks as $i => $c): ?>
            <div style="display:grid;grid-template-columns:24px 1fr auto;gap:12px;padding:11px 14px;align-items:center;<?= $i < count($checks) - 1 ? 'border-bottom:0.5px solid var(--af-border);' : '' ?>">
                <span class="<?= $c['ok'] ? 'af-phosphor' : 'af-blood' ?>" style="font-size:14px;text-align:center">
                    <?= $c['ok'] ? '✓' : '✗' ?>
                </span>
                <span class="af-track-2 af-fs-11"><?= e($c['label']) ?></span>
                <span class="af-mono af-mute af-fs-11"><?= e((string) $c['value']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$allOk): ?>
        <div style="margin-top:22px;padding:14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08)">
            <div class="af-track-3 af-blood af-fs-10 af-mb-2">▲ AÇÃO REQUERIDA</div>
            <div class="af-fs-12 af-soft" style="line-height:1.6">
                Corrija os itens marcados com <span class="af-blood">✗</span> antes de prosseguir.
                Para migrations pendentes, rode <span class="af-mono af-phosphor">php bin/migrate.php</span>.
            </div>
        </div>
    <?php endif; ?>

    <div class="af-divider" style="margin-top:36px"><span>◆</span></div>

    <!-- Atalho dev: links pras telas que existem visualmente -->
    <div class="af-text-c af-fs-10 af-mute af-track-2 af-mb-4">
        ━━ TELAS DISPONÍVEIS (PR 02) ━━
    </div>
    <div class="af-flex af-justify-c af-gap-3" style="flex-wrap:wrap;margin-bottom:24px">
        <a href="/admin/login" class="af-btn af-btn--ghost af-btn--sm">LOGIN</a>
        <a href="/admin/dashboard" class="af-btn af-btn--ghost af-btn--sm">DASHBOARD</a>
        <a href="/admin/settings" class="af-btn af-btn--ghost af-btn--sm">CONFIGURAÇÕES</a>
    </div>

    <div class="af-text-c af-fs-9 af-track-2 af-mute">
        <div>FORK DE TUXXIN/QR-TRACK · LICENÇA GPL-3.0</div>
        <div class="af-mt-2" style="opacity:0.6">CONTAINMENT BUILD · MMXXVI</div>
    </div>
</main>
<?php
$bodyContent = ob_get_clean();
$title = 'Status';
require __DIR__ . '/layout.php';
