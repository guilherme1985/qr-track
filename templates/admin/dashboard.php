<?php
/**
 * Dashboard administrativo — visual completo, mock data hardcoded.
 * O carregamento real virá nos PRs seguintes (categorias, expiração, tipos).
 */

use ArkhamFiles\Icon;

// Mock: árvore de categorias
$categoryTree = [
    [
        'name'     => 'Pessoal', 'slug' => 'pessoal', 'count' => 12,
        'expanded' => false,
        'children' => [
            ['name' => 'Família', 'slug' => 'familia', 'count' => 4],
            ['name' => 'Médico',  'slug' => 'medico',  'count' => 3],
        ],
    ],
    [
        'name'     => 'Cultivo', 'slug' => 'cultivo', 'count' => 28,
        'expanded' => true,
        'children' => [
            [
                'name' => 'Indica', 'slug' => 'indica', 'count' => 14,
                'expanded' => true,
                'children' => [
                    ['name' => 'Northern Lights', 'slug' => 'northern-lights', 'count' => 6],
                    ['name' => 'Bubba Kush',      'slug' => 'bubba-kush',      'count' => 4],
                ],
            ],
            ['name' => 'Sativa',  'slug' => 'sativa',  'count' => 9],
            ['name' => 'Híbrida', 'slug' => 'hibrida', 'count' => 5],
        ],
    ],
    [
        'name' => 'Trabalho', 'slug' => 'trabalho', 'count' => 7,
        'expanded' => false,
        'children' => [
            ['name' => 'Clientes',     'slug' => 'clientes',     'count' => 4],
            ['name' => 'Fornecedores', 'slug' => 'fornecedores', 'count' => 3],
        ],
    ],
    ['name' => 'Wifi', 'slug' => 'wifi', 'count' => 3],
];
$activeCategory = 'indica';
// $currentUser vem do middleware/index.php. $userInitials e role são calculados no layout.

// Mock: stats
$stats = [
    ['label' => t('admin.dashboard.stat_active'),    'value' => '247', 'tone' => 'phosphor'],
    ['label' => t('admin.dashboard.stat_expiring'),  'value' => '12',  'tone' => 'gold'],
    ['label' => t('admin.dashboard.stat_archived'),  'value' => '3',   'tone' => 'blood'],
    ['label' => t('admin.dashboard.stat_scans_24h'), 'value' => '1.4K','tone' => ''],
];

// Mock: linhas de QRs
$rows = [
    ['icon' => 'leaf',  'tone' => 'phosphor', 'title' => 'Northern Lights #3',        'meta' => 'A4F8-2D · feminizada · indica',     'scans' => '127', 'scans_tone' => 'phosphor', 'status' => 'active',   'expires' => '∞ permanente'],
    ['icon' => 'leaf',  'tone' => 'phosphor', 'title' => 'Bubba Kush #7',             'meta' => 'F5B6-9D · clone · indica',          'scans' => '54',  'scans_tone' => 'blood',    'status' => 'archived', 'expires' => '14.MAR.26'],
    ['icon' => 'notes', 'tone' => 'gold',     'title' => 'Receita do amplificador',   'meta' => 'B7C2-1F · markdown · pessoal',      'scans' => '43',  'scans_tone' => '',         'status' => 'expiring', 'expires' => '42 dias'],
    ['icon' => 'photo', 'tone' => 'gold',     'title' => 'Setup do bench',            'meta' => 'C2E9-8A · 847 KB · trabalho',       'scans' => '18',  'scans_tone' => '',         'status' => 'expiring', 'expires' => '3 dias'],
    ['icon' => 'link',  'tone' => 'phosphor', 'title' => 'Repositório do projeto',    'meta' => 'D1F4-5B · github.com · trabalho',   'scans' => '891', 'scans_tone' => 'phosphor', 'status' => 'active',   'expires' => '∞ permanente'],
    ['icon' => 'wifi',  'tone' => 'phosphor', 'title' => 'Wifi convidados',           'meta' => 'E3A1-7C · WPA2 · wifi',             'scans' => '6',   'scans_tone' => '',         'status' => 'active',   'expires' => '∞ permanente'],
];

