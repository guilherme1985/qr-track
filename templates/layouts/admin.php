<?php
/**
 * Layout admin: header + sidebar (árvore de categorias) + content area.
 *
 * @var string $title
 * @var string $bodyContent     Conteúdo principal já renderizado
 * @var array  $categoryTree    Árvore mockada de categorias para a sidebar
 * @var string $activeCategory  Slug da categoria ativa (para destacar)
 * @var string $userInitials    Iniciais do usuário no canto superior
 */
$pageTitle      = $title          ?? 'Admin';
$categoryTree   = $categoryTree   ?? [];
$activeCategory = $activeCategory ?? '';
$userInitials   = $userInitials   ?? 'CR';

// Recursividade pra renderizar a árvore
$renderTree = function (array $items, int $depth = 0) use (&$renderTree, $activeCategory): string {
    if ($items === []) {
        return '';
    }
    $cls = $depth === 0 ? 'af-tree' : 'af-tree__children';
    $html = "<ul class=\"{$cls}\">";
    foreach ($items as $item) {
        $isActive   = ($item['slug'] ?? '') === $activeCategory;
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
            $html .= \ArkhamFiles\Icon::render($caretIcon, 'af-icon--sm af-tree__caret');
        } else {
            $html .= '<span class="af-tree__caret"></span>';
        }
        $html .= \ArkhamFiles\Icon::render(
            $folderIcon,
            'af-icon--sm ' . ($isActive ? '' : 'af-gold')
        );
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
            <div class="af-admin-header__user">
                <div class="af-admin-header__avatar"><?= e($userInitials) ?></div>
                <div class="af-admin-header__user-info">
                    <div><?= e(mb_strtoupper(t('admin.user.role_curator'))) ?></div>
                    <div>● <?= e(mb_strtoupper(t('admin.user.status_active'))) ?></div>
                </div>
            </div>
        </div>
    </header>

    <div class="af-admin-body">
        <!-- Sidebar -->
        <nav class="af-admin-sidebar" aria-label="Navegação por categorias">
            <div class="af-sidebar__heading"><?= e(mb_strtoupper(t('admin.dashboard.archives_heading'))) ?></div>
            <div class="af-sidebar__divider-soft">━━━━━━━━━━━━</div>
            <?= $renderTree($categoryTree) ?>
            <div class="af-sidebar__divider-soft" style="margin-top:18px">━━━━━━━━━━━━</div>
            <a href="#" class="af-tree__item" style="font-size:11px;color:var(--af-text-mute);">
                <?= icon('folder-question', 'af-icon--sm') ?>
                <?= e(t('admin.dashboard.no_category')) ?>
                <span class="af-tree__count">2</span>
            </a>
        </nav>

        <!-- Content -->
        <main class="af-admin-content" role="main">
            <?= $bodyContent ?? '' ?>
        </main>
    </div>
</div>
<?php
$adminBody = ob_get_clean();

// Embrulha no layout HTML base
$bodyContent = $adminBody;
$extraCss = ['/assets/css/arkham-admin.css'];
require dirname(__DIR__) . '/layout.php';
