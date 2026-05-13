<?php
/**
 * Form de criação de imagem (com upload multipart).
 *
 * Variáveis esperadas:
 *   $categories     → list<Category>
 *   $errors         → list<string>
 *   $oldTitle, $oldCategoryId, $oldExpiresAt → repopular após erro
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Category;
use ArkhamFiles\ImageUpload;

/** @var list<Category> $categories */
$categories = $categories ?? [];
$errors = $errors ?? [];

$oldTitle      = $oldTitle      ?? '';
$oldCategoryId = $oldCategoryId ?? null;
$oldExpRadio   = $oldExpRadio   ?? 'none';
$oldCustomDate = $oldCustomDate ?? '';

$maxMb = number_format(ImageUpload::MAX_BYTES / 1024 / 1024, 0);

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.images.new_page_title'))) ?></div>
</div>

<div class="af-divider af-mb-4" style="max-width:260px;margin:0 0 18px 0">
    <span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.images.new_kicker')) ?></span>
</div>

<?php if ($errors !== []): ?>
    <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px;max-width:680px">
        <?php foreach ($errors as $err): ?>
            <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="/admin/images/new" enctype="multipart/form-data"
      class="af-flex-col af-gap-4" style="max-width:680px">
    <?= Session::csrfField() ?>

    <div>
        <label class="af-label" for="i-title"><?= e(t('admin.images.form_title')) ?></label>
        <input id="i-title" name="title" type="text" class="af-input"
               value="<?= e($oldTitle) ?>" required autofocus maxlength="200">
        <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.images.form_title_help')) ?></div>
    </div>

    <div>
        <label class="af-label" for="i-file"><?= e(t('admin.images.form_file')) ?></label>
        <input id="i-file" name="image_file" type="file"
               accept="image/jpeg,image/png,image/webp"
               required
               class="af-input"
               style="padding:10px">
        <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.images.form_file_help')) ?></div>
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
                'none'   => 'form_expires_none',
                '30'     => 'form_expires_30d',
                '90'     => 'form_expires_90d',
                '365'    => 'form_expires_1y',
                'custom' => 'form_expires_custom',
            ] as $val => $key): ?>
                <label class="af-fs-11 af-soft" style="display:flex;align-items:center;gap:6px;cursor:pointer">
                    <input type="radio" name="expires_radio" value="<?= e($val) ?>"
                           <?= $oldExpRadio === $val ? 'checked' : '' ?>
                           onclick="document.getElementById('i-custom-date').disabled = (this.value !== 'custom');">
                    <?= e(t('admin.images.' . $key)) ?>
                </label>
            <?php endforeach; ?>
            <input type="date" name="expires_custom" id="i-custom-date"
                   value="<?= e($oldCustomDate) ?>"
                   <?= $oldExpRadio !== 'custom' ? 'disabled' : '' ?>
                   class="af-input af-fs-11" style="max-width:160px;padding:6px 10px">
        </div>
        <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.images.form_expires_help')) ?></div>
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
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.images.new_page_title');
require dirname(dirname(dirname(dirname(__DIR__)))) . '/templates/layouts/admin.php';