$statusToTone = [
    'active'   => ['tone' => 'phosphor', 'symbol' => '●', 'label' => mb_strtoupper(t('common.status_active'))],
    'archived' => ['tone' => 'blood',    'symbol' => '⊘', 'label' => mb_strtoupper(t('common.status_archived'))],
    'expiring' => ['tone' => 'gold',     'symbol' => '⚠', 'label' => mb_strtoupper(t('common.status_expiring'))],
];

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title">CULTIVO / INDICA</div>
    <div class="af-fs-10 af-mute af-track-1">14 arquivos</div>
</div>

<!-- Stats -->
<div class="af-stats-grid">
    <?php foreach ($stats as $s): ?>
        <div class="af-stat <?= $s['tone'] ? 'af-stat--' . e($s['tone']) : '' ?>">
            <div class="af-stat__label"><?= e(mb_strtoupper($s['label'])) ?></div>
            <div class="af-stat__value"><?= e($s['value']) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Filtros / ações -->
<div class="af-filter-bar">
    <button class="af-btn af-btn--primary af-btn--sm">
        <?= e(mb_strtoupper(t('admin.dashboard.btn_new'))) ?>
    </button>
    <button class="af-btn af-btn--ghost af-btn--sm">
        <?= e(mb_strtoupper(t('admin.dashboard.filter_type'))) ?> ▾
    </button>
    <button class="af-btn af-btn--ghost af-btn--sm">
        <?= e(mb_strtoupper(t('admin.dashboard.filter_status'))) ?> ▾
    </button>
    <button class="af-btn af-btn--sm">
        <?= e(t('admin.dashboard.filter_subcats')) ?>
    </button>
</div>

<!-- Listagem -->
<div class="af-case-list">
    <div class="af-case-list__head">
        <div><?= e(mb_strtoupper(t('admin.dashboard.col_type'))) ?></div>
        <div><?= e(mb_strtoupper(t('admin.dashboard.col_dossier'))) ?></div>
        <div class="af-text-r"><?= e(mb_strtoupper(t('admin.dashboard.col_scans'))) ?></div>
        <div><?= e(mb_strtoupper(t('admin.dashboard.col_expires'))) ?></div>
    </div>
    <?php foreach ($rows as $r): ?>
        <?php
        $st = $statusToTone[$r['status']];
        ?>
        <a href="#" class="af-case-list__row">
            <?= Icon::render($r['icon'], 'af-icon--lg af-' . e($r['tone'])) ?>
            <div>
                <div class="af-case-list__title"><?= e($r['title']) ?></div>
                <div class="af-case-list__meta"><?= e($r['meta']) ?></div>
            </div>
            <div class="af-case-list__scans <?= $r['scans_tone'] ? 'af-' . e($r['scans_tone']) : '' ?>">
                <?= e($r['scans']) ?>
            </div>
            <div class="af-fs-9 af-track-1 af-<?= e($st['tone']) ?>">
                <?= e($st['symbol']) ?> <?= e($st['label']) ?>
                <div class="af-mute af-fs-9 af-mt-1"><?= e($r['expires']) ?></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<!-- Paginação -->
<div class="af-pagination">
    <div><?= e(mb_strtoupper(t('admin.dashboard.pagination_showing', ['from' => 1, 'to' => 6, 'total' => 14]))) ?></div>
    <div class="af-pagination__pages">
        <a class="af-pagination__page" href="#">‹</a>
        <a class="af-pagination__page af-pagination__page--active" href="#">1</a>
        <a class="af-pagination__page" href="#">2</a>
        <a class="af-pagination__page" href="#">3</a>
        <a class="af-pagination__page" href="#">›</a>
    </div>
</div>
<?php
$content = ob_get_clean();

// Renderiza dentro do shell admin
$bodyContent = $content;
$title = t('admin.dashboard.page_title');
require dirname(__DIR__) . '/layouts/admin.php';
