<?php
/**
 * Dashboard administrativo — agora com dados reais.
 *
 * Vars esperadas (todas calculadas pelo handler em public/index.php):
 *   $stats           → array de contadores por status
 *   $countByType     → ['note' => 3, 'strain' => 1, 'image' => 2]
 *   $recentQrs       → list<QrCode>  (últimos 5 criados, não-deleted)
 *   $recentScans     → array de scans recentes  (joinados com qrcode)
 *   $recentAudit     → list<array>  (últimas 10 entradas relevantes)
 *   $maintenanceActive → bool (banner se manutenção tá ativa)
 *   $currentUser     → User
 */
use ArkhamFiles\QrCode;

$stats             = $stats             ?? [];
$countByType       = $countByType       ?? [];
$recentQrs         = $recentQrs         ?? [];
$recentScans       = $recentScans       ?? [];
$recentAudit       = $recentAudit       ?? [];
$maintenanceActive = $maintenanceActive ?? false;

$typeIcons = [
    'note'   => 'notes',
    'strain' => 'seedling',
    'image'  => 'photo',
];
$typePaths = [
    'note'   => 'notes',
    'strain' => 'strains',
    'image'  => 'images',
];

ob_start();
?>
<?php if ($maintenanceActive): ?>
    <!-- Banner de manutenção ATIVA (só admin vê isso) -->
    <div style="padding:14px 18px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <div class="af-fs-11 af-blood af-track-1">
            ⚙ <?= e(t('admin.dashboard.maintenance_banner')) ?>
        </div>
        <a href="/admin/settings/maintenance" class="af-btn af-btn--ghost af-btn--sm">
            <?= e(mb_strtoupper(t('admin.dashboard.maintenance_manage'))) ?>
        </a>
    </div>
<?php endif; ?>

<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.dashboard.page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1">
        <?= e(mb_strtoupper(gmdate('d.m.Y · H:i'))) ?> UTC
    </div>
</div>

<div class="af-divider af-mb-4" style="max-width:300px;margin:0 0 24px 0">
    <span class="af-gold af-fs-9 af-track-3">━━ <?= e(t('admin.dashboard.kicker')) ?> ━━</span>
</div>

<!-- STATS GRID -->
<div class="af-dash-stats">
    <?php foreach ($stats as $stat): ?>
        <div class="af-dash-stat-card">
            <div class="af-fs-10 af-track-3 af-mute af-mb-2"><?= e(mb_strtoupper($stat['label'])) ?></div>
            <div class="af-display af-w-500 <?= e($stat['tone'] ?? '') ?>" style="font-size:42px;line-height:1">
                <?= e((string) $stat['value']) ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- CONTAGEM POR TIPO -->
<div style="margin-top:32px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;max-width:760px">
    <?php foreach (['note', 'strain', 'image'] as $type):
        $count = $countByType[$type] ?? 0;
        $iconName = $typeIcons[$type];
        $path = $typePaths[$type];
    ?>
        <a href="/admin/<?= e($path) ?>" class="af-dash-type-card" style="text-decoration:none;color:inherit">
            <div class="af-flex" style="align-items:center;gap:12px">
                <?= icon($iconName, 'af-icon--md af-phosphor') ?>
                <div>
                    <div class="af-mono af-fs-10 af-track-2 af-mute"><?= e(mb_strtoupper(t('admin.dashboard.type_' . $type))) ?></div>
                    <div class="af-display af-w-500" style="font-size:24px;line-height:1.1">
                        <?= e((string) $count) ?>
                    </div>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<!-- DUAS COLUNAS: ÚLTIMOS QRs E ÚLTIMOS SCANS -->
