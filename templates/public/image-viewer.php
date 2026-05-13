<?php
/**
 * Viewer público de imagem — substitui o placeholder do PR 06 quando um
 * QR do tipo 'image' é acessado em /p/{public_id}.
 *
 * Tom: "Evidência Fotográfica · Setor de Evidências Visuais (S.E.V.)".
 * A imagem é o conteúdo principal — clique abre lightbox fullscreen.
 *
 * Variáveis esperadas:
 *   $qr       → QrCode (já validado)
 *   $img      → ImageQr (já carregado)
 *   $category → Category|null
 */
use ArkhamFiles\Category;
use ArkhamFiles\ImageQr;
use ArkhamFiles\QrCode;

/** @var QrCode $qr */
$qr = $qr;
/** @var ImageQr $img */
$img = $img;
/** @var Category|null $category */
$category = $category ?? null;

[$expKey, $expParams] = $qr->expirationLabel();

ob_start();
?>
<main class="af-container af-container--narrow"
      style="padding-top:48px;padding-bottom:64px;max-width:820px">

    <!-- Header institucional -->
    <div class="af-text-c">
        <?php $logoSize = 80; require dirname(__DIR__) . '/components/logo.php'; ?>
    </div>

    <div class="af-divider af-mt-6 af-mb-4"><span>◆</span></div>

    <div class="af-fs-10 af-track-4 af-gold af-text-c af-mb-3">
        <?= e(t('public.image_viewer.kicker')) ?>
    </div>

    <?php if ($qr->isExpiring()): ?>
        <div class="af-text-c af-fs-10 af-track-2 af-gold af-mb-4"
             style="padding:8px 14px;border:0.5px dashed var(--af-gold);background:rgba(168,139,76,0.06)">
            <?= e(t('public.image_viewer.expiring_warning', ['label' => t($expKey, $expParams)])) ?>
        </div>
    <?php endif; ?>

    <!-- Título principal -->
    <h1 class="af-editorial af-w-500 af-text-c"
        style="font-size:38px;line-height:1.15;margin:0 0 6px 0;letter-spacing:-0.01em">
        <?= e($qr->title) ?>
    </h1>

    <div class="af-mono af-fs-11 af-track-2 af-mute af-text-c af-mb-1">
        <?= e(t('public.image_viewer.dossier_id')) ?>
        <span class="af-phosphor" style="text-transform:none"><?= e($qr->publicId) ?></span>
    </div>

    <div class="af-fs-9 af-track-3 af-faint af-text-c af-mb-6">
        — <?= e(t('public.image_viewer.department')) ?> · <?= e(t('public.image_viewer.department_short')) ?> —
    </div>

    <!-- Painel CLASSIFICAÇÃO -->
    <div class="af-panel af-mb-5" style="background:rgba(168,139,76,0.03)">
        <div class="af-fs-10 af-track-3 af-gold af-mb-3">
            ━━ <?= e(t('public.image_viewer.classification')) ?> ━━
        </div>
        <table class="af-table" style="border:none">
            <tbody>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;width:200px;vertical-align:top">
                        <?= e(t('public.image_viewer.class_label')) ?>
                    </td>
                    <td><span class="af-badge af-badge--gold">VISUAL</span></td>
                </tr>
                <?php if ($category): ?>
                    <tr style="border:none">
                        <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                            <?= e(t('public.image_viewer.category_label')) ?>
                        </td>
                        <td>
                            <?php if ($category->color): ?>
                                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:<?= e($category->color) ?>;vertical-align:middle;margin-right:4px"></span>
                            <?php endif; ?>
                            <?= e($category->name) ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                        <?= e(mb_strtoupper(t('common.validity'))) ?>
                    </td>
                    <td class="af-mono <?= $qr->isExpiring() ? 'af-gold' : 'af-soft' ?>">
                        <?= e(t($expKey, $expParams)) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- IMAGEM CENTRAL — clicável pra abrir lightbox -->
    <div class="af-image-frame">
        <img id="af-img-original" src="<?= e($img->originalUrl()) ?>"
             alt="<?= e($qr->title) ?>" loading="lazy"
             onclick="afImageLightbox.open()">

        <div class="af-image-frame__actions">
            <button type="button" class="af-btn af-btn--ghost af-btn--sm" onclick="afImageLightbox.open()">
                <?= e(mb_strtoupper(t('public.image_viewer.view_full'))) ?>
            </button>
            <a href="<?= e($img->originalUrl()) ?>"
               download="<?= e($img->originalFilename ?: ($qr->publicId . '.' . substr($img->mimeType, 6))) ?>"
               class="af-btn af-btn--ghost af-btn--sm">
                <?= e(mb_strtoupper(t('public.image_viewer.download'))) ?>
            </a>
        </div>
    </div>

    <!-- METADADOS DO ARQUIVO -->
    <div class="af-panel af-mb-5">
        <div class="af-fs-10 af-track-3 af-gold af-mb-3">
            <?= e(t('public.image_viewer.metadata_title')) ?>
        </div>
        <table class="af-table" style="border:none">
            <tbody>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;width:200px;vertical-align:top">
                        <?= e(t('public.image_viewer.format_label')) ?>
                    </td>
                    <td class="af-mono"><?= e($img->formatLabel()) ?></td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                        <?= e(t('public.image_viewer.dimensions_label')) ?>
                    </td>
                    <td class="af-mono"><?= e($img->dimensionsLabel()) ?></td>
                </tr>
                <?php if ($img->aspectRatio()): ?>
                    <tr style="border:none">
                        <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                            <?= e(t('public.image_viewer.aspect_label')) ?>
                        </td>
                        <td class="af-mono"><?= e($img->aspectRatio()) ?></td>
                    </tr>
                <?php endif; ?>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                        <?= e(t('public.image_viewer.size_label')) ?>
                    </td>
                    <td class="af-mono"><?= e($img->fileSizeLabel()) ?></td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                        <?= e(t('public.image_viewer.uploaded_label')) ?>
                    </td>
                    <td class="af-mono"><?= e($img->uploadedAt) ?></td>
                </tr>
                <?php if ($img->originalFilename): ?>
                    <tr style="border:none">
                        <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                            <?= e(t('public.image_viewer.filename_label')) ?>
                        </td>
                        <td class="af-mono af-fs-10" style="word-break:break-all"><?= e($img->originalFilename) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Warning institucional -->
    <div class="af-fs-9 af-track-3 af-blood af-text-c af-mt-6"
         style="padding:10px 14px;border-top:0.5px solid var(--af-border);border-bottom:0.5px solid var(--af-border)">
        ▲ <?= e(mb_strtoupper(t('public.image_viewer.warning_label'))) ?> ▲
    </div>

    <!-- Rodapé -->
    <div class="af-divider" style="margin-top:48px"><span>◆</span></div>

    <div class="af-fs-9 af-track-2 af-faint af-text-c af-mb-2">
        <?= e(t('public.image_viewer.document_accessed', ['datetime' => gmdate('d.m.Y · H:i') . ' UTC'])) ?>
    </div>
    <div class="af-fs-9 af-track-2 af-mute af-text-c">
        <?= e(mb_strtoupper(t('public.image_viewer.footer'))) ?>
    </div>
