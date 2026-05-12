<?php
/**
 * Form de strain (compartilhado new/edit). 3 seções:
 *  1. Dados do registro (título, categoria, expiração do QR)
 *  2. Dados botânicos (nome, origem, genética, tipo de semente)
 *  3. Linha do tempo (datas plantio/floração/colheita)
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Category;
use ArkhamFiles\QrCode;
use ArkhamFiles\Strain;

$isEdit  = $isEdit ?? false;
/** @var QrCode|null $qr */
$qr = $qr ?? null;
/** @var Strain|null $strain */
$strain = $strain ?? null;
/** @var list<Category> $categories */
$categories = $categories ?? [];
$errors  = $errors ?? [];

// Repopular após erro ou pre-fill do registro existente
$oldTitle       = $oldTitle       ?? ($qr->title ?? '');
$oldCategoryId  = $oldCategoryId  ?? ($qr->categoryId ?? null);
$oldExpiresAt   = $oldExpiresAt   ?? ($qr->expiresAt ?? null);
$oldStrainName  = $oldStrainName  ?? ($strain->strainName ?? '');
$oldSource      = $oldSource      ?? ($strain->source ?? 'semente');
$oldGenetics    = $oldGenetics    ?? ($strain->genetics ?? 'hibrida');
$oldSeedType    = $oldSeedType    ?? ($strain->seedType ?? '');
$oldPlanting    = $oldPlanting    ?? ($strain->plantingDate ?? '');
$oldFlowering   = $oldFlowering   ?? ($strain->floweringDate ?? '');
$oldHarvest     = $oldHarvest     ?? ($strain->harvestDate ?? '');

// Determinar qual radio de expiração marcar
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

$pageTitleKey = $isEdit ? 'admin.strains.edit_page_title' : 'admin.strains.new_page_title';
$kickerKey    = $isEdit ? 'admin.strains.edit_kicker'     : 'admin.strains.new_kicker';
$formAction   = $isEdit ? '/admin/strains/' . $qr->id . '/edit' : '/admin/strains/new';

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t($pageTitleKey))) ?></div>
    <?php if ($isEdit): ?>
        <div class="af-fs-10 af-mute af-track-1">
            / Nº <span class="af-mono af-phosphor" style="text-transform:none"><?= e($qr->publicId) ?></span>
        </div>
    <?php endif; ?>
</div>

<div class="af-divider af-mb-4" style="max-width:280px;margin:0 0 18px 0">
    <span class="af-gold af-fs-9 af-track-3"><?= e(t($kickerKey)) ?></span>
</div>

