<?php
/**
 * Listagem de categorias em árvore. Admin only.
 *
 * Vars esperadas:
 *   $flatList    → array<array{category, depth, has_children}>  (Category::listFlat())
 *   $currentUser → User (admin)
 *   $flashMessage→ opcional
 */
use ArkhamFiles\Category;

/** @var array<array{category: Category, depth: int, has_children: bool}> $flatList */
$flatList = $flatList ?? [];

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.categories.page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1"><?= e((string) count($flatList)) ?> registros</div>
</div>

<div class="af-divider af-mb-4" style="max-width:320px;margin:0 0 18px 0">
    <span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.categories.kicker')) ?></span>
</div>

<p class="af-fs-12 af-soft af-mb-4" style="max-width:720px"><?= e(t('admin.categories.help')) ?></p>

<div class="af-mb-4">
    <a href="/admin/categories/new" class="af-btn af-btn--primary af-btn--sm">
        <?= e(mb_strtoupper(t('admin.categories.btn_new'))) ?>
    </a>
</div>

<?php if ($flatList === []): ?>
    <div class="af-panel af-text-c af-mute" style="padding:48px">
        <div class="af-fs-12"><?= e(t('admin.categories.empty')) ?></div>
    </div>
<?php else: ?>
    <table class="af-table">
        <thead>
            <tr>
                <th><?= e(mb_strtoupper(t('admin.categories.col_name'))) ?></th>
                <th><?= e(mb_strtoupper(t('admin.categories.col_slug'))) ?></th>
                <th><?= e(mb_strtoupper(t('admin.categories.col_depth'))) ?></th>
                <th class="af-text-r"><?= e(mb_strtoupper(t('admin.categories.col_children'))) ?></th>
                <th class="af-text-r"><?= e(mb_strtoupper(t('admin.categories.col_archives'))) ?></th>
                <th class="af-text-r"><?= e(mb_strtoupper(t('admin.categories.col_actions'))) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($flatList as $node): ?>
                <?php
                    /** @var Category $cat */
                    $cat = $node['category'];
                    $depth = $node['depth'];
                    $childCount = Category::childCount($cat->id);
                    $qrCount = Category::qrCount($cat->id);
                    $indent = str_repeat('└─ ', max($depth, 0));
                    if ($depth > 0) {
                        $indent = str_repeat('   ', $depth - 1) . '└─ ';
                    }
                ?>
                <tr>
                    <td>
                        <span class="af-mono af-faint"><?= e($indent) ?></span>
                        <?php if ($cat->color): ?>
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= e($cat->color) ?>;vertical-align:middle;margin-right:6px"></span>
                        <?php endif; ?>
                        <?php if ($cat->icon): ?>
                            <span style="vertical-align:middle;margin-right:6px;color:<?= $cat->color ? e($cat->color) : 'var(--af-gold)' ?>"><?= icon($cat->icon, 'af-icon--sm') ?></span>
                        <?php endif; ?>
                        <span class="af-w-500"><?= e($cat->name) ?></span>
                    </td>
                    <td class="af-mono af-fs-11 af-soft"><?= e($cat->slug) ?></td>
                    <td>
                        <span class="af-fs-10 af-track-2 af-mute">
                            <?= $depth === 0
                                ? e(t('admin.categories.depth_root'))
                                : e(t('admin.categories.depth_level', ['n' => (string) $depth])) ?>
                        </span>
                    </td>
                    <td class="af-text-r af-mono"><?= e((string) $childCount) ?></td>
                    <td class="af-text-r af-mono"><?= e((string) $qrCount) ?></td>
                    <td class="af-text-r" style="white-space:nowrap">
                        <?php if ($cat->canHaveChildren()): ?>
                            <a href="/admin/categories/new?parent=<?= e((string) $cat->id) ?>"
                               class="af-fs-10 af-track-1 af-phosphor"
                               style="margin-right:14px">
                                + SUB
                            </a>
                        <?php endif; ?>
                        <a href="/admin/categories/<?= e((string) $cat->id) ?>/edit"
                           class="af-fs-10 af-track-1 af-mute"
                           style="margin-right:14px">
                            <?= e(mb_strtoupper(t('admin.categories.action_edit'))) ?>
                        </a>
                        <a href="/admin/categories/<?= e((string) $cat->id) ?>/delete"
                           class="af-fs-10 af-track-1 af-blood"
                           style="text-decoration:underline">
                            <?= e(mb_strtoupper(t('admin.categories.action_delete'))) ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.categories.page_title');
require dirname(dirname(dirname(__DIR__))) . '/templates/layouts/admin.php';
