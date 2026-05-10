<?php
/**
 * Form de novo curador. Admin only.
 *
 * Vars opcionais:
 *   $errors  → list<string>
 *   $oldUsername, $oldEmail, $oldRole → repreencher campos após erro
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Auth\User;

$errors      = $errors      ?? [];
$oldUsername = $oldUsername ?? '';
$oldEmail    = $oldEmail    ?? '';
$oldRole     = $oldRole     ?? User::ROLE_CURATOR;

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.users.new_page_title'))) ?></div>
</div>

<div class="af-divider af-mb-4" style="max-width:280px;margin:0 0 18px 0"><span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.users.new_kicker')) ?></span></div>

<p class="af-fs-12 af-soft af-mb-4" style="max-width:540px"><?= e(t('admin.users.new_help')) ?></p>

<?php if ($errors !== []): ?>
    <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px;max-width:540px">
        <?php foreach ($errors as $err): ?>
            <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="/admin/users/new" class="af-flex-col af-gap-4" style="max-width:540px">
    <?= Session::csrfField() ?>

    <div>
        <label class="af-label" for="u-username"><?= e(t('admin.users.form_username')) ?></label>
        <input id="u-username" name="username" type="text" class="af-input"
               value="<?= e($oldUsername) ?>" required pattern="[a-zA-Z0-9._-]+" autofocus>
        <div class="af-fs-10 af-mute af-mt-1"><?= e(t('admin.users.form_username_help')) ?></div>
    </div>

    <div>
        <label class="af-label" for="u-email"><?= e(t('admin.users.form_email')) ?></label>
        <input id="u-email" name="email" type="email" class="af-input"
               value="<?= e($oldEmail) ?>">
    </div>

    <div>
        <label class="af-label"><?= e(t('admin.users.form_role')) ?></label>
        <div class="af-flex af-gap-2">
            <label class="af-radio-card <?= $oldRole === User::ROLE_CURATOR ? 'af-radio-card--selected' : '' ?>">
                <input type="radio" name="role" value="<?= e(User::ROLE_CURATOR) ?>"
                       style="display:none" <?= $oldRole === User::ROLE_CURATOR ? 'checked' : '' ?>>
                <span class="af-radio-card__dot"></span>
                <?= e(mb_strtoupper(t('admin.user.role_curator'))) ?>
            </label>
            <label class="af-radio-card <?= $oldRole === User::ROLE_ADMIN ? 'af-radio-card--selected' : '' ?>">
                <input type="radio" name="role" value="<?= e(User::ROLE_ADMIN) ?>"
                       style="display:none" <?= $oldRole === User::ROLE_ADMIN ? 'checked' : '' ?>>
                <span class="af-radio-card__dot"></span>
                <?= e(mb_strtoupper(t('admin.user.role_admin'))) ?>
            </label>
        </div>
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

<script>
// Visual selection de radio cards (sem JS framework — vanilla simples)
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
$title = t('admin.users.new_page_title');
require dirname(dirname(__DIR__)) . '/layouts/admin.php';
