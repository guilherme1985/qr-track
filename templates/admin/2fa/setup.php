<?php
/**
 * Setup inicial de 2FA. Mostra o QR code, chave manual e form de confirmação.
 *
 * Vars esperadas:
 *   $qrSvg       → string SVG do QR code (já renderizado)
 *   $manualKey   → string base32 do segredo (mostrada formatada)
 *   $errors      → list<string> (opcional)
 *   $currentUser → User logado
 */
use ArkhamFiles\Auth\Session;

$errors = $errors ?? [];

// Formata a chave manual em grupos de 4 chars pra facilitar digitação
$manualKeyFormatted = trim(chunk_split($manualKey, 4, ' '));

ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top:32px;padding-bottom:48px;max-width:560px">
    <div class="af-text-c">
        <?php $logoSize = 96; require dirname(dirname(__DIR__)) . '/components/logo.php'; ?>
    </div>

    <div class="af-divider af-mt-6 af-mb-4"><span>◆</span></div>

    <div class="af-fs-10 af-track-4 af-gold af-text-c af-mb-3">
        <?= e(t('admin.two_factor_setup.security_kicker')) ?>
    </div>
    <div class="af-editorial af-w-500 af-text-c" style="font-size:28px;line-height:1.2;margin-bottom:14px">
        <?= e(t('admin.two_factor_setup.title')) ?>
    </div>
    <div class="af-fs-12 af-soft af-text-c af-mb-6" style="line-height:1.6">
        <?= e(t('admin.two_factor_setup.help')) ?>
    </div>

    <?php if ($errors !== []): ?>
        <div style="padding:12px 14px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.08);margin-bottom:18px">
            <?php foreach ($errors as $err): ?>
                <div class="af-fs-11 af-blood af-track-1">⚠ <?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Etapa 1: QR code -->
    <div class="af-panel af-mb-4">
        <div class="af-fs-10 af-track-4 af-phosphor af-mb-3">
            <?= e(mb_strtoupper(t('admin.two_factor_setup.step_1'))) ?>
        </div>
        <p class="af-fs-12 af-soft af-mb-4"><?= e(t('admin.two_factor_setup.step_1_help')) ?></p>

        <div class="af-text-c" style="padding:16px;background:#fff;border:0.5px solid var(--af-border);margin-bottom:14px">
            <?= $qrSvg /* SVG inline gerado pelo TwoFactor::qrCodeSvg */ ?>
        </div>

        <div class="af-label"><?= e(t('admin.two_factor_setup.manual_key')) ?></div>
        <div style="background:var(--af-bg);border:0.5px solid var(--af-border);padding:12px;font-family:var(--af-font-mono);font-size:13px;letter-spacing:1px;color:var(--af-phosphor);text-align:center;user-select:all;word-break:break-all">
            <?= e($manualKeyFormatted) ?>
        </div>
        <div class="af-fs-10 af-mute af-mt-2 af-text-c"><?= e(t('admin.two_factor_setup.manual_key_help')) ?></div>
    </div>

    <!-- Etapa 2: confirmar com código -->
    <div class="af-panel af-mb-4">
        <div class="af-fs-10 af-track-4 af-phosphor af-mb-3">
            <?= e(mb_strtoupper(t('admin.two_factor_setup.step_2'))) ?>
        </div>
        <p class="af-fs-12 af-soft af-mb-4"><?= e(t('admin.two_factor_setup.step_2_help')) ?></p>

        <form method="post" action="/admin/2fa/setup">
            <?= Session::csrfField() ?>
            <input type="text" name="code" class="af-input"
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   placeholder="000000"
                   style="text-align:center;font-size:24px;letter-spacing:8px;font-family:var(--af-font-mono)"
                   autocomplete="one-time-code" autofocus required>

            <button type="submit" class="af-btn af-btn--primary af-mt-4" style="width:100%">
                <?= e(mb_strtoupper(t('admin.two_factor_setup.submit'))) ?> →
            </button>
        </form>
    </div>

    <div class="af-fs-10 af-track-2 af-gold af-text-c">
        <?= e(t('admin.two_factor_setup.warning')) ?>
    </div>

    <div class="af-text-c af-mt-6">
        <div class="af-fs-9 af-track-3 af-blood"><?= e(t('admin.two_factor_setup.restricted')) ?></div>
    </div>
</main>
<?php
$bodyContent = ob_get_clean();
$title = t('admin.two_factor_setup.page_title');
require dirname(dirname(dirname(__DIR__))) . '/templates/layout.php';
