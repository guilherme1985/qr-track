<?php
/**
 * Página dedicada à visualização e download do QR code.
 * Acessível em /admin/{type}/{id}/qr (type = note|strain|image).
 *
 * Variáveis esperadas:
 *   $qr            → QrCode
 *   $type          → string  (note | strain | image)
 *   $publicUrl     → string  (URL completa que o QR codifica)
 *   $iconAvailable → bool    (categoria tem ícone customizado)
 */
use ArkhamFiles\Category;
use ArkhamFiles\QrCode;

/** @var QrCode $qr */
$qr = $qr;
$publicUrl = $publicUrl;
$iconAvailable = $iconAvailable ?? false;
$type = $type;

// "Voltar pra listagem" depende do tipo
$listingUrl = "/admin/{$type}s";
$editUrl = "/admin/{$type}s/{$qr->id}/edit";

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title">
        CÓDIGO QR
    </div>
    <div class="af-fs-10 af-mute af-track-1">
        / Nº <span class="af-mono af-phosphor" style="text-transform:none"><?= e($qr->publicId) ?></span>
    </div>
</div>

<div class="af-divider af-mb-4" style="max-width:260px;margin:0 0 18px 0">
    <span class="af-gold af-fs-9 af-track-3">━━ EMISSÃO DE CÓDIGO ━━</span>
</div>

<div class="af-flex af-gap-5" style="flex-wrap:wrap;max-width:960px">
    <!-- Preview grande -->
    <div style="flex:1;min-width:360px;text-align:center">
        <div style="display:inline-block;padding:24px;background:white;border:0.5px solid var(--af-border)">
            <img src="/p/<?= e($qr->publicId) ?>.png?size=large<?= $iconAvailable ? '' : '&plain=1' ?>"
                 alt="QR <?= e($qr->publicId) ?>"
                 style="width:360px;height:auto;display:block">
        </div>
        <div class="af-fs-10 af-mono af-mute af-mt-3" style="text-transform:none;word-break:break-all">
            <?= e($publicUrl) ?>
        </div>
    </div>

    <!-- Sidebar com informações + downloads -->
    <div style="flex:0 0 320px">
        <div class="af-fs-9 af-track-3 af-gold af-mb-3">━━ DOCUMENTO ━━</div>
        <table class="af-table" style="border:none">
            <tbody>
                <tr style="border:none">
                    <td class="af-mute af-fs-10" style="padding-left:0;padding-top:4px;padding-bottom:4px">Título</td>
                    <td class="af-w-500 af-fs-12" style="padding-top:4px;padding-bottom:4px"><?= e($qr->title) ?></td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-10" style="padding-left:0;padding-top:4px;padding-bottom:4px">Tipo</td>
                    <td class="af-fs-11" style="padding-top:4px;padding-bottom:4px">
                        <span class="af-badge af-badge--gold"><?= e(mb_strtoupper($type)) ?></span>
                    </td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-10" style="padding-left:0;padding-top:4px;padding-bottom:4px">Dossiê Nº</td>
                    <td class="af-mono af-phosphor af-fs-11" style="text-transform:none;padding-top:4px;padding-bottom:4px">
                        <?= e($qr->publicId) ?>
                    </td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-10" style="padding-left:0;padding-top:4px;padding-bottom:4px">Logo no centro</td>
                    <td class="af-fs-10" style="padding-top:4px;padding-bottom:4px">
                        <?= $iconAvailable
                            ? '<span class="af-phosphor">✓ ícone da categoria</span>'
                            : '<span class="af-faint">— sem ícone —</span>' ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="af-fs-9 af-track-3 af-gold af-mt-5 af-mb-3">━━ DOWNLOAD ━━</div>

        <div class="af-flex-col af-gap-2">
            <a href="/p/<?= e($qr->publicId) ?>.svg" download="qr-<?= e($qr->publicId) ?>.svg"
               class="af-btn af-btn--ghost af-btn--sm" style="justify-content:flex-start">
                <?= icon('download', 'af-icon--sm af-phosphor') ?>
                SVG <span class="af-fs-9 af-faint">· vetorial · impressão</span>
            </a>

            <a href="/p/<?= e($qr->publicId) ?>.png?size=small" download="qr-<?= e($qr->publicId) ?>-300.png"
               class="af-btn af-btn--ghost af-btn--sm" style="justify-content:flex-start">
                <?= icon('download', 'af-icon--sm af-phosphor') ?>
                PNG pequeno <span class="af-fs-9 af-faint">· 300×300 · inline</span>
            </a>

            <a href="/p/<?= e($qr->publicId) ?>.png?size=medium" download="qr-<?= e($qr->publicId) ?>-600.png"
               class="af-btn af-btn--ghost af-btn--sm" style="justify-content:flex-start">
                <?= icon('download', 'af-icon--sm af-phosphor') ?>
                PNG médio <span class="af-fs-9 af-faint">· 600×600 · web</span>
            </a>

            <a href="/p/<?= e($qr->publicId) ?>.png?size=large" download="qr-<?= e($qr->publicId) ?>-1200.png"
               class="af-btn af-btn--ghost af-btn--sm" style="justify-content:flex-start">
                <?= icon('download', 'af-icon--sm af-phosphor') ?>
                PNG grande <span class="af-fs-9 af-faint">· 1200×1200 · panfleto</span>
            </a>
        </div>

        <div class="af-fs-10 af-mute af-mt-5" style="line-height:1.6">
            <em>O QR aponta pra URL pública. Mudanças na categoria depois
            da impressão não atualizam o QR físico — exclua e crie de novo
            se precisar mudar o ícone.</em>
        </div>

        <div class="af-flex af-gap-3 af-mt-5">
            <a href="<?= e($editUrl) ?>" class="af-btn af-btn--ghost af-btn--sm">
                ← Editar
            </a>
            <a href="<?= e($listingUrl) ?>" class="af-btn af-btn--ghost af-btn--sm">
                Listagem
            </a>
            <a href="javascript:window.print()" class="af-btn af-btn--ghost af-btn--sm">
                <?= icon('download', 'af-icon--sm') ?>
                Imprimir
            </a>
        </div>
    </div>
</div>

<style>
@media print {
    .af-admin-header, .af-admin-sidebar, .af-admin-content__title-row,
    .af-divider, table, .af-flex.af-gap-3 { display: none !important; }
    body { background: white !important; }
    .af-admin-content { padding: 0 !important; }
}
</style>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = 'QR Code · ' . $qr->title;
require dirname(dirname(dirname(__DIR__))) . '/templates/layouts/admin.php';
