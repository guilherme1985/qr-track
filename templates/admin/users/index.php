<?php
/**
 * Listagem de curadores. Admin only.
 *
 * Vars esperadas:
 *   $users        → User[]
 *   $currentUser  → user logado (não pode se desabilitar)
 *   $flashMessage → opcional
 */
use ArkhamFiles\Auth\User;

/** @var User[] $users */
$users = $users ?? [];
/** @var User $currentUser */
$currentUser = $currentUser ?? \ArkhamFiles\Auth\Auth::currentUser();
$flashMessage = $flashMessage ?? null;

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.users.page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1"><?= e((string) count($users)) ?> registros</div>
</div>

<div class="af-divider af-mb-6" style="max-width:280px;margin:0 0 24px 0"><span class="af-gold af-fs-9 af-track-3"><?= e(t('admin.users.kicker')) ?></span></div>

<div class="af-mb-4">
    <a href="/admin/users/new" class="af-btn af-btn--primary af-btn--sm">
        <?= e(mb_strtoupper(t('admin.users.btn_new'))) ?>
    </a>
</div>

<table class="af-table">
    <thead>
        <tr>
            <th><?= e(mb_strtoupper(t('admin.users.col_username'))) ?></th>
            <th><?= e(mb_strtoupper(t('admin.users.col_email'))) ?></th>
            <th><?= e(mb_strtoupper(t('admin.users.col_role'))) ?></th>
            <th><?= e(mb_strtoupper(t('admin.users.col_status'))) ?></th>
            <th><?= e(mb_strtoupper(t('admin.users.col_last_login'))) ?></th>
            <th class="af-text-r"><?= e(mb_strtoupper(t('admin.users.col_actions'))) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td class="af-mono af-w-500"><?= e($u->username) ?></td>
                <td class="af-fs-12 af-soft">
                    <?= $u->email ? e($u->email) : '<span class="af-faint">' . e(t('admin.users.no_email')) . '</span>' ?>
                </td>
                <td>
                    <span class="af-badge <?= $u->isAdmin() ? 'af-badge--gold' : 'af-badge--phosphor' ?>">
                        <?= e(mb_strtoupper($u->roleLabel())) ?>
                    </span>
                </td>
                <td>
                    <?php if ($u->isDisabled()): ?>
                        <span class="af-badge af-badge--blood"><?= e(mb_strtoupper(t('common.status_disabled'))) ?></span>
                    <?php else: ?>
                        <span class="af-status af-phosphor"><?= e(mb_strtoupper(t('common.status_active'))) ?></span>
                    <?php endif; ?>
                </td>
                <td class="af-fs-11 af-mute">
                    <?= $u->lastLoginAt ? e($u->lastLoginAt) : '<span class="af-faint">' . e(t('admin.users.never_logged')) . '</span>' ?>
                </td>
                <td class="af-text-r" style="white-space:nowrap">
                    <a href="/admin/users/<?= e((string)$u->id) ?>/edit" class="af-fs-10 af-track-1 af-mute" style="margin-right:14px"><?= e(mb_strtoupper(t('admin.users.action_edit'))) ?></a>
                    <a href="/admin/users/<?= e((string)$u->id) ?>/reset-password" class="af-fs-10 af-track-1 af-gold" style="margin-right:14px"><?= e(mb_strtoupper(t('admin.users.action_reset'))) ?></a>
                    <?php if ($u->id !== $currentUser->id): ?>
                        <?php if ($u->isDisabled()): ?>
                            <form method="post" action="/admin/users/<?= e((string)$u->id) ?>/enable" style="display:inline">
                                <?= \ArkhamFiles\Auth\Session::csrfField() ?>
                                <button type="submit" class="af-fs-10 af-track-1 af-phosphor"
                                        style="background:transparent;border:0;cursor:pointer;font:inherit;padding:0">
                                    <?= e(mb_strtoupper(t('admin.users.action_enable'))) ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="/admin/users/<?= e((string)$u->id) ?>/disable" style="display:inline"
                                  onsubmit="return confirm('<?= e(t('common.confirm_delete')) ?>')">
                                <?= \ArkhamFiles\Auth\Session::csrfField() ?>
                                <button type="submit" class="af-fs-10 af-track-1 af-blood"
                                        style="background:transparent;border:0;cursor:pointer;font:inherit;padding:0">
                                    <?= e(mb_strtoupper(t('admin.users.action_disable'))) ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.users.page_title');
require dirname(dirname(__DIR__)) . '/layouts/admin.php';
