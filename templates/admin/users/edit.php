<?php
/**
 * Form de edição de curador. Admin only.
 *
 * Vars esperadas:
 *   $user    → User sendo editado
 *   $errors  → list<string> (opcional)
 *   $currentUser → user logado (admin)
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Auth\User as UserModel;

/** @var \ArkhamFiles\Auth\User $user */
$user        = $user;
$errors      = $errors ?? [];
/** @var \ArkhamFiles\Auth\User $currentUser */
$currentUser = $currentUser ?? \ArkhamFiles\Auth\Auth::currentUser();
$isSelf      = $currentUser && $currentUser->id === $user->id;

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.users.edit_page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1">/ <?= e($user->username) ?></div>
</div>

<div class="af-divider af-mb-4" style="max-width:280px;margin:0 0 18px 0"><span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.users.edit_kicker')) ?></span></div>

<?php if ($errors !== []): ?>
    <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px;max-width:540px">
        <?php foreach ($errors as $err): ?>
            <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="/admin/users/<?= e((string)$user->id) ?>/edit" class="af-flex-col af-gap-4" style="max-width:540px">
    <?= Session::csrfField() ?>

    <div>
        <label class="af-label"><?= e(t('admin.users.form_username')) ?></label>
        <input type="text" class="af-input" value="<?= e($user->username) ?>" disabled>
        <div class="af-fs-10 af-mute af-mt-1">A identificação não pode ser alterada após criação.</div>
    </div>

    <div>
        <label class="af-label" for="u-email"><?= e(t('admin.users.form_email')) ?></label>
        <input id="u-email" name="email" type="email" class="af-input"
               value="<?= e($user->email ?? '') ?>">
    </div>

    <div>
        <label class="af-label"><?= e(t('admin.users.form_role')) ?></label>
        <div class="af-flex af-gap-2">
            <label class="af-radio-card <?= $user->isCurator() ? 'af-radio-card--selected' : '' ?>">
                <input type="radio" name="role" value="<?= e(UserModel::ROLE_CURATOR) ?>"
                       style="display:none" <?= $user->isCurator() ? 'checked' : '' ?>
                       <?= $isSelf ? 'disabled' : '' ?>>
                <span class="af-radio-card__dot"></span>
                <?= e(mb_strtoupper(t('admin.user.role_curator'))) ?>
            </label>
            <label class="af-radio-card <?= $user->isAdmin() ? 'af-radio-card--selected' : '' ?>">
                <input type="radio" name="role" value="<?= e(UserModel::ROLE_ADMIN) ?>"
                       style="display:none" <?= $user->isAdmin() ? 'checked' : '' ?>
                       <?= $isSelf ? 'disabled' : '' ?>>
                <span class="af-radio-card__dot"></span>
                <?= e(mb_strtoupper(t('admin.user.role_admin'))) ?>
            </label>
        </div>
        <?php if ($isSelf): ?>
            <div class="af-fs-10 af-mute af-mt-2">⚠ Você não pode alterar seu próprio papel.</div>
        <?php endif; ?>
    </div>

    <div class="af-flex af-gap-3 af-mt-3">
        <button type="submit" class="af-btn af-btn--primary af-btn--sm">
            <?= e(mb_strtoupper(t('common.save'))) ?> →
        </button>
        <a href="/admin/users" class="af-btn af-btn--ghost af-btn--sm">
            <?= e(mb_strtoupper(t('common.cancel'))) ?>
        </a>
    </div>
</form>

<div class="af-mt-6" style="max-width:540px">
    <a href="/admin/users/<?= e((string)$user->id) ?>/reset-password" class="af-btn af-btn--ghost af-btn--sm">
        <?= icon('lock', 'af-icon--sm af-gold') ?>
        <?= e(mb_strtoupper(t('admin.users.action_reset'))) ?>
    </a>
</div>

<script>
document.querySelectorAll('.af-radio-card input[type=radio]').forEach(function (input) {
    input.addEventListener('change', function () {
        document.querySelectorAll('.af-radio-card').forEach(function (card) {
            card.classList.remove('af-radio-card--selected');
        });
        this.closest('.af-radio-card').classList.add('af-radio-card--selected');
    });
});
</script>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.users.edit_page_title');
require dirname(dirname(__DIR__)) . '/layouts/admin.php';
