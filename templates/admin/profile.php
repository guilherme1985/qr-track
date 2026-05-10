<?php
/**
 * Perfil do user logado. Mostra dados + link pra trocar senha.
 *
 * Vars esperadas:
 *   $currentUser → ArkhamFiles\Auth\User
 */
use ArkhamFiles\Auth\Auth;

/** @var \ArkhamFiles\Auth\User $currentUser */
$currentUser = $currentUser ?? Auth::currentUser();
if ($currentUser === null) {
    return;
}

$flashMessage = $flashMessage ?? null;

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.profile.page_title'))) ?></div>
</div>

<div class="af-divider af-mb-6" style="max-width:240px;margin:0 0 24px 0"><span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.profile.kicker')) ?></span></div>

<div class="af-panel af-mb-4" style="max-width:560px">
    <table class="af-table" style="border:none">
        <tbody>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0;width:180px;vertical-align:top">
                    <?= e(t('admin.profile.username_label')) ?>
                </td>
                <td class="af-mono af-w-500"><?= e($currentUser->username) ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                    <?= e(t('admin.profile.email_label')) ?>
                </td>
                <td class="af-mono">
                    <?php if ($currentUser->email): ?>
                        <?= e($currentUser->email) ?>
                    <?php else: ?>
                        <span class="af-faint"><?= e(t('admin.profile.no_email')) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                    <?= e(t('admin.profile.role_label')) ?>
                </td>
                <td>
                    <span class="af-badge <?= $currentUser->isAdmin() ? 'af-badge--gold' : 'af-badge--phosphor' ?>">
                        <?= e(mb_strtoupper($currentUser->roleLabel())) ?>
                    </span>
                </td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                    <?= e(t('admin.profile.created_label')) ?>
                </td>
                <td class="af-mono"><?= e($currentUser->createdAt) ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                    <?= e(t('admin.profile.last_login_label')) ?>
                </td>
                <td class="af-mono">
                    <?php if ($currentUser->lastLoginAt): ?>
                        <?= e($currentUser->lastLoginAt) ?>
                    <?php else: ?>
                        <span class="af-faint"><?= e(t('admin.profile.no_login_yet')) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div style="max-width:560px">
    <a href="/admin/change-password" class="af-btn af-btn--ghost af-btn--sm">
        <?= icon('lock', 'af-icon--sm') ?>
        <?= e(mb_strtoupper(t('admin.profile.change_password'))) ?>
    </a>
</div>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.profile.page_title');
require dirname(__DIR__) . '/layouts/admin.php';
