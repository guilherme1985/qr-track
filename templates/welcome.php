<?php
/**
 * Landing pública na raiz `/`.
 *
 * Tom: institucional, minimalista, sem revelar mecânica.
 * Não expõe link de login (privacidade — quem precisa, sabe o caminho).
 * Não tem ads, formulário, ou pegadinhas.
 */

ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top:120px;padding-bottom:120px;text-align:center;min-height:80vh;display:flex;flex-direction:column;justify-content:center">
    <!-- Logo grande -->
    <div style="margin-bottom:40px">
        <?php $logoSize = 140; require __DIR__ . '/components/logo.php'; ?>
    </div>

    <div class="af-divider af-mb-6"><span>◆</span></div>

    <div class="af-fs-10 af-track-4 af-gold af-mb-3">
        ━━ <?= e(t('welcome.kicker')) ?> ━━
    </div>

    <!-- Título principal -->
    <h1 class="af-display af-w-500" style="font-size:56px;line-height:1.1;margin:0 0 14px 0;letter-spacing:-0.01em">
        <?= e(t('common.app_name')) ?>
    </h1>

    <div class="af-mono af-soft af-track-3 af-fs-12 af-mb-2">
        <?= e(mb_strtoupper(t('common.app_subtitle'))) ?>
    </div>

    <div class="af-mono af-faint af-track-2 af-fs-10 af-mb-8">
        <?= e(t('welcome.department_line')) ?>
    </div>

    <!-- Bloco institucional -->
    <div class="af-panel" style="max-width:560px;margin:0 auto 32px auto;text-align:left;background:rgba(168,139,76,0.03);border-color:var(--af-gold)">
        <div class="af-fs-10 af-track-3 af-gold af-mb-3 af-text-c">
            ━━ <?= e(t('welcome.notice_label')) ?> ━━
        </div>
        <p class="af-fs-12 af-soft" style="line-height:1.8">
            <?= e(t('welcome.body_p1')) ?>
        </p>
        <p class="af-fs-12 af-soft" style="line-height:1.8;margin-top:14px">
            <?= e(t('welcome.body_p2')) ?>
        </p>
    </div>

    <!-- Sello/badge -->
    <div class="af-fs-9 af-track-3 af-blood af-mb-8" style="display:inline-block;padding:8px 18px;border:0.5px solid var(--af-blood);background:rgba(92,26,27,0.04)">
        ▲ <?= e(mb_strtoupper(t('welcome.access_label'))) ?> ▲
    </div>

    <div class="af-divider af-mt-3"><span>◆</span></div>

    <div class="af-fs-9 af-track-2 af-mute" style="margin-top:24px">
        <?= e(mb_strtoupper(t('common.app_name'))) ?> · <?= e(mb_strtoupper(t('welcome.footer_line'))) ?>
    </div>
</main>
<?php
$bodyContent = ob_get_clean();
$title = t('common.app_name');
require __DIR__ . '/layout.php';
