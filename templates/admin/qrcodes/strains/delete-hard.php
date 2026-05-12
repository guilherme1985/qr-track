<?php
use ArkhamFiles\Auth\Session;
use ArkhamFiles\QrCode;
use ArkhamFiles\Strain;

/** @var QrCode $qr */
$qr = $qr;
$strain = $strain ?? Strain::findByQrId($qr->id);
$scanCount = $scanCount ?? $qr->scanCount();
$errors = $errors ?? [];

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.strains.hard_delete_page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1">
        / Nº <span class="af-mono af-phosphor" style="text-transform:none"><?= e($qr->publicId) ?></span>
    </div>
</div>

<div class="af-divider af-mb-4" style="max-width:260px;margin:0 0 18px 0">
    <span class="af-blood af-fs-9 af-track-3"><?= e(t('admin.strains.hard_delete_kicker')) ?></span>
</div>

<?php if ($errors !== []): ?>
    <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px;max-width:560px">
        <?php foreach ($errors as $err): ?>
            <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="af-panel af-mb-4" style="max-width:560px;border-color:var(--af-blood)">
    <div class="af-editorial af-w-500 af-mb-3 af-blood" style="font-size:22px;line-height:1.2">
        ⚠ <?= e(t('admin.strains.hard_delete_title')) ?>
    </div>
    <p class="af-fs-12 af-soft" style="line-height:1.7">
        <?= t('admin.strains.hard_delete_body', [
            'title' => '<strong>' . e($qr->title) . '</strong>',
            'scans' => (string) $scanCount,
        ]) ?>
    </p>

    <table class="af-table" style="border:none;border-top:0.5px solid var(--af-border);margin-top:14px">
        <tbody>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0;width:160px"><?= e(t('admin.strains.form_title')) ?></td>
                <td class="af-w-500"><?= e($qr->title) ?></td>
            </tr>
            <?php if ($strain): ?>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0"><?= e(t('admin.strains.form_strain_name')) ?></td>
                <td><?= e($strain->strainName) ?></td>
            </tr>
            <?php endif; ?>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0"><?= e(t('admin.strains.col_dossier_id')) ?></td>
                <td class="af-mono af-phosphor" style="text-transform:none"><?= e($qr->publicId) ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0"><?= e(t('admin.strains.col_scans')) ?></td>
                <td class="af-mono"><?= e((string) $scanCount) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<form method="post" action="/admin/strains/<?= e((string) $qr->id) ?>/delete-hard" style="max-width:560px">
    <?= Session::csrfField() ?>

    <div class="af-label af-mb-2" style="line-height:1.6">
        <?= t('admin.strains.hard_delete_confirm_label', [
            'title' => '<span class="af-mono af-phosphor" style="text-transform:none">' . e($qr->title) . '</span>',
        ]) ?>
    </div>

    <input type="text" name="confirm_title" required
           class="af-input af-mb-3"
           placeholder="<?= e(t('admin.strains.hard_delete_placeholder')) ?>"
           autocomplete="off">

    <div class="af-flex af-gap-3">
        <button type="submit" class="af-btn af-btn--danger af-btn--sm">
            <?= icon('trash', 'af-icon--sm') ?>
            <?= e(mb_strtoupper(t('admin.strains.hard_delete_btn'))) ?>
        </button>
        <a href="/admin/strains" class="af-btn af-btn--ghost af-btn--sm">
            <?= e(mb_strtoupper(t('common.cancel'))) ?>
        </a>
    </div>
</form>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.strains.hard_delete_page_title');
require dirname(dirname(dirname(dirname(__DIR__)))) . '/templates/layouts/admin.php';
