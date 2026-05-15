<?php
/**
 * Página de erro genérica. Suporta 4 modos:
 *
 *   1. 404 / WIP / outros          → tela básica (code + title + subtitle + botão back)
 *   2. 410 (expirado, /p/{id})     → kicker + title + subtitle + body + archived_on + retention
 *   3. 403 (forbidden)             → tela básica com tom blood mais forte
 *   4. 500 (server error)          → tela com tom blood + "ANOMALIA DETECTADA"
 *
 * Variáveis (todas opcionais — defaults sensatos):
 *   $errorCode        → 404 | 410 | 403 | 500 | WIP ...
 *   $errorKicker      → linha de prefixo ouro (modo 410)
 *   $errorTitle       → título principal
 *   $errorSubtitle    → linha em mono blood
 *   $errorBody        → parágrafo descritivo (HTML permitido)
 *   $errorArchivedOn  → data formatada (modo 410)
 *   $errorRetention   → texto institucional do rodapé (modo 410)
 */

$errorCode       = $errorCode       ?? '404';
$errorKicker     = $errorKicker     ?? null;
$errorTitle      = $errorTitle      ?? t('errors.not_found.title');
$errorSubtitle   = $errorSubtitle   ?? t('errors.not_found.subtitle');
$errorBody       = $errorBody       ?? null;
$errorArchivedOn = $errorArchivedOn ?? null;
$errorRetention  = $errorRetention  ?? null;

$inaccessibleLabel = match ((string) $errorCode) {
    '410'   => '━━ ' . mb_strtoupper(t('errors.expired.banner_label'))    . ' ━━',
    '403'   => '━━ ' . mb_strtoupper(t('errors.forbidden.banner_label'))  . ' ━━',
    '500'   => '━━ ' . mb_strtoupper(t('errors.server.banner_label'))     . ' ━━',
    default => '━━ ' . mb_strtoupper(t('errors.not_found.banner_label'))  . ' ━━',
};

ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top:80px;padding-bottom:80px;text-align:center">
    <div class="af-divider af-mb-6"><span>◆</span></div>

    <?php if ($errorKicker): ?>
        <div class="af-fs-10 af-track-4 af-gold af-mb-3">
            <?= e($errorKicker) ?>
        </div>
    <?php endif; ?>

    <div class="af-display af-blood af-fs-42" style="font-size:96px;line-height:1;margin-bottom:8px;letter-spacing:8px">
        <?= e($errorCode) ?>
    </div>
    <div class="af-track-4 af-mute af-fs-9 af-mb-8">
        <?= e($inaccessibleLabel) ?>
    </div>

    <div class="af-editorial af-w-500" style="font-size:28px;line-height:1.2;margin-bottom:14px">
        <?= e($errorTitle) ?>
    </div>
    <div class="af-mono af-gold af-track-2 af-fs-11 af-mb-6">
        ⚠ <?= e(mb_strtoupper($errorSubtitle)) ?> ⚠
    </div>

    <?php if ($errorBody): ?>
        <div class="af-panel" style="max-width:520px;margin:0 auto 28px auto;text-align:left">
            <p class="af-fs-12 af-soft" style="line-height:1.7"><?= $errorBody /* contém HTML inline */ ?></p>

            <?php if ($errorArchivedOn): ?>
                <table class="af-table" style="border:none;border-top:0.5px solid var(--af-border);margin-top:16px">
                    <tbody>
                        <tr style="border:none">
                            <td class="af-mute af-fs-11" style="padding-left:0;width:200px">
                                <?= e(t('errors.expired.archived_on')) ?>
                            </td>
                            <td class="af-mono af-blood"><?= e($errorArchivedOn) ?></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <a href="/" class="af-btn"><?= e(mb_strtoupper(t('common.back'))) ?> ←</a>

    <?php if ($errorRetention): ?>
        <div class="af-fs-9 af-track-2 af-faint af-mt-6" style="max-width:520px;margin:24px auto 0 auto;line-height:1.6">
            <?= e($errorRetention) ?>
        </div>
    <?php endif; ?>

    <div class="af-divider" style="margin-top:56px"><span>◆</span></div>

    <div class="af-fs-9 af-track-2 af-mute">
        <?= e(mb_strtoupper(t('common.app_name'))) ?> · <?= e(mb_strtoupper(t('common.app_subtitle'))) ?>
    </div>
</main>
<?php
$bodyContent = ob_get_clean();
$title = $errorTitle;
require __DIR__ . '/layout.php';
