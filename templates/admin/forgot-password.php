<?php
ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top:60px;padding-bottom:60px;text-align:center">
    <div class="af-text-c">
        <?php $logoSize = 96; require dirname(__DIR__) . '/components/logo.php'; ?>
    </div>

    <div class="af-divider af-mt-6 af-mb-6"><span>◆</span></div>

    <div class="af-fs-10 af-track-4 af-gold af-mb-3">
        <?= e(t('admin.forgot.kicker')) ?>
    </div>

    <div class="af-editorial af-w-500" style="font-size:32px;line-height:1.2;margin-bottom:8px">
        <?= e(t('admin.forgot.title')) ?>
    </div>
    <div class="af-mono af-fs-11 af-track-2 af-blood af-mb-6">
        ▲ <?= e(mb_strtoupper(t('admin.forgot.subtitle'))) ?> ▲
    </div>

    <div class="af-panel af-text-c" style="text-align:left">
        <p class="af-fs-13 af-soft" style="line-height:1.7"><?= t('admin.forgot.body') /* contém HTML inline */ ?></p>
    </div>

    <a href="/admin/login" class="af-btn af-mt-6">
        ← <?= e(mb_strtoupper(t('admin.forgot.back_to_login'))) ?>
    </a>

    <div class="af-divider" style="margin-top:48px"><span>◆</span></div>

    <div class="af-fs-9 af-track-2 af-mute">
        <?= e(mb_strtoupper(t('common.app_name'))) ?> · <?= e(mb_strtoupper(t('common.app_subtitle'))) ?>
    </div>
</main>
<?php
$bodyContent = ob_get_clean();
$title = t('admin.forgot.page_title');
require dirname(__DIR__) . '/layout.php';
