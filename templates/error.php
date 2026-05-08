<?php
/**
 * Página de erro genérica.
 *
 * @var string $errorTitle
 * @var string $errorSubtitle
 * @var string $errorCode
 */
$errorTitle    = $errorTitle    ?? 'Documento ausente';
$errorSubtitle = $errorSubtitle ?? 'Arquivo não localizado';
$errorCode     = $errorCode     ?? '404';

ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top: 80px; padding-bottom: 80px; text-align: center;">
    <div class="af-divider" style="margin-bottom: 24px;"><span>◆</span></div>

    <div class="af-display af-blood" style="font-size: 96px; line-height: 1; margin-bottom: 8px; letter-spacing: 8px;">
        <?= htmlspecialchars($errorCode, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="af-track-4 af-mute" style="font-size: 9px; margin-bottom: 36px;">
        ━━ ARQUIVO INACESSÍVEL ━━
    </div>

    <div class="af-editorial" style="font-size: 28px; font-weight: 500; line-height: 1.2; margin-bottom: 14px;">
        <?= htmlspecialchars($errorTitle, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="af-mono af-gold af-track-2" style="font-size: 11px; margin-bottom: 36px;">
        ⚠ <?= htmlspecialchars(mb_strtoupper($errorSubtitle, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?> ⚠
    </div>

    <a href="/" class="af-btn">RETORNAR ←</a>

    <div class="af-divider" style="margin-top: 56px;"><span>◆</span></div>

    <div style="font-size: 9px; letter-spacing: 2px; color: var(--af-text-mute);">
        ARKHAM FILES · QR DIVISION
    </div>
</main>
<?php
$bodyContent = ob_get_clean();
$title = $errorTitle;
require __DIR__ . '/layout.php';