<div class="af-dash-columns" style="margin-top:40px">
    <!-- Últimos criados -->
    <div>
        <div class="af-fs-9 af-track-3 af-gold af-mb-3">
            ━━ <?= e(t('admin.dashboard.recent_qrs_title')) ?> ━━
        </div>

        <?php if ($recentQrs === []): ?>
            <div class="af-panel af-mute af-fs-12" style="text-align:center;padding:24px">
                <?= e(t('admin.dashboard.empty_recent_qrs')) ?>
            </div>
        <?php else: ?>
            <div class="af-dash-list">
                <?php foreach ($recentQrs as $qr):
                    $iconName = $typeIcons[$qr->type] ?? 'qrcode';
                    $path = $typePaths[$qr->type] ?? 'notes';
                ?>
                    <a href="/admin/<?= e($path) ?>/<?= e((string) $qr->id) ?>/edit" class="af-dash-list-item">
                        <?= icon($iconName, 'af-icon--sm af-phosphor') ?>
                        <div style="flex:1;min-width:0">
                            <div class="af-fs-11 af-w-500" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= e($qr->title) ?>
                            </div>
                            <div class="af-mono af-fs-10 af-mute" style="text-transform:none">
                                <?= e($qr->publicId) ?> · <?= e(mb_strtoupper($qr->type)) ?>
                            </div>
                        </div>
                        <div class="af-fs-10 af-faint af-mono" style="white-space:nowrap">
                            <?= e(substr($qr->createdAt, 5, 11)) /* "MM-DD HH:MM" */ ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Últimos scans -->
    <div>
        <div class="af-fs-9 af-track-3 af-gold af-mb-3">
            ━━ <?= e(t('admin.dashboard.recent_scans_title')) ?> ━━
        </div>

        <?php if ($recentScans === []): ?>
            <div class="af-panel af-mute af-fs-12" style="text-align:center;padding:24px">
                <?= e(t('admin.dashboard.empty_recent_scans')) ?>
            </div>
        <?php else: ?>
            <div class="af-dash-list">
                <?php foreach ($recentScans as $scan):
                    $iconName = $typeIcons[$scan['type']] ?? 'qrcode';
                ?>
                    <div class="af-dash-list-item">
                        <?= icon($iconName, 'af-icon--sm af-soft') ?>
                        <div style="flex:1;min-width:0">
                            <div class="af-fs-11" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= e($scan['title'] ?? '—') ?>
                            </div>
                            <div class="af-mono af-fs-10 af-mute" style="text-transform:none">
                                <?= e($scan['public_id']) ?> · <?= e($scan['ip_address'] ?? '—') ?>
                            </div>
                        </div>
                        <div class="af-fs-10 af-faint af-mono" style="white-space:nowrap">
                            <?= e(substr($scan['scanned_at'], 5, 11)) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- AUDIT LOG RECENTE -->
<?php if ($recentAudit !== []): ?>
<div style="margin-top:40px;max-width:980px">
    <div class="af-fs-9 af-track-3 af-gold af-mb-3">
        ━━ <?= e(t('admin.dashboard.recent_audit_title')) ?> ━━
    </div>

    <div class="af-dash-list">
        <?php foreach ($recentAudit as $entry):
            $eventColor = str_contains($entry['event_type'], 'failed') || str_contains($entry['event_type'], 'denied') || str_contains($entry['event_type'], 'deleted')
                ? 'af-blood' : 'af-mute';
        ?>
            <div class="af-dash-list-item">
                <span class="af-mono af-fs-9 af-track-1 <?= $eventColor ?>" style="min-width:140px;text-transform:none">
                    <?= e($entry['event_type']) ?>
                </span>
                <div style="flex:1;min-width:0">
                    <div class="af-fs-11 af-soft">
                        <?= e($entry['username'] ?? '—') ?>
                    </div>
                    <div class="af-mono af-fs-10 af-faint">
                        <?= e($entry['ip_address'] ?? '—') ?>
                    </div>
                </div>
                <div class="af-fs-10 af-faint af-mono" style="white-space:nowrap">
                    <?= e(substr($entry['created_at'], 5, 11)) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
.af-dash-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    max-width: 760px;
}
.af-dash-stat-card {
    padding: 18px 20px;
    border: 0.5px solid var(--af-border);
    background: rgba(255, 255, 255, 0.02);
}
.af-dash-type-card {
    padding: 14px 18px;
    border: 0.5px solid var(--af-border);
    background: rgba(125, 219, 79, 0.02);
    transition: border-color 0.15s ease;
}
.af-dash-type-card:hover {
    border-color: var(--af-phosphor);
}
.af-dash-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
    max-width: 980px;
}
@media (max-width: 768px) {
    .af-dash-columns { grid-template-columns: 1fr; }
}
.af-dash-list {
    display: flex;
    flex-direction: column;
    border: 0.5px solid var(--af-border);
}
.af-dash-list-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-bottom: 0.5px solid var(--af-border);
    background: rgba(255, 255, 255, 0.01);
    transition: background 0.15s ease;
    text-decoration: none;
    color: inherit;
}
.af-dash-list-item:last-child { border-bottom: none; }
a.af-dash-list-item:hover {
    background: rgba(125, 219, 79, 0.04);
}
</style>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.dashboard.page_title');
require dirname(dirname(__DIR__)) . '/templates/layouts/admin.php';
