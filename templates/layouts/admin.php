<?php
/**
 * Layout admin: header + sidebar (árvore de categorias) + content area.
 *
 * Variáveis esperadas (todas opcionais):
 *   $title          → vai pra <title>
 *   $bodyContent    → HTML principal já renderizado
 *   $categoryTree   → árvore mockada de categorias
 *   $activeCategory → slug da categoria ativa
 *   $currentUser    → ArkhamFiles\Auth\User (vem do middleware)
 *   $flashMessage   → string opcional pra exibir banner de sucesso
 *   $hideSidebar    → bool — ocultar a sidebar (útil em telas focadas)
 */

use ArkhamFiles\Auth\Auth;
use ArkhamFiles\Auth\User;
use ArkhamFiles\Icon;

$pageTitle      = $title          ?? 'Admin';
$categoryTree   = $categoryTree   ?? [];
$activeCategory = $activeCategory ?? '';

// Se não veio explícito, busca da sessão atual
$currentUser = $currentUser ?? Auth::currentUser();

$userInitials = $currentUser?->initials() ?? '··';
$userRole     = $currentUser?->roleLabel() ?? '—';
$isAdmin      = $currentUser?->isAdmin() ?? false;
$flashMessage = $flashMessage ?? null;
$hideSidebar  = $hideSidebar  ?? false;

// Recursividade pra renderizar a árvore
$renderTree = function (array $items, int $depth = 0) use (&$renderTree, $activeCategory): string {
    if ($items === []) {
        return '';
    }
    $cls = $depth === 0 ? 'af-tree' : 'af-tree__children';
    $html = "<ul class=\"{$cls}\">";
    foreach ($items as $item) {
        $isActive    = ($item['slug'] ?? '') === $activeCategory;
        $hasChildren = !empty($item['children']);
        $itemClass = 'af-tree__item';
        if ($depth > 0) {
            $itemClass .= ' af-tree__item--child';
        }
        if ($isActive) {
            $itemClass .= ' af-tree__item--active';
        }
        $caretIcon = $hasChildren
            ? ($item['expanded'] ?? false ? 'chevron-down' : 'chevron-right')
            : null;
        $folderIcon = $hasChildren && ($item['expanded'] ?? false)
            ? 'folder-open'
            : 'folder';

        $html .= '<li>';
        $html .= '<a href="#" class="' . $itemClass . '">';
        if ($caretIcon) {
            $html .= Icon::render($caretIcon, 'af-icon--sm af-tree__caret');
        } else {
            $html .= '<span class="af-tree__caret"></span>';
        }
        $html .= Icon::render($folderIcon, 'af-icon--sm ' . ($isActive ? '' : 'af-gold'));
        $html .= ' ' . e($item['name']);
        if (isset($item['count'])) {
            $html .= '<span class="af-tree__count">' . e((string) $item['count']) . '</span>';
        }
        $html .= '</a>';

        if ($hasChildren && ($item['expanded'] ?? false)) {
            $html .= $renderTree($item['children'], $depth + 1);
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
};

ob_start();
?>
<div class="af-admin-shell">
    <!-- Header -->
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
            <!-- Sidebar -->
            <nav class="af-admin-sidebar" aria-label="Navegação">
                <div class="af-sidebar__heading"><?= e(mb_strtoupper(t('admin.dashboard.archives_heading'))) ?></div>
                <div class="af-sidebar__divider-soft">━━━━━━━━━━━━</div>
                <?= $renderTree($categoryTree) ?>
                <?php if ($categoryTree !== []): ?>
                    <div class="af-sidebar__divider-soft" style="margin-top:14px">━━━━━━━━━━━━</div>
                <?php endif; ?>

                <!-- Bottom nav: profile, users (admin), settings, logout -->
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

        <!-- Content -->
        <main class="af-admin-content" role="main">
            <?php if ($flashMessage): ?>
                <div class="af-panel af-mb-4" style="border-color:var(--af-phosphor);background:var(--af-phosphor-glow)">
                    <div class="af-fs-12 af-phosphor af-track-1">
                        ✓ <?= $flashMessage /* CONTÉM HTML — caller é responsável por escapar partes user-supplied */ ?>
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