<?php if ($errors !== []): ?>
    <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px;max-width:760px">
        <?php foreach ($errors as $err): ?>
            <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($formAction) ?>" class="af-flex-col af-gap-5" style="max-width:760px">
    <?= Session::csrfField() ?>

    <!-- Seção 1: Dados do QR -->
    <div>
        <div class="af-fs-9 af-track-3 af-gold af-mb-3"><?= e(t('admin.strains.section_qr')) ?></div>

        <div class="af-flex-col af-gap-3">
            <div>
                <label class="af-label" for="s-title"><?= e(t('admin.strains.form_title')) ?></label>
                <input id="s-title" name="title" type="text" class="af-input"
                       value="<?= e($oldTitle) ?>" required autofocus maxlength="200">
                <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.strains.form_title_help')) ?></div>
            </div>

            <div>
                <label class="af-label" for="s-cat"><?= e(t('admin.strains.form_category')) ?></label>
                <select id="s-cat" name="category_id" class="af-input">
                    <option value="">— <?= e(t('admin.strains.form_category_none')) ?> —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e((string) $cat->id) ?>"
                                <?= ((int) $oldCategoryId === $cat->id) ? 'selected' : '' ?>>
                            <?= str_repeat('— ', $cat->depth) ?><?= e($cat->name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="af-label"><?= e(t('admin.strains.form_expires')) ?></label>
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
                                   onclick="document.getElementById('s-custom-date').disabled = (this.value !== 'custom');">
                            <?= e(t('admin.strains.' . $key)) ?>
                        </label>
                    <?php endforeach; ?>
                    <input type="date" name="expires_custom" id="s-custom-date"
                           value="<?= e($customDate) ?>"
                           <?= $expRadio !== 'custom' ? 'disabled' : '' ?>
                           class="af-input af-fs-11" style="max-width:160px;padding:6px 10px">
                </div>
                <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.strains.form_expires_help')) ?></div>
            </div>
        </div>
    </div>

    <!-- Seção 2: Dados botânicos -->
    <div>
        <div class="af-fs-9 af-track-3 af-gold af-mb-3"><?= e(t('admin.strains.section_botanical')) ?></div>

        <div class="af-flex-col af-gap-3">
            <div>
                <label class="af-label" for="s-strain-name"><?= e(t('admin.strains.form_strain_name')) ?></label>
                <input id="s-strain-name" name="strain_name" type="text" class="af-input"
                       value="<?= e($oldStrainName) ?>" required maxlength="100">
                <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.strains.form_strain_name_help')) ?></div>
            </div>

            <div class="af-flex af-gap-3" style="flex-wrap:wrap">
                <div style="flex:1;min-width:200px">
                    <label class="af-label"><?= e(t('admin.strains.form_source')) ?></label>
                    <div class="af-flex af-gap-3 af-mt-2">
                        <?php foreach (Strain::SOURCES as $src): ?>
                            <label class="af-fs-11 af-soft" style="display:flex;align-items:center;gap:6px;cursor:pointer">
                                <input type="radio" name="source" value="<?= e($src) ?>"
                                       <?= $oldSource === $src ? 'checked' : '' ?>
                                       onclick="document.getElementById('s-seed-block').style.display = (this.value === 'semente' ? 'block' : 'none');">
                                <?= e(t('admin.strains.form_source_' . $src)) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.strains.form_source_help')) ?></div>
                </div>

                <div style="flex:1;min-width:200px">
                    <label class="af-label"><?= e(t('admin.strains.form_genetics')) ?></label>
                    <div class="af-flex af-gap-3 af-mt-2">
                        <?php foreach (Strain::GENETICS as $g): ?>
                            <label class="af-fs-11 af-soft" style="display:flex;align-items:center;gap:6px;cursor:pointer">
                                <input type="radio" name="genetics" value="<?= e($g) ?>"
                                       <?= $oldGenetics === $g ? 'checked' : '' ?>>
                                <?= e(t('admin.strains.form_genetics_' . $g)) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div id="s-seed-block" style="display:<?= $oldSource === 'semente' ? 'block' : 'none' ?>">
                <label class="af-label" for="s-seed-type"><?= e(t('admin.strains.form_seed_type')) ?></label>
                <select id="s-seed-type" name="seed_type" class="af-input" style="max-width:240px">
                    <option value=""><?= e(t('admin.strains.form_seed_type_none')) ?></option>
                    <?php foreach (Strain::SEED_TYPES as $st): ?>
                        <option value="<?= e($st) ?>" <?= $oldSeedType === $st ? 'selected' : '' ?>>
                            <?= e(t('admin.strains.form_seed_type_' . $st)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.strains.form_seed_type_help')) ?></div>
            </div>
        </div>
    </div>

    <!-- Seção 3: Timeline -->
    <div>
        <div class="af-fs-9 af-track-3 af-gold af-mb-3"><?= e(t('admin.strains.section_timeline')) ?></div>

        <div class="af-flex af-gap-3" style="flex-wrap:wrap">
            <div style="flex:1;min-width:180px">
                <label class="af-label" for="s-plant"><?= e(t('admin.strains.form_planting')) ?></label>
                <input id="s-plant" name="planting_date" type="date" class="af-input af-mono"
                       value="<?= e($oldPlanting) ?>">
            </div>
            <div style="flex:1;min-width:180px">
                <label class="af-label" for="s-flower"><?= e(t('admin.strains.form_flowering')) ?></label>
                <input id="s-flower" name="flowering_date" type="date" class="af-input af-mono"
                       value="<?= e($oldFlowering) ?>">
            </div>
            <div style="flex:1;min-width:180px">
                <label class="af-label" for="s-harvest"><?= e(t('admin.strains.form_harvest')) ?></label>
                <input id="s-harvest" name="harvest_date" type="date" class="af-input af-mono"
                       value="<?= e($oldHarvest) ?>">
            </div>
        </div>
        <div class="af-fs-10 af-mute af-mt-2"><?= e(t('admin.strains.form_dates_help')) ?></div>
    </div>

    <div class="af-flex af-gap-3 af-mt-3">
        <button type="submit" class="af-btn af-btn--primary af-btn--sm">
            <?= e(mb_strtoupper(t('common.save'))) ?> →
        </button>
        <a href="/admin/strains" class="af-btn af-btn--ghost af-btn--sm">
            <?= e(mb_strtoupper(t('common.cancel'))) ?>
        </a>
    </div>
</form>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t($pageTitleKey);
require dirname(dirname(dirname(dirname(__DIR__)))) . '/templates/layouts/admin.php';
