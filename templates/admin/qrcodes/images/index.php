<?php
/**
 * Listagem de imagens (QRs do tipo 'image').
 * Diferente das listagens de notes/strains: usa GRID com thumbnails
 * em vez de tabela, porque o thumb é o principal identificador visual.
 */
use ArkhamFiles\Category;
use ArkhamFiles\ImageQr;
use ArkhamFiles\QrCode;

/** @var list<QrCode> $images */
$images = $images ?? [];
$totalCount = $totalCount ?? count($images);
$filters = $filters ?? [];
$isAdmin = $currentUser?->isAdmin() ?? false;

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.images.page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1"><?= e((string) $totalCount) ?> registros</div>
</div>

<div class="af-divider af-mb-4" style="max-width:280px;margin:0 0 18px 0">
    <span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.images.kicker')) ?></span>
</div>

<p class="af-fs-12 af-soft af-mb-4" style="max-width:720px"><?= e(t('admin.images.help')) ?></p>

<div class="af-flex af-gap-3 af-mb-4" style="align-items:center;flex-wrap:wrap">
    <a href="/admin/images/new" class="af-btn af-btn--primary af-btn--sm">
        <?= e(mb_strtoupper(t('admin.images.btn_new'))) ?>
    </a>

    <form method="get" action="/admin/images" class="af-flex af-gap-2" style="margin:0;flex-wrap:wrap;align-items:center">
        <select name="status" class="af-input af-fs-11" style="max-width:160px;padding:6px 10px">
            <?php foreach (['all-active' => 'filter_status_all', 'active' => 'filter_status_active', 'expired' => 'filter_status_expired', 'disabled' => 'filter_status_disabled', 'deleted' => 'filter_status_deleted'] as $val => $key): ?>
                <option value="<?= e($val) ?>" <?= (($filters['status'] ?? 'all-active') === $val) ? 'selected' : '' ?>>
                    <?= e(t('admin.images.' . $key)) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($isAdmin): ?>
            <label class="af-fs-11 af-soft" style="display:flex;align-items:center;gap:6px;cursor:pointer">
                <input type="checkbox" name="mine" value="1" <?= !empty($filters['mine']) ? 'checked' : '' ?>>
                <?= e(t('admin.images.filter_mine')) ?>
            </label>
        <?php endif; ?>

        <input type="text" name="q" value="<?= e((string)($filters['search'] ?? '')) ?>"
               class="af-input af-fs-11" style="max-width:200px;padding:6px 10px"
               placeholder="<?= e(t('admin.images.filter_search')) ?>">

        <button type="submit" class="af-btn af-btn--ghost af-btn--sm">
            <?= e(mb_strtoupper(t('admin.images.filter_apply'))) ?>
        </button>
    </form>
</div>

<?php if ($images === []): ?>
    <div class="af-panel af-text-c af-mute" style="padding:48px">
        <div class="af-fs-12">
            <?= e(t(($filters !== []) ? 'admin.images.empty_filtered' : 'admin.images.empty')) ?>
        </div>
    </div>
<?php else: ?>
    <div class="af-image-grid">
        <?php foreach ($images as $qr):
            $img = ImageQr::findByQrId($qr->id);
            if (!$img) continue;
            $status = $qr->status();
            $canEdit = $qr->canBeEditedBy($currentUser);
            $statusColor = match ($status) {
                'active'   => 'af-phosphor',
                'expiring' => 'af-gold',
                'expired'  => 'af-blood',
                'disabled' => 'af-mute',
                'deleted'  => 'af-blood',
                default    => 'af-soft',
            };
        ?>
            <div class="af-image-card">
                <a href="/p/<?= e($qr->publicId) ?>" target="_blank" rel="noopener" class="af-image-card__thumb-link">
                    <img src="<?= e($img->thumbnailUrl()) ?>" alt="<?= e($qr->title) ?>" loading="lazy">
                </a>

                <div class="af-image-card__body">
                    <div class="af-fs-12 af-w-500" style="line-height:1.3">
                        <?= e($qr->title) ?>
                    </div>
                    <div class="af-mono af-fs-10 af-phosphor af-mt-1" style="text-transform:none">
                        <?= e($qr->publicId) ?>
                    </div>
                    <div class="af-fs-10 af-mute af-mt-1">
                        <?= e($img->dimensionsLabel()) ?> · <?= e($img->fileSizeLabel()) ?>
                    </div>
                    <div class="af-fs-10 af-track-1 af-mt-2 <?= $statusColor ?>">
                        ● <?= e(mb_strtoupper(t('admin.images.status_' . $status))) ?>
                        <span class="af-mute" style="margin-left:6px"><?= e((string) $qr->scanCount()) ?> acessos</span>
                    </div>
                </div>

                <div class="af-image-card__actions">
                    <a href="/p/<?= e($qr->publicId) ?>" target="_blank" rel="noopener" class="af-fs-10 af-track-1 af-soft">
                        <?= e(mb_strtoupper(t('admin.images.action_view'))) ?>
                    </a>
                    <?php if ($canEdit && !$qr->isDeleted): ?>
                        <a href="/admin/images/<?= e((string) $qr->id) ?>/edit" class="af-fs-10 af-track-1 af-mute">
                            <?= e(mb_strtoupper(t('admin.images.action_edit'))) ?>
                        </a>
                        <a href="/admin/images/<?= e((string) $qr->id) ?>/delete" class="af-fs-10 af-track-1 af-blood" style="text-decoration:underline">
                            <?= e(mb_strtoupper(t('admin.images.action_archive'))) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($isAdmin && $qr->isDeleted): ?>
                        <a href="/admin/images/<?= e((string) $qr->id) ?>/delete-hard" class="af-fs-10 af-track-1 af-blood" style="text-decoration:underline">
                            <?= e(mb_strtoupper(t('admin.images.action_delete_hard'))) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <style>
    .af-image-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 18px;
    }
    .af-image-card {
        border: 0.5px solid var(--af-border);
        background: rgba(255, 255, 255, 0.02);
        display: flex;
        flex-direction: column;
    }
    .af-image-card__thumb-link {
        display: block;
        aspect-ratio: 1;
        background: #101010;
        overflow: hidden;
    }
    .af-image-card__thumb-link img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
        transition: transform 0.2s ease;
    }
    .af-image-card__thumb-link:hover img {
        transform: scale(1.02);
    }
    .af-image-card__body {
        padding: 12px 14px;
        flex: 1;
    }
    .af-image-card__actions {
        display: flex;
        gap: 12px;
        padding: 10px 14px;
        border-top: 0.5px solid var(--af-border);
        flex-wrap: wrap;
    }
    </style>
<?php endif; ?>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.images.page_title');
require dirname(dirname(dirname(dirname(__DIR__)))) . '/templates/layouts/admin.php';
