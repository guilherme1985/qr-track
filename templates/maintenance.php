<?php
/**
 * Tela pública de manutenção. Renderizada pelo Maintenance::render()
 * quando data/maintenance.flag existe.
 *
 * Variáveis:
 *   $maintenanceMessage → conteúdo do arquivo flag (custom message)
 */
$maintenanceMessage = $maintenanceMessage ?? '';

ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top:80px;padding-bottom:80px;text-align:center">
    <div class="af-divider af-mb-6"><span>◆</span></div>

    <!-- Logo -->
    <div style="margin-bottom:32px">
        <?php $logoSize = 96; require __DIR__ . '/components/logo.php'; ?>
    </div>

    <div class="af-fs-10 af-track-4 af-gold af-mb-3">
        ━━ <?= e(t('maintenance.kicker')) ?> ━━
    </div>

    <!-- Engrenagem decorativa via símbolo -->
    <div class="af-display af-gold" style="font-size:72px;line-height:1;margin-bottom:8px">
        ⚙
    </div>

    <div class="af-track-4 af-mute af-fs-9 af-mb-6">
        ━━ <?= e(t('maintenance.system_offline')) ?> ━━
    </div>

    <div class="af-editorial af-w-500" style="font-size:32px;line-height:1.2;margin-bottom:18px">
        <?= e(t('maintenance.title')) ?>
    </div>

    <div class="af-mono af-gold af-track-2 af-fs-11 af-mb-6">
        ⚠ <?= e(mb_strtoupper(t('maintenance.subtitle'))) ?> ⚠
    </div>

    <div class="af-panel" style="max-width:520px;margin:0 auto 28px auto;text-align:left;background:rgba(168,139,76,0.04)">
        <div class="af-fs-10 af-track-3 af-gold af-mb-3">
            ━━ <?= e(t('maintenance.notice_label')) ?> ━━
        </div>
        <p class="af-fs-12 af-soft" style="line-height:1.7">
            <?= e($maintenanceMessage) ?>
        </p>
    </div>

    <div class="af-fs-9 af-track-2 af-faint" style="max-width:520px;margin:24px auto 0 auto;line-height:1.6">
        <?= e(t('maintenance.retry_hint')) ?>
    </div>

    <div class="af-divider" style="margin-top:56px"><span>◆</span></div>

    <div class="af-fs-9 af-track-2 af-mute">
        <?= e(mb_strtoupper(t('common.app_name'))) ?> · <?= e(mb_strtoupper(t('common.app_subtitle'))) ?>
    </div>
</main>
<?php
$bodyContent = ob_get_clean();
$title = t('maintenance.title');
require __DIR__ . '/layout.php';
