<?php
/**
 * Form de nota (compartilhado new/edit).
 *
 * Vars esperadas:
 *   $isEdit       → bool
 *   $qr           → QrCode|null (somente em edit)
 *   $categories   → list<Category> (pra dropdown)
 *   $oldTitle, $oldMarkdown, $oldCategoryId, $oldExpiresAt → repopular após erro
 *   $errors       → list<string>
 *   $currentUser  → User
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Category;
use ArkhamFiles\Markdown;
use ArkhamFiles\Note;
use ArkhamFiles\QrCode;

$isEdit  = $isEdit ?? false;
/** @var QrCode|null $qr */
$qr = $qr ?? null;
/** @var list<Category> $categories */
$categories = $categories ?? [];
$errors  = $errors ?? [];

$oldTitle      = $oldTitle      ?? ($qr->title ?? '');
$oldMarkdown   = $oldMarkdown   ?? ($qr ? Note::getMarkdown($qr->id) : '');
$oldCategoryId = $oldCategoryId ?? ($qr->categoryId ?? null);
$oldExpiresAt  = $oldExpiresAt  ?? ($qr->expiresAt ?? null);

// Calcular qual radio de expiração marcar
$expRadio = 'none';
if ($oldExpiresAt !== null) {
    $diff = strtotime($oldExpiresAt) - time();
    $days = (int) round($diff / 86400);
    if (abs($days - 30) <= 1) {
        $expRadio = '30';
    } elseif (abs($days - 90) <= 1) {
        $expRadio = '90';
    } elseif (abs($days - 365) <= 5) {
        $expRadio = '365';
    } else {
        $expRadio = 'custom';
    }
}
$customDate = ($expRadio === 'custom' && $oldExpiresAt) ? substr($oldExpiresAt, 0, 10) : '';

$pageTitleKey = $isEdit ? 'admin.notes.edit_page_title' : 'admin.notes.new_page_title';
$kickerKey    = $isEdit ? 'admin.notes.edit_kicker'     : 'admin.notes.new_kicker';
$formAction   = $isEdit ? '/admin/notes/' . $qr->id . '/edit' : '/admin/notes/new';

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

<form method="post" action="<?= e($formAction) ?>" class="af-flex-col af-gap-4" style="max-width:760px">
    <?= Session::csrfField() ?>

    <div>
        <label class="af-label" for="n-title"><?= e(t('admin.notes.form_title')) ?></label>
        <input id="n-title" name="title" type="text" class="af-input"
               value="<?= e($oldTitle) ?>" required autofocus maxlength="200">
        <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.notes.form_title_help')) ?></div>
    </div>

    <div class="af-flex af-gap-3">
        <div style="flex:1">
            <label class="af-label" for="n-category"><?= e(t('admin.notes.form_category')) ?></label>
            <select id="n-category" name="category_id" class="af-input">
                <option value="">— <?= e(t('admin.notes.form_category_none')) ?> —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e((string) $cat->id) ?>"
                            <?= ((int) $oldCategoryId === $cat->id) ? 'selected' : '' ?>>
                        <?= str_repeat('— ', $cat->depth) ?><?= e($cat->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.notes.form_category_help')) ?></div>
        </div>
    </div>

    <div>
        <label class="af-label"><?= e(t('admin.notes.form_expires')) ?></label>
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
                           onclick="document.getElementById('n-custom-date').disabled = (this.value !== 'custom');">
                    <?= e(t('admin.notes.' . $key)) ?>
                </label>
            <?php endforeach; ?>
            <input type="date" name="expires_custom" id="n-custom-date"
                   value="<?= e($customDate) ?>"
                   <?= $expRadio !== 'custom' ? 'disabled' : '' ?>
                   class="af-input af-fs-11" style="max-width:160px;padding:6px 10px">
        </div>
        <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.notes.form_expires_help')) ?></div>
    </div>

    <div>
        <label class="af-label" for="n-md"><?= e(t('admin.notes.form_markdown')) ?></label>
        <textarea id="n-md" name="markdown" class="af-input af-mono" required
                  rows="20" maxlength="<?= e((string) Markdown::MAX_LENGTH_BYTES) ?>"
                  style="font-size:13px;line-height:1.5;resize:vertical;min-height:400px"><?= e($oldMarkdown) ?></textarea>
        <div class="af-flex" style="justify-content:space-between;align-items:flex-start;margin-top:4px">
            <div class="af-fs-10 af-mute"><?= e(t('admin.notes.form_markdown_help')) ?></div>
            <div class="af-fs-10 af-mute af-mono" id="n-md-counter"></div>
        </div>
    </div>

    <div class="af-flex af-gap-3 af-mt-3">
        <button type="submit" class="af-btn af-btn--primary af-btn--sm">
            <?= e(mb_strtoupper(t('common.save'))) ?> →
        </button>
        <a href="/admin/notes" class="af-btn af-btn--ghost af-btn--sm">
            <?= e(mb_strtoupper(t('common.cancel'))) ?>
        </a>
    </div>
</form>

<script>
// Contador de caracteres
(function () {
    const ta = document.getElementById('n-md');
    const counter = document.getElementById('n-md-counter');
    const max = <?= (int) Markdown::MAX_LENGTH_BYTES ?>;
    function update() {
        const n = ta.value.length;
        counter.textContent = n.toLocaleString('pt-BR') + ' / ' + max.toLocaleString('pt-BR') + ' caracteres';
        counter.classList.toggle('af-blood', n > max);
        counter.classList.toggle('af-gold', n > max * 0.9 && n <= max);
    }
    ta.addEventListener('input', update);
    update();
})();
</script>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t($pageTitleKey);
require dirname(dirname(dirname(dirname(__DIR__)))) . '/templates/layouts/admin.php';
