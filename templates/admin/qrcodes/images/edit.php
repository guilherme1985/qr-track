<?php
/**
 * Form de edição de imagem.
 * Não permite trocar o arquivo — mostra a imagem atual com seus metadados
 * e permite editar título, categoria, expiração.
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Category;
use ArkhamFiles\ImageQr;
use ArkhamFiles\QrCode;

/** @var QrCode $qr */
$qr = $qr;
/** @var ImageQr $img */
$img = $img;
/** @var list<Category> $categories */
$categories = $categories ?? [];
$errors = $errors ?? [];

$oldTitle      = $oldTitle      ?? $qr->title;
$oldCategoryId = $oldCategoryId ?? $qr->categoryId;
$oldExpiresAt  = $oldExpiresAt  ?? $qr->expiresAt;

// Calcular qual radio de expiração marcar
$expRadio = 'none';
if ($oldExpiresAt !== null) {
    $diff = strtotime($oldExpiresAt) - time();
    $days = (int) round($diff / 86400);
    if (abs($days - 30) <= 1)       $expRadio = '30';
    elseif (abs($days - 90) <= 1)   $expRadio = '90';
    elseif (abs($days - 365) <= 5)  $expRadio = '365';
    else                            $expRadio = 'custom';
}
$customDate = ($expRadio === 'custom' && $oldExpiresAt) ? substr($oldExpiresAt, 0, 10) : '';

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.images.edit_page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1">
        / Nº <span class="af-mono af-phosphor" style="text-transform:none"><?= e($qr->publicId) ?></span>
    </div>
</div>

<div class="af-divider af-mb-4" style="max-width:260px;margin:0 0 18px 0">
    <span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.images.edit_kicker')) ?></span>
</div>

<?php if ($errors !== []): ?>
    <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px;max-width:680px">
        <?php foreach ($errors as $err): ?>
            <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="af-flex af-gap-5" style="flex-wrap:wrap;max-width:880px">
    <!-- Sidebar com preview da imagem atual e metadados -->
    <div style="flex:0 0 280px">
        <div class="af-fs-9 af-track-3 af-gold af-mb-2"><?= e(mb_strtoupper(t('admin.images.current_image'))) ?></div>
        <div style="border:0.5px solid var(--af-border);background:#101010;aspect-ratio:1;display:flex;align-items:center;justify-content:center;overflow:hidden">
            <img src="<?= e($img->thumbnailUrl()) ?>" alt="<?= e($qr->title) ?>"
                 style="max-width:100%;max-height:100%;object-fit:contain">
        </div>

        <table class="af-table" style="border:none;margin-top:14px">
            <tbody>
                <tr style="border:none">
                    <td class="af-mute af-fs-10" style="padding-left:0;padding-top:4px;padding-bottom:4px"><?= e(t('admin.images.mime_label')) ?></td>
                    <td class="af-mono af-fs-11" style="padding-top:4px;padding-bottom:4px"><?= e($img->formatLabel()) ?></td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-10" style="padding-left:0;padding-top:4px;padding-bottom:4px"><?= e(t('admin.images.size_label')) ?></td>
                    <td class="af-mono af-fs-11" style="padding-top:4px;padding-bottom:4px"><?= e($img->fileSizeLabel()) ?></td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-10" style="padding-left:0;padding-top:4px;padding-bottom:4px"><?= e(t('admin.images.dim_label')) ?></td>
                    <td class="af-mono af-fs-11" style="padding-top:4px;padding-bottom:4px"><?= e($img->dimensionsLabel()) ?></td>
                </tr>
                <?php if ($img->aspectRatio()): ?>
                    <tr style="border:none">
                        <td class="af-mute af-fs-10" style="padding-left:0;padding-top:4px;padding-bottom:4px"><?= e(t('admin.images.aspect_label')) ?></td>
                        <td class="af-mono af-fs-11" style="padding-top:4px;padding-bottom:4px"><?= e($img->aspectRatio()) ?></td>
                    </tr>
                <?php endif; ?>
                <tr style="border:none">
                    <td class="af-mute af-fs-10" style="padding-left:0;padding-top:4px;padding-bottom:4px"><?= e(t('admin.images.uploaded_at')) ?></td>
                    <td class="af-mono af-fs-11" style="padding-top:4px;padding-bottom:4px"><?= e($img->uploadedAt) ?></td>
                </tr>
                <?php if ($img->originalFilename): ?>
                    <tr style="border:none">
                        <td class="af-mute af-fs-10" style="padding-left:0;padding-top:4px;padding-bottom:4px"><?= e(t('admin.images.original_filename')) ?></td>
                        <td class="af-mono af-fs-10" style="padding-top:4px;padding-bottom:4px;word-break:break-all"><?= e($img->originalFilename) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="af-fs-10 af-faint af-mt-3" style="line-height:1.5">
            <em><?= e(t('admin.images.edit_note')) ?></em>
        </div>
    </div>

    <!-- Form de edição -->
    <form method="post" action="/admin/images/<?= e((string) $qr->id) ?>/edit"
          class="af-flex-col af-gap-4" style="flex:1;min-width:320px">
        <?= Session::csrfField() ?>

        <div>
            <label class="af-label" for="i-title"><?= e(t('admin.images.form_title')) ?></label>
            <input id="i-title" name="title" type="text" class="af-input"
                   value="<?= e($oldTitle) ?>" required maxlength="200">
        </div>

        <div>
            <label class="af-label" for="i-cat"><?= e(t('admin.images.form_category')) ?></label>
            <select id="i-cat" name="category_id" class="af-input">
                <option value="">— <?= e(t('admin.images.form_category_none')) ?> —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e((string) $cat->id) ?>" <?= ((int) $oldCategoryId === $cat->id) ? 'selected' : '' ?>>
                        <?= str_repeat('— ', $cat->depth) ?><?= e($cat->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="af-label"><?= e(t('admin.images.form_expires')) ?></label>
            <div class="af-flex af-gap-4 af-mt-2" style="flex-wrap:wrap">
                <?php foreach ([
                    'none' => 'form_expires_none',
                    '30'   => 'form_expires_30d',
                    '90'   => 'form_expires_90d',
                    '365'  => 'form_expires_1y',
                    'custom' => 'form_expires_custom',
                ] as $val => $key): ?>
                    <label class="af-fs-11 af-soft" style="display:flex;align-items:center;gap:6px;cursor:pointer">
                        <input type="radio" name="expires_radio" value="<?= e($val) ?>"
                               <?= $expRadio === $val ? 'checked' : '' ?>
                               onclick="document.getElementById('i-custom-date').disabled = (this.value !== 'custom');">
                        <?= e(t('admin.images.' . $key)) ?>
                    </label>
                <?php endforeach; ?>
                <input type="date" name="expires_custom" id="i-custom-date"
                       value="<?= e($customDate) ?>"
                       <?= $expRadio !== 'custom' ? 'disabled' : '' ?>
                       class="af-input af-fs-11" style="max-width:160px;padding:6px 10px">
            </div>
        </div>

        <div class="af-flex af-gap-3 af-mt-3">
            <button type="submit" class="af-btn af-btn--primary af-btn--sm">
                <?= e(mb_strtoupper(t('common.save'))) ?> →
            </button>
            <a href="/admin/images" class="af-btn af-btn--ghost af-btn--sm">
                <?= e(mb_strtoupper(t('common.cancel'))) ?>
            </a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.images.edit_page_title');
require dirname(dirname(dirname(dirname(__DIR__)))) . '/templates/layouts/admin.php';
