<?php
/**
 * Confirmação de exclusão permanente de um curador.
 *
 * Vars esperadas:
 *   $user     → User a ser excluído
 *   $errors   → list<string> (opcional)
 *   $stats    → array com contagens (archives, scans, audit_events)
 *   $currentUser → User logado (admin)
 */
use ArkhamFiles\Auth\Session;

/** @var \ArkhamFiles\Auth\User $user */
$user = $user;
$errors = $errors ?? [];
$stats  = $stats  ?? ['archives' => 0, 'scans' => 0, 'audit_events' => 0];

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title"><?= e(mb_strtoupper(t('admin.users.delete_page_title'))) ?></div>
    <div class="af-fs-10 af-mute af-track-1">/ <?= e($user->username) ?></div>
</div>

<div class="af-divider af-mb-4" style="max-width:280px;margin:0 0 20px 0">
    <span class="af-blood af-fs-9 af-track-3"><?= e(t('admin.users.delete_kicker')) ?></span>
</div>

<?php if ($errors !== []): ?>
    <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px;max-width:560px">
        <?php foreach ($errors as $err): ?>
            <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="af-panel af-mb-4" style="max-width:560px;border-color:var(--af-blood)">
    <div class="af-editorial af-w-500 af-blood af-mb-3" style="font-size:22px;line-height:1.2">
        ⚠ <?= e(t('admin.users.delete_title')) ?>
    </div>
    <p class="af-fs-12 af-soft" style="line-height:1.7">
        <?= t('admin.users.delete_body', ['user' => e($user->username)]) /* HTML */ ?>
    </p>

    <div class="af-fs-10 af-track-3 af-mute af-mt-4 af-mb-2">
        <?= e(mb_strtoupper(t('admin.users.delete_summary'))) ?>
    </div>
    <table class="af-table" style="border:none;border-top:0.5px solid var(--af-border)">
        <tbody>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0;width:240px">
                    <?= e(t('admin.users.delete_qty_archives')) ?>
                </td>
                <td class="af-mono af-w-500"><?= e((string) $stats['archives']) ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0">
                    <?= e(t('admin.users.delete_qty_scans')) ?>
                </td>
                <td class="af-mono"><?= e((string) $stats['scans']) ?></td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0">
                    <?= e(t('admin.users.delete_qty_audit')) ?>
                </td>
                <td class="af-mono"><?= e((string) $stats['audit_events']) ?>
                    <span class="af-fs-9 af-faint" style="margin-left:8px">(serão preservados como anônimos)</span>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="af-fs-10 af-mute af-mt-4" style="line-height:1.6">
        <?= t('admin.users.delete_alt_disable', ['url' => '/admin/users']) /* HTML */ ?>
    </div>
</div>

<form method="post" action="/admin/users/<?= e((string) $user->id) ?>/delete" style="max-width:560px">
    <?= Session::csrfField() ?>

    <div class="af-label" style="line-height:1.6">
        <?= t('admin.users.delete_confirm_label', ['user' => '<span class="af-mono af-phosphor" style="text-transform:none">' . e($user->username) . '</span>']) /* HTML */ ?>
    </div>
    <input type="text" name="confirm_username" class="af-input"
           placeholder="<?= e(t('admin.users.delete_confirm_placeholder')) ?>"
           autocomplete="off" autocapitalize="none"
           style="font-family:var(--af-font-mono)"
           required autofocus>

    <div class="af-flex af-gap-3 af-mt-4">
        <button type="submit" class="af-btn af-btn--danger">
            <?= icon('trash', 'af-icon--sm') ?>
            <?= e(mb_strtoupper(t('admin.users.delete_btn'))) ?>
        </button>
        <a href="/admin/users" class="af-btn af-btn--ghost">
            <?= e(mb_strtoupper(t('admin.users.delete_btn_cancel'))) ?>
        </a>
    </div>
</form>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.users.delete_page_title');
require dirname(dirname(dirname(__DIR__))) . '/templates/layouts/admin.php';
