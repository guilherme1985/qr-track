<?php
/**
 * Viewer público de nota — substitui o placeholder do PR 06 quando um
 * QR do tipo 'note' é acessado em /p/{public_id}.
 *
 * Variáveis esperadas:
 *   $qr           → ArkhamFiles\QrCode  (já validado: não expirou, não está disabled/deleted)
 *   $renderedHtml → string  (Markdown já renderizado e sanitizado)
 *   $category     → ArkhamFiles\Category|null
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Category;
use ArkhamFiles\QrCode;

/** @var QrCode $qr */
$qr = $qr;
/** @var Category|null $category */
$category = $category ?? null;
$renderedHtml = $renderedHtml ?? '';

[$expKey, $expParams] = $qr->expirationLabel();

ob_start();
?>
<main class="af-container af-container--narrow"
      style="padding-top:48px;padding-bottom:64px;max-width:720px">

    <!-- Header institucional -->
    <div class="af-text-c">
        <?php $logoSize = 80; require dirname(__DIR__) . '/components/logo.php'; ?>
    </div>

    <div class="af-divider af-mt-6 af-mb-4"><span>◆</span></div>

    <div class="af-fs-10 af-track-4 af-gold af-text-c af-mb-3">
        <?= e(t('public.note_viewer.kicker')) ?>
    </div>

    <!-- Aviso de expiração próxima -->
    <?php if ($qr->isExpiring()): ?>
        <div class="af-text-c af-fs-10 af-track-2 af-gold af-mb-4"
             style="padding:8px 14px;border:0.5px dashed var(--af-gold);background:rgba(168,139,76,0.06)">
            <?= e(t('public.note_viewer.expiring_warning', ['label' => t($expKey, $expParams)])) ?>
        </div>
    <?php endif; ?>

    <!-- Título da nota -->
    <h1 class="af-editorial af-w-500 af-text-c"
        style="font-size:36px;line-height:1.15;margin:0 0 10px 0;letter-spacing:-0.01em">
        <?= e($qr->title) ?>
    </h1>

    <div class="af-mono af-fs-11 af-track-2 af-mute af-text-c af-mb-2">
        <?= e(t('public.note_viewer.dossier_id')) ?>
        <span class="af-phosphor" style="text-transform:none"><?= e($qr->publicId) ?></span>
    </div>

    <?php if ($category): ?>
        <div class="af-fs-10 af-track-2 af-mute af-text-c af-mb-6">
            <?= e(t('public.note_viewer.category_label')) ?>:
            <?php if ($category->color): ?>
                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:<?= e($category->color) ?>;vertical-align:middle;margin:0 4px"></span>
            <?php endif; ?>
            <span class="af-soft"><?= e($category->name) ?></span>
        </div>
    <?php else: ?>
        <div class="af-mb-6"></div>
    <?php endif; ?>

    <!-- Metadata cartão -->
    <div class="af-panel af-mb-6" style="background:rgba(168,139,76,0.03)">
        <div class="af-fs-10 af-track-3 af-gold af-mb-3">
            ━━ <?= e(t('public.note_viewer.classification')) ?> ━━
        </div>
        <table class="af-table" style="border:none">
            <tbody>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;width:180px;vertical-align:top">
                        <?= e(t('public.note_viewer.class_label')) ?>
                    </td>
                    <td><span class="af-badge af-badge--gold">MEMORANDO</span></td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                        <?= e(t('public.note_viewer.authored_on')) ?>
                    </td>
                    <td class="af-mono"><?= e($qr->createdAt) ?></td>
                </tr>
                <?php if ($qr->updatedAt !== $qr->createdAt): ?>
                    <tr style="border:none">
                        <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                            <?= e(t('public.note_viewer.last_revision')) ?>
                        </td>
                        <td class="af-mono"><?= e($qr->updatedAt) ?></td>
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

    <!-- Conteúdo principal: markdown renderizado -->
    <article class="af-note-content">
        <?= $renderedHtml /* já sanitizado */ ?>
    </article>

    <!-- Footer institucional -->
    <div class="af-divider" style="margin-top:64px"><span>◆</span></div>

    <div class="af-fs-9 af-track-2 af-faint af-text-c af-mb-2">
        <?= e(t('public.note_viewer.document_accessed', ['datetime' => gmdate('d.m.Y · H:i') . ' UTC'])) ?>
    </div>
    <div class="af-fs-9 af-track-2 af-mute af-text-c">
        <?= e(mb_strtoupper(t('public.note_viewer.footer'))) ?>
    </div>
