<?php
/**
 * Confirmação de arquivamento (soft-delete) de uma nota.
 * Disponível pra autor da nota ou admin.
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\QrCode;

/** @var QrCode $qr */
$qr = $qr;
$scanCount = $scanCount ?? $qr->scanCount();

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.notes.delete_page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1">
        / Nº <span class="af-mono af-phosphor" style="text-transform:none"><?= e($qr->publicId) ?></span>
    </div>
</div>

<div class="af-divider af-mb-4" style="max-width:260px;margin:0 0 18px 0">
    <span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.notes.delete_kicker')) ?></span>
</div>

<div class="af-panel af-mb-4" style="max-width:560px;border-color:var(--af-gold)">
    <div class="af-editorial af-w-500 af-mb-3 af-gold" style="font-size:22px;line-height:1.2">
        ⚠ <?= e(t('admin.notes.delete_title')) ?>
    </div>
    <p class="af-fs-12 af-soft" style="line-height:1.7">
        <?= t('admin.notes.delete_body', ['title' => '<strong>' . e($qr->title) . '</strong>']) /* HTML */ ?>
    </p>

    <table class="af-table" style="border:none;border-top:0.5px solid var(--af-border);margin-top:14px">
        <tbody>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0;width:160px"><?= e(t('admin.notes.form_title')) ?></td>
                <td class="af-w-500"><?= e($qr->title) ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0"><?= e(t('admin.notes.col_dossier_id')) ?></td>
                <td class="af-mono af-phosphor" style="text-transform:none"><?= e($qr->publicId) ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0"><?= e(t('admin.notes.col_scans')) ?></td>
                <td class="af-mono"><?= e((string) $scanCount) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<form method="post" action="/admin/notes/<?= e((string) $qr->id) ?>/delete" style="max-width:560px">
    <?= Session::csrfField() ?>
    <div class="af-flex af-gap-3">
        <button type="submit" class="af-btn af-btn--sm" style="background:var(--af-gold);color:var(--af-bg);border-color:var(--af-gold)">
            <?= e(mb_strtoupper(t('admin.notes.delete_btn'))) ?>
        </button>
        <a href="/admin/notes" class="af-btn af-btn--ghost af-btn--sm">
            <?= e(mb_strtoupper(t('common.cancel'))) ?>
        </a>
    </div>
</form>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.notes.delete_page_title');
require dirname(dirname(dirname(dirname(__DIR__)))) . '/templates/layouts/admin.php';
