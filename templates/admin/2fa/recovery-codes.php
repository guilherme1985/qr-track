<?php
/**
 * Mostra os 10 códigos de recuperação após ativação do 2FA.
 * Vista uma única vez — depois disso só dá pra gerar novos via /admin/profile.
 *
 * Vars esperadas:
 *   $codes → list<string>
 */
use ArkhamFiles\Auth\Session;

/** @var list<string> $codes */
$codes = $codes;
$currentUser = $currentUser ?? \ArkhamFiles\Auth\Auth::currentUser();

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.two_factor_recovery_codes.page_title'))) ?></div>
</div>

<div class="af-divider af-mb-4" style="max-width:320px;margin:0 0 20px 0">
    <span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.two_factor_recovery_codes.kicker')) ?></span>
</div>

<div class="af-panel af-mb-4" style="max-width:560px;border-color:var(--af-gold)">
    <div class="af-editorial af-w-500 af-mb-3" style="font-size:22px">
        <?= e(t('admin.two_factor_recovery_codes.title')) ?>
    </div>
    <p class="af-fs-12 af-soft" style="line-height:1.7">
        <?= t('admin.two_factor_recovery_codes.help') /* contém HTML */ ?>
    </p>

    <div style="background:var(--af-bg);border:0.5px solid var(--af-border);padding:18px;margin-top:14px;font-family:var(--af-font-mono);font-size:16px;line-height:2;letter-spacing:2px;color:var(--af-phosphor);user-select:all;text-align:center">
        <?php foreach ($codes as $idx => $code): ?>
            <div><?= sprintf('%02d.', $idx + 1) ?> <?= e($code) ?></div>
        <?php endforeach; ?>
    </div>

    <div class="af-fs-10 af-mute af-mt-2 af-text-c">
        ↑ <?= e(t('admin.two_factor_recovery_codes.warning')) ?>
    </div>
</div>

<form method="post" action="/admin/2fa/recovery-codes/confirm" style="max-width:560px">
    <?= Session::csrfField() ?>
    <button type="submit" class="af-btn af-btn--primary">
        <?= e(mb_strtoupper(t('admin.two_factor_recovery_codes.confirm_seen'))) ?>
    </button>
</form>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.two_factor_recovery_codes.page_title');
require dirname(dirname(dirname(__DIR__))) . '/templates/layouts/admin.php';