</main>

<!-- Lightbox overlay (escondido por padrão) -->
<div id="af-lightbox" class="af-lightbox" onclick="afImageLightbox.close()">
    <button type="button" class="af-lightbox__close" aria-label="<?= e(t('public.image_viewer.close_full')) ?>"
            onclick="afImageLightbox.close(event)">×</button>
    <img class="af-lightbox__img" src="" alt="">
</div>

<style>
/* Frame da imagem principal */
.af-image-frame {
    margin: 0 auto 32px auto;
    max-width: 760px;
    border: 0.5px solid var(--af-border);
    background: #0a0a0a;
}
.af-image-frame img {
    width: 100%;
    max-height: 600px;
    object-fit: contain;
    display: block;
    cursor: zoom-in;
    background: #0a0a0a;
}
.af-image-frame__actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    padding: 12px 14px;
    border-top: 0.5px solid var(--af-border);
    background: rgba(168, 139, 76, 0.04);
    flex-wrap: wrap;
}

/* Lightbox fullscreen */
.af-lightbox {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(5, 5, 5, 0.94);
    z-index: 9999;
    cursor: zoom-out;
    align-items: center;
    justify-content: center;
    padding: 32px;
}
.af-lightbox--open { display: flex; }
.af-lightbox__img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border: 0.5px solid var(--af-gold);
}
.af-lightbox__close {
    position: absolute;
    top: 16px;
    right: 24px;
    background: transparent;
    border: 0.5px solid var(--af-gold);
    color: var(--af-gold);
    font-size: 24px;
    width: 44px;
    height: 44px;
    cursor: pointer;
    line-height: 1;
    transition: all 0.15s ease;
}
.af-lightbox__close:hover {
    background: var(--af-gold);
    color: var(--af-bg);
}
</style>

<script>
// Lightbox simples — sem framework, sem deps
const afImageLightbox = {
    el: null,
    img: null,
    init() {
        this.el = document.getElementById('af-lightbox');
        this.img = this.el.querySelector('.af-lightbox__img');
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
        });
    },
    open() {
        if (!this.el) this.init();
        this.img.src = document.getElementById('af-img-original').src;
        this.el.classList.add('af-lightbox--open');
        document.body.style.overflow = 'hidden';
    },
    close(event) {
        if (event) event.stopPropagation();
        if (!this.el) return;
        this.el.classList.remove('af-lightbox--open');
        this.img.src = '';
        document.body.style.overflow = '';
    },
};
</script>
<?php
$bodyContent = ob_get_clean();
$title = $qr->title;
require dirname(__DIR__) . '/layout.php';
