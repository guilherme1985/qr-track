<?php
/**
 * Página de erro genérica.
 *
 * @var string $errorTitle
 * @var string $errorSubtitle
 * @var string $errorCode
 */
$errorTitle    = $errorTitle    ?? t('errors.not_found.title');
$errorSubtitle = $errorSubtitle ?? t('errors.not_found.subtitle');
$errorCode     = $errorCode     ?? '404';

ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top:80px;padding-bottom:80px;text-align:center">
    <div class="af-divider af-mb-6"><span>◆</span></div>

    <div class="af-display af-blood af-fs-42" style="font-size:96px;line-height:1;margin-bottom:8px;letter-spacing:8px">
        <?= e($errorCode) ?>
    </div>
    <div class="af-track-4 af-mute af-fs-9 af-mb-8">
        ━━ ARQUIVO INACESSÍVEL ━━
    </div>

    <div class="af-editorial af-w-500" style="font-size:28px;line-height:1.2;margin-bottom:14px">
        <?= e($errorTitle) ?>
    </div>
    <div class="af-mono af-gold af-track-2 af-fs-11 af-mb-8">
        ⚠ <?= e(mb_strtoupper($errorSubtitle)) ?> ⚠
    </div>

    <a href="/" class="af-btn"><?= e(mb_strtoupper(t('common.back'))) ?> ←</a>

    <div class="af-divider" style="margin-top:56px"><span>◆</span></div>

    <div class="af-fs-9 af-track-2 af-mute">
        <?= e(mb_strtoupper(t('common.app_name'))) ?> · <?= e(mb_strtoupper(t('common.app_subtitle'))) ?>
    </div>
</main>
<?php
$bodyContent = ob_get_clean();
$title = $errorTitle;
require __DIR__ . '/layout.php';
