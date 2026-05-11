<?php
/**
 * Tela de verificação 2FA pós-login. Mostra um único input de 6 dígitos
 * com link discreto pra usar recovery code.
 *
 * Vars esperadas:
 *   $errors → list<string>
 *   $useRecovery → bool — se true, mostra form de recovery em vez do TOTP
 */
use ArkhamFiles\Auth\Session;

$errors      = $errors      ?? [];
$useRecovery = $useRecovery ?? false;

ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top:48px;padding-bottom:48px;max-width:480px">
    <div class="af-text-c">
        <?php $logoSize = 96; require dirname(dirname(__DIR__)) . '/components/logo.php'; ?>
    </div>

    <div class="af-text-c af-mt-4">
        <div class="af-display af-phosphor af-fs-20 af-track-5">
            <?= e(mb_strtoupper(t('common.app_name'))) ?>
        </div>
    </div>

    <div style="border-top:0.5px solid var(--af-border-soft);border-bottom:0.5px solid var(--af-border-soft);padding:12px 0;text-align:center;margin:28px 0;">
        <div class="af-fs-10 af-track-4 af-gold"><?= e(t('admin.two_factor.second_step')) ?></div>
    </div>

    <?php if ($errors !== []): ?>
        <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px">
            <?php foreach ($errors as $err): ?>
                <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$useRecovery): ?>
        <!-- Modo TOTP -->
        <div class="af-fs-12 af-track-2 af-mute af-text-c af-mb-3">
            <?= e(mb_strtoupper(t('admin.two_factor.token_title'))) ?>
        </div>
        <p class="af-fs-12 af-soft af-text-c af-mb-5" style="line-height:1.6">
            <?= e(t('admin.two_factor.token_help')) ?>
        </p>

        <form method="post" action="/admin/2fa/verify">
            <?= Session::csrfField() ?>
            <input type="text" name="code" class="af-input"
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   placeholder="000000"
                   style="text-align:center;font-size:28px;letter-spacing:10px;font-family:var(--af-font-mono);height:60px"
                   autocomplete="one-time-code" autofocus required>

            <button type="submit" class="af-btn af-mt-4" style="width:100%">
                <?= e(mb_strtoupper(t('admin.two_factor.submit'))) ?> →
            </button>
        </form>

        <div class="af-text-c af-mt-5">
            <a href="/admin/2fa/verify?recovery=1" class="af-fs-10 af-mute af-track-1" style="text-decoration:underline">
                <?= e(t('admin.two_factor.recovery_link')) ?>
            </a>
        </div>
    <?php else: ?>
        <!-- Modo Recovery code -->
        <div class="af-fs-12 af-track-2 af-gold af-text-c af-mb-3">
            <?= e(mb_strtoupper(t('admin.two_factor.recovery_title'))) ?>
        </div>
        <p class="af-fs-12 af-soft af-text-c af-mb-5" style="line-height:1.6">
            <?= t('admin.two_factor.recovery_help') /* contém HTML */ ?>
        </p>

        <form method="post" action="/admin/2fa/verify?recovery=1">
            <?= Session::csrfField() ?>
            <input type="hidden" name="mode" value="recovery">
            <input type="text" name="code" class="af-input"
                   placeholder="ABCDE-FGHIJ"
                   style="text-align:center;font-size:18px;letter-spacing:3px;font-family:var(--af-font-mono);text-transform:uppercase"
                   autocomplete="off" autofocus required>

            <button type="submit" class="af-btn af-btn--ghost af-mt-4" style="width:100%">
                <?= e(mb_strtoupper(t('admin.two_factor.recovery_submit'))) ?>
            </button>
        </form>

        <div class="af-text-c af-mt-5">
            <a href="/admin/2fa/verify" class="af-fs-10 af-mute af-track-1" style="text-decoration:underline">
                ← <?= e(t('admin.two_factor.recovery_back')) ?>
            </a>
        </div>
    <?php endif; ?>
</main>
<?php
$bodyContent = ob_get_clean();
$title = t('admin.two_factor.page_title');
require dirname(dirname(dirname(__DIR__))) . '/templates/layout.php';
