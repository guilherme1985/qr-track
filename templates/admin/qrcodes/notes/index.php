<?php
/**
 * Listagem de notas (QRs do tipo 'note') com filtros.
 *
 * Vars esperadas:
 *   $notes          → list<QrCode>
 *   $totalCount     → int (sem limite, pra paginação)
 *   $filters        → array (status, category_id, mine, search)
 *   $categories     → list<Category> (pra dropdown)
 *   $currentUser    → User (admin ou curator)
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Category;
use ArkhamFiles\Markdown;
use ArkhamFiles\Note;
use ArkhamFiles\QrCode;

/** @var list<QrCode> $notes */
$notes = $notes ?? [];
$totalCount = $totalCount ?? count($notes);
$filters = $filters ?? [];
/** @var list<Category> $categories */
$categories = $categories ?? [];

$isAdmin = $currentUser?->isAdmin() ?? false;

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.notes.page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1"><?= e((string) $totalCount) ?> registros</div>
</div>

<div class="af-divider af-mb-4" style="max-width:280px;margin:0 0 18px 0">
    <span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.notes.kicker')) ?></span>
</div>

<p class="af-fs-12 af-soft af-mb-4" style="max-width:720px"><?= e(t('admin.notes.help')) ?></p>

<div class="af-flex af-gap-3 af-mb-4" style="align-items:center;flex-wrap:wrap">
    <a href="/admin/notes/new" class="af-btn af-btn--primary af-btn--sm">
        <?= e(mb_strtoupper(t('admin.notes.btn_new'))) ?>
    </a>

    <form method="get" action="/admin/notes" class="af-flex af-gap-2" style="margin:0;flex-wrap:wrap;align-items:center">
        <select name="status" class="af-input af-fs-11" style="max-width:160px;padding:6px 10px">
            <?php foreach (['all-active' => 'filter_status_all', 'active' => 'filter_status_active', 'expired' => 'filter_status_expired', 'disabled' => 'filter_status_disabled', 'deleted' => 'filter_status_deleted'] as $val => $key): ?>
                <option value="<?= e($val) ?>" <?= (($filters['status'] ?? 'all-active') === $val) ? 'selected' : '' ?>>
                    <?= e(t('admin.notes.' . $key)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="category_id" class="af-input af-fs-11" style="max-width:200px;padding:6px 10px">
            <option value=""><?= e(t('admin.notes.filter_category_all')) ?></option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e((string) $cat->id) ?>" <?= ((string)($filters['category_id'] ?? '') === (string) $cat->id) ? 'selected' : '' ?>>
                    <?= str_repeat('— ', $cat->depth) ?><?= e($cat->name) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if (!$isAdmin): /* curador: já é filtrado automaticamente, sem checkbox */ ?>
        <?php else: ?>
            <label class="af-fs-11 af-soft" style="display:flex;align-items:center;gap:6px;cursor:pointer">
                <input type="checkbox" name="mine" value="1" <?= !empty($filters['mine']) ? 'checked' : '' ?>>
                <?= e(t('admin.notes.filter_mine')) ?>
            </label>
        <?php endif; ?>

        <input type="text" name="q" value="<?= e((string)($filters['search'] ?? '')) ?>"
               class="af-input af-fs-11" style="max-width:200px;padding:6px 10px"
               placeholder="<?= e(t('admin.notes.filter_search')) ?>">

        <button type="submit" class="af-btn af-btn--ghost af-btn--sm">
            <?= e(mb_strtoupper(t('admin.notes.filter_apply'))) ?>
        </button>
        <?php if (!empty($filters['status']) || !empty($filters['category_id']) || !empty($filters['mine']) || !empty($filters['search'])): ?>
            <a href="/admin/notes" class="af-fs-10 af-track-1 af-mute" style="text-decoration:underline">
                <?= e(t('admin.notes.filter_clear')) ?>
            </a>
        <?php endif; ?>
    </form>
</div>

<?php if ($notes === []): ?>
    <div class="af-panel af-text-c af-mute" style="padding:48px">
        <div class="af-fs-12">
            <?= e(t(($filters !== []) ? 'admin.notes.empty_filtered' : 'admin.notes.empty')) ?>
        </div>
    </div>
<?php else: ?>
    <table class="af-table">
        <thead>
            <tr>
                <th><?= e(mb_strtoupper(t('admin.notes.col_title'))) ?></th>
                <th><?= e(mb_strtoupper(t('admin.notes.col_dossier_id'))) ?></th>
                <th><?= e(mb_strtoupper(t('admin.notes.col_category'))) ?></th>
                <th><?= e(mb_strtoupper(t('admin.notes.col_status'))) ?></th>
                <th class="af-text-r"><?= e(mb_strtoupper(t('admin.notes.col_scans'))) ?></th>
                <th class="af-text-r"><?= e(mb_strtoupper(t('admin.notes.col_actions'))) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notes as $n):
                $status = $n->status();
                $canEdit = $n->canBeEditedBy($currentUser);
                $cat = $n->categoryId ? Category::findById($n->categoryId) : null;
            ?>
                <tr>
                    <td>
                        <span class="af-w-500"><?= e($n->title) ?></span>
                        <?php
                        $preview = Markdown::preview(Note::getMarkdown($n->id), 80);
                        if ($preview !== ''): ?>
                            <div class="af-fs-10 af-mute" style="margin-top:2px"><?= e($preview) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="af-mono af-fs-11 af-phosphor" style="text-transform:none"><?= e($n->publicId) ?></td>
                    <td>
                        <?php if ($cat): ?>
                            <?php if ($cat->color): ?>
                                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:<?= e($cat->color) ?>;vertical-align:middle;margin-right:4px"></span>
                            <?php endif; ?>
                            <span class="af-fs-11"><?= e($cat->name) ?></span>
                        <?php else: ?>
                            <span class="af-fs-10 af-faint">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $statusKey = 'admin.notes.status_' . $status;
                        $statusColor = match ($status) {
                            'active'   => 'af-phosphor',
                            'expiring' => 'af-gold',
                            'expired'  => 'af-blood',
                            'disabled' => 'af-mute',
                            'deleted'  => 'af-blood',
                            default    => 'af-soft',
                        };
                        ?>
                        <span class="af-fs-10 af-track-1 <?= $statusColor ?>">● <?= e(mb_strtoupper(t($statusKey))) ?></span>
                    </td>
                    <td class="af-text-r af-mono"><?= e((string) $n->scanCount()) ?></td>
                    <td class="af-text-r" style="white-space:nowrap">
                        <a href="/p/<?= e($n->publicId) ?>" target="_blank" rel="noopener"
                           class="af-fs-10 af-track-1 af-soft" style="margin-right:14px">
                            <?= e(mb_strtoupper(t('admin.notes.action_view'))) ?>
                        </a>
                        <a href="/admin/notes/<?= e((string) $n->id) ?>/qr"
                           class="af-fs-10 af-track-1 af-phosphor" style="margin-right:14px">
                            VER QR
                        </a>
                        <?php if ($canEdit && !$n->isDeleted): ?>
                            <a href="/admin/notes/<?= e((string) $n->id) ?>/edit"
                               class="af-fs-10 af-track-1 af-mute" style="margin-right:14px">
                                <?= e(mb_strtoupper(t('admin.notes.action_edit'))) ?>
                            </a>
                            <a href="/admin/notes/<?= e((string) $n->id) ?>/delete"
                               class="af-fs-10 af-track-1 af-blood" style="text-decoration:underline">
                                <?= e(mb_strtoupper(t('admin.notes.action_archive'))) ?>
                            </a>
                        <?php endif; ?>
                        <?php if ($isAdmin && $n->isDeleted): ?>
                            <a href="/admin/notes/<?= e((string) $n->id) ?>/restore"
                               class="af-fs-10 af-track-1 af-phosphor" style="margin-right:14px">
                                <?= e(mb_strtoupper(t('admin.notes.action_restore'))) ?>
                            </a>
                            <a href="/admin/notes/<?= e((string) $n->id) ?>/delete-hard"
                               class="af-fs-10 af-track-1 af-blood" style="text-decoration:underline">
                                <?= e(mb_strtoupper(t('admin.notes.action_delete_hard'))) ?>
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
$title = t('admin.notes.page_title');
require dirname(dirname(dirname(dirname(__DIR__)))) . '/templates/layouts/admin.php';
