<?php
/**
 * Layout admin: header + sidebar (árvore de categorias reais) + content area.
 *
 * Variáveis esperadas (todas opcionais):
 *   $title          → vai pra <title>
 *   $bodyContent    → HTML principal já renderizado
 *   $activeCategory → slug da categoria ativa (highlight na sidebar)
 *   $currentUser    → ArkhamFiles\Auth\User (vem do middleware)
 *   $flashMessage   → string opcional pra exibir banner de sucesso
 *   $hideSidebar    → bool — ocultar a sidebar (útil em telas focadas)
 */

use ArkhamFiles\Auth\Auth;
use ArkhamFiles\Auth\User;
use ArkhamFiles\Icon;
use ArkhamFiles\Category;

$pageTitle      = $title          ?? 'Admin';
$activeCategory = $activeCategory ?? '';

$currentUser = $currentUser ?? Auth::currentUser();

$userInitials = $currentUser?->initials() ?? '··';
$userRole     = $currentUser?->roleLabel() ?? '—';
$isAdmin      = $currentUser?->isAdmin() ?? false;
$flashMessage = $flashMessage ?? null;
$hideSidebar  = $hideSidebar  ?? false;

// Carrega árvore real de categorias para a sidebar
$categoryFlat = [];
if (!$hideSidebar) {
    try {
        $categoryFlat = Category::listFlat();
    } catch (\Throwable) {
        $categoryFlat = [];
    }
}

ob_start();
?>
<div class="af-admin-shell">
    <header class="af-admin-header" role="banner">
        <a href="/admin/dashboard" class="af-admin-header__brand">
            <?php require dirname(__DIR__) . '/components/logo-mini.php'; ?>
            <div class="af-admin-header__brand-text">
                <div class="af-display"><?= e(t('common.app_name')) ?></div>
                <div class="af-subtitle"><?= e(mb_strtoupper(t('common.app_subtitle'))) ?> · DASHBOARD</div>
            </div>
        </a>
        <div class="af-admin-header__search" role="search">
            <div class="af-admin-header__search-box">
                <?= icon('search', 'af-icon--sm af-mute') ?>
                <input type="text" placeholder="<?= e(t('common.search')) ?>" aria-label="Buscar">
            </div>
        </div>
        <div class="af-admin-header__actions">
            <?= icon('bell', 'af-icon--lg af-soft', ['aria-label' => 'Notificações']) ?>
            <a href="/admin/profile" class="af-admin-header__user" style="text-decoration:none;color:inherit">
                <div class="af-admin-header__avatar"><?= e($userInitials) ?></div>
                <div class="af-admin-header__user-info">
                    <div><?= e(mb_strtoupper($userRole)) ?></div>
                    <div>● <?= e(mb_strtoupper(t('admin.user.status_active'))) ?></div>
                </div>
            </a>
        </div>
    </header>

    <div class="af-admin-body">
        <?php if (!$hideSidebar): ?>
            <nav class="af-admin-sidebar" aria-label="Navegação">
                <div class="af-sidebar__heading"><?= e(mb_strtoupper(t('admin.dashboard.archives_heading'))) ?></div>
                <div class="af-sidebar__divider-soft">━━━━━━━━━━━━</div>

                <?php if ($categoryFlat === []): ?>
                    <div class="af-fs-10 af-faint" style="padding:8px 4px;line-height:1.6">
                        <?= e(t('admin.categories.empty')) ?>
                        <?php if ($isAdmin): ?>
                            <br><a href="/admin/categories/new" class="af-phosphor" style="text-decoration:underline">criar primeira</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <ul class="af-tree">
                        <?php foreach ($categoryFlat as $node): ?>
                            <?php
                                /** @var Category $cat */
                                $cat = $node['category'];
                                $depth = $node['depth'];
                                $hasChildren = $node['has_children'];
                                $isActive = $cat->slug === $activeCategory;
                                $itemClass = 'af-tree__item';
                                if ($depth > 0)  $itemClass .= ' af-tree__item--child';
                                if ($isActive)   $itemClass .= ' af-tree__item--active';
                                $folderIcon = $hasChildren ? 'folder' : 'file';
                            ?>
                            <li>
                                <a href="/admin/dashboard?category=<?= e($cat->slug) ?>" class="<?= e($itemClass) ?>"
                                   style="padding-left:<?= e((string) (2 + $depth * 14)) ?>px">
                                    <?= Icon::render($folderIcon, 'af-icon--sm ' . ($isActive ? '' : 'af-gold')) ?>
                                    <?= e($cat->name) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div style="margin-top:24px;display:flex;flex-direction:column;gap:2px">
                    <a href="/admin/profile" class="af-tree__item">
                        <span class="af-tree__caret"></span>
                        <?= icon('user', 'af-icon--sm af-soft') ?>
                        <?= e(t('admin.sidebar.profile')) ?>
                    </a>
                    <?php if ($isAdmin): ?>
                        <a href="/admin/users" class="af-tree__item">
                            <span class="af-tree__caret"></span>
                            <?= icon('user', 'af-icon--sm af-gold') ?>
                            <?= e(t('admin.sidebar.users')) ?>
                        </a>
                        <a href="/admin/categories" class="af-tree__item">
                            <span class="af-tree__caret"></span>
                            <?= icon('folder', 'af-icon--sm af-gold') ?>
                            <?= e(t('admin.sidebar.categories')) ?>
                        </a>
                    <?php endif; ?>
                    <a href="/admin/settings" class="af-tree__item">
                        <span class="af-tree__caret"></span>
                        <?= icon('settings', 'af-icon--sm af-soft') ?>
                        <?= e(t('admin.sidebar.settings')) ?>
                    </a>
                    <form method="post" action="/admin/logout" style="margin:0">
                        <?= \ArkhamFiles\Auth\Session::csrfField() ?>
                        <button type="submit" class="af-tree__item"
                                style="background:transparent;border:0;width:100%;text-align:left;padding:1px 0;cursor:pointer;font:inherit;color:var(--af-blood-bright)">
                            <span class="af-tree__caret"></span>
                            <?= icon('logout', 'af-icon--sm af-blood') ?>
                            <?= e(t('admin.sidebar.logout')) ?>
                        </button>
                    </form>
                </div>
            </nav>
        <?php endif; ?>

        <main class="af-admin-content" role="main">
            <?php if ($flashMessage): ?>
                <div class="af-panel af-mb-4" style="border-color:var(--af-phosphor);background:var(--af-phosphor-glow)">
                    <div class="af-fs-12 af-phosphor af-track-1">
                        ✓ <?= $flashMessage /* contém HTML — caller responsável por escapar parts user-supplied */ ?>
                    </div>
                </div>
            <?php endif; ?>
            <?= $bodyContent ?? '' ?>
        </main>
    </div>
</div>
<?php
$adminBody = ob_get_clean();

$bodyContent = $adminBody;
$extraCss = ['/assets/css/arkham-admin.css'];
require dirname(__DIR__) . '/layout.php';
