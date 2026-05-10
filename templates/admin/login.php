<?php
/**
 * Tela de login.
 *
 * Variáveis opcionais (vêm do handler em caso de submit com erro):
 *   $errorMessage  → string já traduzida pra exibir no topo
 *   $oldUsername   → username submetido (pra repreencher campo)
 */
use ArkhamFiles\Auth\Session;

$errorMessage = $errorMessage ?? null;
$oldUsername  = $oldUsername  ?? '';

ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top:48px;padding-bottom:48px">
    <div class="af-text-c">
        <?php $logoSize = 120; require dirname(__DIR__) . '/components/logo.php'; ?>
    </div>

    <div class="af-text-c af-mt-6">
        <div class="af-display af-phosphor af-fs-24 af-w-500 af-track-5">
            <?= e(mb_strtoupper(t('common.app_name'))) ?>
        </div>
        <div class="af-fs-9 af-track-5 af-mute af-mt-2">
            ━━━ <?= e(mb_strtoupper(t('common.app_subtitle'))) ?> ━━━
        </div>
    </div>

    <div style="border-top:0.5px solid var(--af-border-soft);border-bottom:0.5px solid var(--af-border-soft);padding:12px 0;text-align:center;margin:32px 0;">
        <div class="af-fs-10 af-track-4 af-blood">▲ <?= e(t('admin.login.restricted_area')) ?> ▲</div>
        <div class="af-fs-9 af-track-3 af-mute af-mt-1"><?= e(t('admin.login.authorized_only')) ?></div>
    </div>

    <?php if ($errorMessage): ?>
        <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px">
            <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($errorMessage) ?></div>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/login" class="af-flex-col af-gap-5">
        <?= Session::csrfField() ?>
        <div>
            <label class="af-label" for="login-user"><?= e(t('admin.login.username_label')) ?></label>
            <input id="login-user" name="username" type="text" class="af-input"
                   value="<?= e($oldUsername) ?>" autocomplete="username" autofocus required>
        </div>
        <div>
            <label class="af-label" for="login-pass"><?= e(t('admin.login.password_label')) ?></label>
            <input id="login-pass" name="password" type="password" class="af-input"
                   autocomplete="current-password" required>
        </div>
        <button type="submit" class="af-btn af-mt-3">
            <?= e(mb_strtoupper(t('admin.login.submit'))) ?> →
        </button>
    </form>

    <div class="af-text-c af-mt-4">
        <a href="/admin/forgot-password" class="af-fs-10 af-mute af-track-1" style="text-decoration:underline">
            <?= e(t('admin.login.forgot')) ?>
        </a>
    </div>

    <div class="af-text-c" style="margin-top:60px;padding-top:22px;border-top:0.5px solid var(--af-border)">
        <div class="af-fs-9 af-track-3 af-mute"><?= e(mb_strtoupper(t('admin.login.logs_retention'))) ?></div>
        <div class="af-fs-8 af-track-2 af-faint af-mt-2">
            <?= e(mb_strtoupper(t('common.app_name'))) ?> v1.2.0 · <?= e(mb_strtoupper(t('admin.login.build_tag'))) ?>
        </div>
    </div>
</main>
<?php
$bodyContent = ob_get_clean();
$title = t('admin.login.page_title');
require dirname(__DIR__) . '/layout.php';
