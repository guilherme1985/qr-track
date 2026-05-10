<?php
/**
 * Reset de senha pelo admin. Tem dois estados:
 *
 *   1. Confirmação (GET):
 *      - $user é exibido
 *      - $temporaryPassword é null
 *      - Form de confirmação POST
 *
 *   2. Exibição da senha (POST after success):
 *      - $user é exibido
 *      - $temporaryPassword é a senha gerada (mostrada uma vez)
 */
use ArkhamFiles\Auth\Session;

/** @var \ArkhamFiles\Auth\User $user */
$user = $user;
$temporaryPassword = $temporaryPassword ?? null;

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.users.reset_page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1">/ <?= e($user->username) ?></div>
</div>

<div class="af-divider af-mb-4" style="max-width:280px;margin:0 0 18px 0"><span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.users.reset_kicker')) ?></span></div>

<?php if ($temporaryPassword === null): ?>
    <!-- Estado 1: confirmação -->
    <div class="af-panel af-mb-4" style="max-width:540px">
        <div class="af-fs-12 af-blood af-track-2 af-mb-3">⚠ <?= e(t('admin.users.reset_confirm_title')) ?></div>
        <p class="af-fs-12 af-soft" style="line-height:1.7">
            <?= t('admin.users.reset_confirm_body', ['user' => e($user->username)]) /* contém HTML */ ?>
        </p>
        <ul style="margin-top:12px;padding-left:20px;font-size:11px;line-height:1.8;color:var(--af-text-soft)">
            <li>· <?= e(t('admin.users.reset_consequence_1')) ?></li>
            <li>· <?= e(t('admin.users.reset_consequence_2')) ?></li>
            <li>· <?= e(t('admin.users.reset_consequence_3')) ?></li>
        </ul>
    </div>

    <form method="post" action="/admin/users/<?= e((string)$user->id) ?>/reset-password" class="af-flex af-gap-3">
        <?= Session::csrfField() ?>
        <button type="submit" class="af-btn af-btn--danger af-btn--sm">
            <?= icon('lock', 'af-icon--sm') ?>
            <?= e(mb_strtoupper(t('admin.users.reset_btn'))) ?>
        </button>
        <a href="/admin/users/<?= e((string)$user->id) ?>/edit" class="af-btn af-btn--ghost af-btn--sm">
            <?= e(mb_strtoupper(t('common.cancel'))) ?>
        </a>
    </form>

<?php else: ?>
    <!-- Estado 2: senha gerada -->
    <div class="af-panel af-mb-4" style="max-width:540px;border-color:var(--af-gold)">
        <div class="af-fs-12 af-gold af-track-2 af-mb-3">✓ <?= e(t('admin.users.reset_done_title')) ?></div>
        <p class="af-fs-12 af-soft af-mb-4" style="line-height:1.7">
            <?= t('admin.users.reset_done_help') /* contém HTML */ ?>
        </p>

        <div class="af-label"><?= e(t('admin.users.reset_done_field')) ?></div>
        <div style="background:var(--af-bg);border:0.5px solid var(--af-gold);padding:14px;font-family:var(--af-font-mono);font-size:18px;letter-spacing:2px;color:var(--af-phosphor);text-align:center;user-select:all">
            <?= e($temporaryPassword) ?>
        </div>
        <div class="af-fs-10 af-mute af-mt-2 af-text-c">↑ clique pra selecionar tudo</div>
    </div>

    <a href="/admin/users" class="af-btn af-btn--ghost af-btn--sm">
        ← <?= e(mb_strtoupper(t('admin.users.reset_done_back'))) ?>
    </a>
<?php endif; ?>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.users.reset_page_title');
require dirname(dirname(__DIR__)) . '/layouts/admin.php';
