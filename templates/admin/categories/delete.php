<?php
/**
 * Confirmação de exclusão de categoria.
 *
 * Vars esperadas:
 *   $category    → Category
 *   $childCount  → int
 *   $qrCount     → int
 *   $errors      → list<string>
 *   $currentUser → User
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Category;

/** @var Category $category */
$category = $category;
$childCount = $childCount ?? 0;
$qrCount    = $qrCount    ?? 0;
$errors     = $errors     ?? [];
$blocked    = $childCount > 0 || $qrCount > 0;

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.categories.delete_page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1">/ <?= e($category->name) ?></div>
</div>

<div class="af-divider af-mb-4" style="max-width:280px;margin:0 0 18px 0">
    <span class="af-blood af-fs-9 af-track-3"><?= e(t('admin.categories.delete_kicker')) ?></span>
</div>

<?php if ($errors !== []): ?>
    <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px;max-width:560px">
        <?php foreach ($errors as $err): ?>
            <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="af-panel af-mb-4" style="max-width:560px;border-color:<?= $blocked ? 'var(--af-gold)' : 'var(--af-blood)' ?>">
    <div class="af-editorial af-w-500 af-mb-3" style="font-size:22px;line-height:1.2;color:<?= $blocked ? 'var(--af-gold)' : 'var(--af-blood)' ?>">
        <?php if ($blocked): ?>
            ⛔ Bloqueado
        <?php else: ?>
            ⚠ <?= e(t('admin.categories.delete_title')) ?>
        <?php endif; ?>
    </div>

    <?php if ($childCount > 0): ?>
        <p class="af-fs-12 af-soft" style="line-height:1.7">
            <?= e(t('admin.categories.delete_blocked_children', ['n' => (string) $childCount])) ?>
        </p>
    <?php elseif ($qrCount > 0): ?>
        <p class="af-fs-12 af-soft" style="line-height:1.7">
            <?= e(t('admin.categories.delete_blocked_qrs', ['n' => (string) $qrCount])) ?>
        </p>
    <?php else: ?>
        <p class="af-fs-12 af-soft" style="line-height:1.7">
            <?= t('admin.categories.delete_body', ['name' => '<strong>' . e($category->name) . '</strong>']) /* HTML */ ?>
        </p>
    <?php endif; ?>

    <table class="af-table" style="border:none;border-top:0.5px solid var(--af-border);margin-top:14px">
        <tbody>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0;width:160px"><?= e(t('admin.categories.form_name')) ?></td>
                <td class="af-w-500"><?= e($category->name) ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0"><?= e(t('admin.categories.form_slug')) ?></td>
                <td class="af-mono"><?= e($category->slug) ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0"><?= e(t('admin.categories.col_depth')) ?></td>
                <td class="af-mono">
                    <?= $category->depth === 0
                        ? e(t('admin.categories.depth_root'))
                        : e(t('admin.categories.depth_level', ['n' => (string) $category->depth])) ?>
                </td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0"><?= e(t('admin.categories.col_children')) ?></td>
                <td class="af-mono"><?= e((string) $childCount) ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0"><?= e(t('admin.categories.col_archives')) ?></td>
                <td class="af-mono"><?= e((string) $qrCount) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<?php if (!$blocked): ?>
    <form method="post" action="/admin/categories/<?= e((string) $category->id) ?>/delete" style="max-width:560px">
        <?= Session::csrfField() ?>
        <div class="af-flex af-gap-3">
            <button type="submit" class="af-btn af-btn--danger af-btn--sm">
                <?= icon('trash', 'af-icon--sm') ?>
                <?= e(mb_strtoupper(t('admin.categories.delete_btn'))) ?>
            </button>
            <a href="/admin/categories" class="af-btn af-btn--ghost af-btn--sm">
                <?= e(mb_strtoupper(t('common.cancel'))) ?>
            </a>
        </div>
    </form>
<?php else: ?>
    <a href="/admin/categories" class="af-btn af-btn--ghost af-btn--sm">
        ← <?= e(mb_strtoupper(t('common.back'))) ?>
    </a>
<?php endif; ?>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.categories.delete_page_title');
require dirname(dirname(dirname(__DIR__))) . '/templates/layouts/admin.php';