</main>

<style>
/* Estilos do conteúdo renderizado da nota (Markdown → HTML).
   Escopo isolado para não interferir com o restante do site. */
.af-note-content {
    font-family: var(--af-font-serif, Georgia, serif);
    font-size: 16px;
    line-height: 1.75;
    color: var(--af-text);
    max-width: 660px;
    margin: 0 auto;
}
.af-note-content > * + * { margin-top: 1.1em; }
.af-note-content h1,
.af-note-content h2,
.af-note-content h3,
.af-note-content h4 {
    font-family: var(--af-font-editorial, Georgia, serif);
    font-weight: 500;
    color: var(--af-text);
    line-height: 1.25;
    margin-top: 2em;
    margin-bottom: 0.5em;
}
.af-note-content h1 { font-size: 26px; border-bottom: 0.5px solid var(--af-border); padding-bottom: 8px; }
.af-note-content h2 { font-size: 22px; color: var(--af-gold); }
.af-note-content h3 { font-size: 18px; }
.af-note-content h4 { font-size: 15px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--af-soft); }
.af-note-content p { margin: 0 0 0.9em 0; }
.af-note-content a {
    color: var(--af-phosphor);
    text-decoration: underline;
    text-decoration-thickness: 0.5px;
    text-underline-offset: 3px;
}
.af-note-content a:hover { color: var(--af-phosphor-bright, #9ee85e); }
.af-note-content strong { color: var(--af-text); font-weight: 600; }
.af-note-content em { font-style: italic; color: var(--af-soft); }
.af-note-content code {
    font-family: var(--af-font-mono, Consolas, monospace);
    font-size: 0.88em;
    background: rgba(125, 219, 79, 0.08);
    color: var(--af-phosphor);
    padding: 1px 6px;
    border-radius: 2px;
}
.af-note-content pre {
    font-family: var(--af-font-mono, Consolas, monospace);
    background: rgba(0, 0, 0, 0.35);
    border: 0.5px solid var(--af-border);
    border-left: 2px solid var(--af-phosphor);
    padding: 12px 16px;
    overflow-x: auto;
    font-size: 13px;
    line-height: 1.55;
}
.af-note-content pre code {
    background: transparent;
    color: var(--af-text);
    padding: 0;
}
.af-note-content blockquote {
    border-left: 2px solid var(--af-gold);
    padding-left: 18px;
    margin-left: 0;
    color: var(--af-soft);
    font-style: italic;
}
.af-note-content ul, .af-note-content ol { padding-left: 24px; }
.af-note-content li { margin: 4px 0; }
.af-note-content table {
    border-collapse: collapse;
    width: 100%;
    margin: 1em 0;
    font-size: 14px;
}
.af-note-content table th,
.af-note-content table td {
    border-bottom: 0.5px solid var(--af-border);
    padding: 8px 10px;
    text-align: left;
}
.af-note-content table th {
    color: var(--af-gold);
    font-family: var(--af-font-mono);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 0.5px solid var(--af-gold);
}
.af-note-content hr {
    border: none;
    border-top: 0.5px solid var(--af-border);
    margin: 2em 0;
}
.af-note-content img {
    max-width: 100%;
    height: auto;
    border: 0.5px solid var(--af-border);
    margin: 1em 0;
}
</style>
<?php
$bodyContent = ob_get_clean();
$title = $qr->title;
require dirname(__DIR__) . '/layout.php';
