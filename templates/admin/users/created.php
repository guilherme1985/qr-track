<?php
/**
 * Mostrado uma única vez após criação de novo curador. Exibe a senha
 * temporária pra que o admin possa anotar e entregar ao novo usuário.
 *
 * Vars esperadas:
 *   $user              → User criado
 *   $temporaryPassword → senha em texto plano
 */
/** @var \ArkhamFiles\Auth\User $user */
$user = $user;
/** @var string $temporaryPassword */
$temporaryPassword = $temporaryPassword;

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.users.created_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1">/ <?= e($user->username) ?></div>
</div>

<div class="af-panel af-mb-4" style="max-width:540px;border-color:var(--af-phosphor)">
    <div class="af-fs-12 af-phosphor af-track-2 af-mb-3">✓ <?= e(mb_strtoupper(t('admin.users.created_title'))) ?></div>
    <p class="af-fs-12 af-soft af-mb-4" style="line-height:1.7">
        <?= t('admin.users.created_help') /* contém HTML */ ?>
    </p>

    <table class="af-table" style="border:none;margin-bottom:14px">
        <tbody>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0;width:160px"><?= e(t('admin.users.col_username')) ?></td>
                <td class="af-mono af-w-500"><?= e($user->username) ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0"><?= e(t('admin.users.col_email')) ?></td>
                <td class="af-mono"><?= $user->email ? e($user->email) : '—' ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0"><?= e(t('admin.users.col_role')) ?></td>
                <td>
                    <span class="af-badge <?= $user->isAdmin() ? 'af-badge--gold' : 'af-badge--phosphor' ?>">
                        <?= e(mb_strtoupper($user->roleLabel())) ?>
                    </span>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="af-label"><?= e(t('admin.users.reset_done_field')) ?></div>
    <div style="background:var(--af-bg);border:0.5px solid var(--af-phosphor);padding:14px;font-family:var(--af-font-mono);font-size:18px;letter-spacing:2px;color:var(--af-phosphor);text-align:center;user-select:all">
        <?= e($temporaryPassword) ?>
    </div>
    <div class="af-fs-10 af-mute af-mt-2 af-text-c">↑ clique pra selecionar tudo</div>
</div>

<a href="/admin/users" class="af-btn af-btn--ghost af-btn--sm">
    ← <?= e(mb_strtoupper(t('admin.users.reset_done_back'))) ?>
</a>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.users.created_title');
require dirname(dirname(__DIR__)) . '/layouts/admin.php';
