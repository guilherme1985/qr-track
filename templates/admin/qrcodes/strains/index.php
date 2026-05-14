<?php
/**
 * Listagem de strains (QRs do tipo 'strain') com filtros.
 *
 * Vars esperadas:
 *   $strains        → list<QrCode>
 *   $totalCount     → int
 *   $filters        → array (genetics, phase, mine, search, status)
 *   $categories     → list<Category>
 *   $currentUser    → User
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Category;
use ArkhamFiles\QrCode;
use ArkhamFiles\Strain;

/** @var list<QrCode> $strains */
$strains = $strains ?? [];
$totalCount = $totalCount ?? count($strains);
$filters = $filters ?? [];
/** @var list<Category> $categories */
$categories = $categories ?? [];

$isAdmin = $currentUser?->isAdmin() ?? false;

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.strains.page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1"><?= e((string) $totalCount) ?> registros</div>
</div>

<div class="af-divider af-mb-4" style="max-width:300px;margin:0 0 18px 0">
    <span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.strains.kicker')) ?></span>
</div>

<p class="af-fs-12 af-soft af-mb-4" style="max-width:720px"><?= e(t('admin.strains.help')) ?></p>

<div class="af-flex af-gap-3 af-mb-4" style="align-items:center;flex-wrap:wrap">
    <a href="/admin/strains/new" class="af-btn af-btn--primary af-btn--sm">
        <?= e(mb_strtoupper(t('admin.strains.btn_new'))) ?>
    </a>

    <form method="get" action="/admin/strains" class="af-flex af-gap-2" style="margin:0;flex-wrap:wrap;align-items:center">
        <select name="genetics" class="af-input af-fs-11" style="max-width:160px;padding:6px 10px">
            <option value=""><?= e(t('admin.strains.filter_genetics_all')) ?></option>
            <?php foreach (Strain::GENETICS as $g): ?>
                <option value="<?= e($g) ?>" <?= (($filters['genetics'] ?? '') === $g) ? 'selected' : '' ?>>
                    <?= e(t('admin.strains.form_genetics_' . $g)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="phase" class="af-input af-fs-11" style="max-width:160px;padding:6px 10px">
            <option value=""><?= e(t('admin.strains.filter_phase_all')) ?></option>
            <?php foreach (['planted', 'flowering', 'harvested', 'unknown'] as $p): ?>
                <option value="<?= e($p) ?>" <?= (($filters['phase'] ?? '') === $p) ? 'selected' : '' ?>>
                    <?= e(t('admin.strains.phase_' . $p)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($isAdmin): ?>
            <label class="af-fs-11 af-soft" style="display:flex;align-items:center;gap:6px;cursor:pointer">
                <input type="checkbox" name="mine" value="1" <?= !empty($filters['mine']) ? 'checked' : '' ?>>
                <?= e(t('admin.strains.filter_mine')) ?>
            </label>
        <?php endif; ?>

        <input type="text" name="q" value="<?= e((string)($filters['search'] ?? '')) ?>"
               class="af-input af-fs-11" style="max-width:200px;padding:6px 10px"
               placeholder="<?= e(t('admin.strains.filter_search')) ?>">

        <button type="submit" class="af-btn af-btn--ghost af-btn--sm">
            <?= e(mb_strtoupper(t('admin.strains.filter_apply'))) ?>
        </button>
        <?php if (!empty($filters['genetics']) || !empty($filters['phase']) || !empty($filters['mine']) || !empty($filters['search'])): ?>
            <a href="/admin/strains" class="af-fs-10 af-track-1 af-mute" style="text-decoration:underline">
                <?= e(t('admin.strains.filter_clear')) ?>
            </a>
        <?php endif; ?>
    </form>
</div>

<?php if ($strains === []): ?>
    <div class="af-panel af-text-c af-mute" style="padding:48px">
        <div class="af-fs-12">
            <?= e(t(($filters !== []) ? 'admin.strains.empty_filtered' : 'admin.strains.empty')) ?>
        </div>
    </div>
<?php else: ?>
    <table class="af-table">
        <thead>
            <tr>
                <th><?= e(mb_strtoupper(t('admin.strains.col_strain'))) ?></th>
                <th><?= e(mb_strtoupper(t('admin.strains.col_dossier_id'))) ?></th>
                <th><?= e(mb_strtoupper(t('admin.strains.col_genetics'))) ?></th>
                <th><?= e(mb_strtoupper(t('admin.strains.col_phase'))) ?></th>
                <th class="af-text-r"><?= e(mb_strtoupper(t('admin.strains.col_cycle'))) ?></th>
                <th class="af-text-r"><?= e(mb_strtoupper(t('admin.strains.col_scans'))) ?></th>
                <th class="af-text-r"><?= e(mb_strtoupper(t('admin.strains.col_actions'))) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($strains as $qr):
                $strain = Strain::findByQrId($qr->id);
                if (!$strain) continue;
                $phase = $strain->lifecyclePhase();
                $canEdit = $qr->canBeEditedBy($currentUser);
                $cycle = $strain->totalCycleDays();
                $phaseColor = match ($phase) {
                    'planted'   => 'af-phosphor',
                    'flowering' => 'af-gold',
                    'harvested' => 'af-mute',
                    default     => 'af-faint',
                };
            ?>
                <tr>
                    <td>
                        <span class="af-w-500"><?= e($qr->title) ?></span>
                        <div class="af-fs-10 af-mute" style="margin-top:2px">
                            <?= icon('seedling', 'af-icon--sm') ?>
                            <?= e($strain->strainName) ?>
                        </div>
                    </td>
                    <td class="af-mono af-fs-11 af-phosphor" style="text-transform:none"><?= e($qr->publicId) ?></td>
                    <td>
                        <span class="af-badge af-badge--gold"><?= e(mb_strtoupper(t('admin.strains.form_genetics_' . $strain->genetics))) ?></span>
                    </td>
                    <td>
                        <span class="af-fs-10 af-track-1 <?= $phaseColor ?>">
                            ● <?= e(mb_strtoupper(t('admin.strains.phase_' . $phase))) ?>
                        </span>
                    </td>
                    <td class="af-text-r af-mono">
                        <?php if ($cycle !== null): ?>
                            <?= e((string) $cycle) ?>d
                        <?php else: ?>
                            <span class="af-faint">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="af-text-r af-mono"><?= e((string) $qr->scanCount()) ?></td>
                    <td class="af-text-r" style="white-space:nowrap">
                        <a href="/p/<?= e($qr->publicId) ?>" target="_blank" rel="noopener"
                           class="af-fs-10 af-track-1 af-soft" style="margin-right:14px">
                            <?= e(mb_strtoupper(t('admin.strains.action_view'))) ?>
                        </a>
                        <a href="/admin/strains/<?= e((string) $qr->id) ?>/qr"
                           class="af-fs-10 af-track-1 af-phosphor" style="margin-right:14px">
                            VER QR
                        </a>
                        <?php if ($canEdit && !$qr->isDeleted): ?>
                            <a href="/admin/strains/<?= e((string) $qr->id) ?>/edit"
                               class="af-fs-10 af-track-1 af-mute" style="margin-right:14px">
                                <?= e(mb_strtoupper(t('admin.strains.action_edit'))) ?>
                            </a>
                            <a href="/admin/strains/<?= e((string) $qr->id) ?>/delete"
                               class="af-fs-10 af-track-1 af-blood" style="text-decoration:underline">
                                <?= e(mb_strtoupper(t('admin.strains.action_archive'))) ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($isAdmin && $qr->isDeleted): ?>
                            <a href="/admin/strains/<?= e((string) $qr->id) ?>/delete-hard"
                               class="af-fs-10 af-track-1 af-blood" style="text-decoration:underline">
                                <?= e(mb_strtoupper(t('admin.strains.action_delete_hard'))) ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.strains.page_title');
require dirname(dirname(dirname(dirname(__DIR__)))) . '/templates/layouts/admin.php';
