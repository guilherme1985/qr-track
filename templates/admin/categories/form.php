<?php
/**
 * Form de categoria (compartilhado new e edit).
 *
 * Vars esperadas:
 *   $isEdit       → bool — true para edit, false para new
 *   $category     → Category|null (apenas em edit)
 *   $parents      → list<Category> — todas as categorias com depth < MAX (candidatas a parent)
 *   $oldName, $oldSlug, $oldIcon, $oldColor, $oldParentId, $oldSortOrder → repreencher após erro
 *   $errors       → list<string>
 *   $currentUser  → User (admin)
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Category;

$isEdit  = $isEdit ?? false;
/** @var Category|null $category */
$category = $category ?? null;
/** @var list<Category> $parents */
$parents = $parents ?? [];
$errors  = $errors  ?? [];

$oldName       = $oldName       ?? ($category->name ?? '');
$oldSlug       = $oldSlug       ?? ($category->slug ?? '');
$oldIcon       = $oldIcon       ?? ($category->icon ?? '');
$oldColor      = $oldColor      ?? ($category->color ?? '');
$oldParentId   = $oldParentId   ?? ($category->parentId ?? null);
$oldSortOrder  = $oldSortOrder  ?? ($category->sortOrder ?? 0);

$pageTitleKey  = $isEdit ? 'admin.categories.edit_page_title'    : 'admin.categories.new_page_title';
$kickerKey     = $isEdit ? 'admin.categories.edit_kicker'        : 'admin.categories.new_kicker';
$formAction    = $isEdit
    ? '/admin/categories/' . $category->id . '/edit'
    : '/admin/categories/new';

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t($pageTitleKey))) ?></div>
    <?php if ($isEdit): ?>
        <div class="af-fs-10 af-mute af-track-1">/ <?= e($category->name) ?></div>
    <?php endif; ?>
</div>

<div class="af-divider af-mb-4" style="max-width:280px;margin:0 0 18px 0">
    <span class="af-gold af-fs-9 af-track-3"><?= e(t($kickerKey)) ?></span>
</div>

<?php if ($errors !== []): ?>
    <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px;max-width:560px">
        <?php foreach ($errors as $err): ?>
            <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($formAction) ?>" class="af-flex-col af-gap-4" style="max-width:560px">
    <?= Session::csrfField() ?>

    <div>
        <label class="af-label" for="c-name"><?= e(t('admin.categories.form_name')) ?></label>
        <input id="c-name" name="name" type="text" class="af-input"
               value="<?= e($oldName) ?>" required autofocus>
        <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.categories.form_name_help')) ?></div>
    </div>

    <div>
        <label class="af-label" for="c-slug"><?= e(t('admin.categories.form_slug')) ?></label>
        <input id="c-slug" name="slug" type="text" class="af-input af-mono"
               value="<?= e($oldSlug) ?>" placeholder="auto-gerado">
        <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.categories.form_slug_help')) ?></div>
    </div>

    <?php if (!$isEdit): ?>
        <div>
            <label class="af-label" for="c-parent"><?= e(t('admin.categories.form_parent')) ?></label>
            <select id="c-parent" name="parent_id" class="af-input">
                <option value=""<?= $oldParentId === null ? ' selected' : '' ?>>
                    — <?= e(t('admin.categories.form_parent_none')) ?> —
                </option>
                <?php foreach ($parents as $p): ?>
                    <option value="<?= e((string) $p->id) ?>"
                            <?= ((int) $oldParentId === $p->id) ? 'selected' : '' ?>>
                        <?= str_repeat('— ', $p->depth) ?><?= e($p->name) ?>
                        (<?= e($p->slug) ?> · nível <?= e((string) $p->depth) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.categories.form_parent_help')) ?></div>
        </div>
    <?php else: ?>
        <div>
            <div class="af-label"><?= e(t('admin.categories.form_parent')) ?></div>
            <div class="af-input af-mute" style="cursor:not-allowed">
                <?php if ($category->parentId === null): ?>
                    — <?= e(t('admin.categories.form_parent_none')) ?> —
                <?php else: ?>
                    <?php $parent = Category::findById($category->parentId); ?>
                    <?= $parent ? e($parent->name) : '(?)' ?>
                <?php endif; ?>
            </div>
            <div class="af-fs-10 af-mute af-mt-1">
                A categoria pai não pode ser alterada após criação. Para reorganizar, crie uma nova categoria e migre os arquivos.
            </div>
        </div>
    <?php endif; ?>

    <div class="af-flex af-gap-3">
        <div style="flex:1">
            <label class="af-label" for="c-icon"><?= e(t('admin.categories.form_icon')) ?></label>
            <input id="c-icon" name="icon" type="text" class="af-input af-mono"
                   value="<?= e($oldIcon) ?>" placeholder="folder">
            <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.categories.form_icon_help')) ?></div>
        </div>
        <div style="flex:1">
            <label class="af-label" for="c-color"><?= e(t('admin.categories.form_color')) ?></label>
            <input id="c-color" name="color" type="text" class="af-input af-mono"
                   value="<?= e($oldColor) ?>" placeholder="#7DDB4F">
            <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.categories.form_color_help')) ?></div>
        </div>
    </div>

    <div>
        <label class="af-label" for="c-sort"><?= e(t('admin.categories.form_sort_order')) ?></label>
        <input id="c-sort" name="sort_order" type="number" min="0" max="999"
               class="af-input af-mono" style="max-width:120px"
               value="<?= e((string) $oldSortOrder) ?>">
        <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.categories.form_sort_order_help')) ?></div>
    </div>

    <div class="af-flex af-gap-3 af-mt-3">
        <button type="submit" class="af-btn af-btn--primary af-btn--sm">
            <?= e(mb_strtoupper(t('common.save'))) ?> →
        </button>
        <a href="/admin/categories" class="af-btn af-btn--ghost af-btn--sm">
            <?= e(mb_strtoupper(t('common.cancel'))) ?>
        </a>
    </div>
</form>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t($pageTitleKey);
require dirname(dirname(dirname(__DIR__))) . '/templates/layouts/admin.php';
