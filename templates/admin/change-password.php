<?php
/**
 * Trocar senha. Modos:
 *   - $forced=true  → veio de reset por admin, não pede senha atual
 *   - $forced=false → mudança voluntária, exige senha atual
 *
 * Outras vars opcionais:
 *   $errors  → list<string> (chaves de I18n já traduzidas pra exibir)
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Auth\PasswordPolicy;

$forced = $forced ?? false;
$errors = $errors ?? [];

ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top:48px;padding-bottom:48px">
    <div class="af-text-c">
        <?php $logoSize = 96; require dirname(__DIR__) . '/components/logo.php'; ?>
    </div>

    <div class="af-divider af-mt-6 af-mb-4"><span>◆</span></div>

    <div class="af-fs-10 af-track-4 af-gold af-text-c af-mb-3">
        <?= e(t('admin.change_password.kicker')) ?>
    </div>
    <div class="af-editorial af-w-500 af-text-c" style="font-size:28px;line-height:1.2;margin-bottom:14px">
        <?= e(t('admin.change_password.title')) ?>
    </div>
    <div class="af-fs-12 af-soft af-text-c af-mb-6" style="line-height:1.6">
        <?= $forced
            ? t('admin.change_password.forced_reason')
            : t('admin.change_password.voluntary_reason') /* contém HTML */ ?>
    </div>

    <?php if ($errors !== []): ?>
        <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px">
            <?php foreach ($errors as $err): ?>
                <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/change-password" class="af-flex-col af-gap-4">
        <?= Session::csrfField() ?>

        <?php if (!$forced): ?>
            <div>
                <label class="af-label" for="cp-current"><?= e(t('admin.change_password.current_label')) ?></label>
                <input id="cp-current" name="current_password" type="password" class="af-input"
                       autocomplete="current-password" required>
            </div>
        <?php endif; ?>

        <div>
            <label class="af-label" for="cp-new"><?= e(t('admin.change_password.new_label')) ?></label>
            <input id="cp-new" name="new_password" type="password" class="af-input"
                   autocomplete="new-password" required>
            <div class="af-fs-10 af-mute af-mt-2" style="line-height:1.6">
                <?php foreach (PasswordPolicy::requirements() as $req): ?>
                    · <?= e(t($req)) ?><br>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <label class="af-label" for="cp-confirm"><?= e(t('admin.change_password.confirm_label')) ?></label>
            <input id="cp-confirm" name="confirm_password" type="password" class="af-input"
                   autocomplete="new-password" required>
        </div>

        <button type="submit" class="af-btn af-mt-3">
            <?= e(mb_strtoupper(t('admin.change_password.submit'))) ?> →
        </button>
    </form>
</main>
<?php
$bodyContent = ob_get_clean();
$title = t('admin.change_password.page_title');
require dirname(__DIR__) . '/layout.php';
