<?php
/**
 * Painel de toggle do modo manutenção.
 *
 * Vars esperadas:
 *   $isActive          → bool
 *   $currentMessage    → string  (mensagem atual no arquivo)
 *   $flashMessage      → string|null  (feedback após ação)
 */
use ArkhamFiles\Auth\Session;
use ArkhamFiles\Maintenance;

$isActive = $isActive ?? false;
$currentMessage = $currentMessage ?? '';
$flashMessage = $flashMessage ?? null;

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title">
        <?= e(mb_strtoupper(t('admin.maintenance.page_title'))) ?>
    </div>
    <div class="af-fs-10 af-mute af-track-1">
        <?php if ($isActive): ?>
            <span class="af-blood">● <?= e(mb_strtoupper(t('admin.maintenance.status_active'))) ?></span>
        <?php else: ?>
            <span class="af-phosphor">● <?= e(mb_strtoupper(t('admin.maintenance.status_inactive'))) ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="af-divider af-mb-4" style="max-width:300px;margin:0 0 18px 0">
    <span class="af-gold af-fs-9 af-track-3">━━ <?= e(t('admin.maintenance.kicker')) ?> ━━</span>
</div>

<?php if ($flashMessage): ?>
    <div style="padding:12px 14px;border:0.5px solid var(--af-phosphor);background:rgba(125,219,79,0.06);margin-bottom:18px;max-width:680px">
        <div class="af-fs-11 af-phosphor af-track-1">✓ <?= e($flashMessage) ?></div>
    </div>
<?php endif; ?>

<p class="af-fs-12 af-soft af-mb-4" style="max-width:680px;line-height:1.7">
    <?= e(t('admin.maintenance.help')) ?>
</p>

<?php if ($isActive): ?>
    <!-- Modo ATIVO — mostra status + form pra desligar -->
    <div class="af-panel af-mb-4" style="max-width:680px;border-color:var(--af-blood);background:rgba(92,26,27,0.05)">
        <div class="af-editorial af-w-500 af-blood af-mb-3" style="font-size:22px;line-height:1.2">
            ⚙ <?= e(t('admin.maintenance.active_title')) ?>
        </div>
        <p class="af-fs-12 af-soft" style="line-height:1.7;margin-bottom:14px">
            <?= e(t('admin.maintenance.active_body')) ?>
        </p>

        <div class="af-fs-10 af-track-3 af-gold af-mb-2">
            ━━ <?= e(t('admin.maintenance.current_message_label')) ?> ━━
        </div>
        <div style="padding:12px 14px;border:0.5px solid var(--af-border);background:rgba(0,0,0,0.25);font-family:var(--af-font-mono);font-size:12px;line-height:1.6;color:var(--af-soft);margin-bottom:14px">
            <?= e($currentMessage) ?>
        </div>
    </div>

    <form method="post" action="/admin/settings/maintenance/disable" style="max-width:680px">
        <?= Session::csrfField() ?>
        <button type="submit" class="af-btn af-btn--primary af-btn--sm">
            <?= e(mb_strtoupper(t('admin.maintenance.disable_btn'))) ?>
        </button>
    </form>

<?php else: ?>
    <!-- Modo INATIVO — form pra ligar com mensagem -->
    <form method="post" action="/admin/settings/maintenance/enable" class="af-flex-col af-gap-4" style="max-width:680px">
        <?= Session::csrfField() ?>

        <div>
            <label class="af-label" for="m-msg">
                <?= e(t('admin.maintenance.message_label')) ?>
            </label>
            <textarea id="m-msg" name="message" rows="3" class="af-input af-mono"
                      placeholder="<?= e(t('admin.maintenance.message_placeholder')) ?>"
                      maxlength="500"
                      style="resize:vertical;line-height:1.6"></textarea>
            <div class="af-fs-10 af-mute af-mt-1">
                <?= e(t('admin.maintenance.message_help')) ?>
            </div>
        </div>

        <div>
            <button type="submit" class="af-btn af-btn--sm"
                    style="background:var(--af-blood);color:var(--af-text);border-color:var(--af-blood)">
                ⚙ <?= e(mb_strtoupper(t('admin.maintenance.enable_btn'))) ?>
            </button>
        </div>
    </form>
<?php endif; ?>

<div class="af-fs-10 af-faint af-mt-6" style="max-width:680px;line-height:1.7">
    <em><?= e(t('admin.maintenance.shell_note')) ?></em>
    <div class="af-mono af-fs-10 af-mt-2" style="padding:8px 12px;background:rgba(0,0,0,0.3);color:var(--af-phosphor)">
        echo "Mensagem" &gt; <?= e(\ArkhamFiles\Maintenance::flagPath()) ?>
        <br>
        rm <?= e(\ArkhamFiles\Maintenance::flagPath()) ?>
    </div>
</div>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = t('admin.maintenance.page_title');
require dirname(__DIR__, 3) . '/templates/layouts/admin.php';
