<?php
/**
 * Tela "viewer placeholder" — usada quando um QR válido é acessado mas o
 * viewer específico do tipo (note/strain/image/etc.) ainda não foi implementado.
 *
 * PRs 07/08/09 vão substituir esta tela por viewers temáticos por tipo.
 * Por enquanto, mantém o tom institucional do app.
 *
 * Vars esperadas:
 *   $qr → ArkhamFiles\QrCode (já validado: not expired, not disabled, not deleted)
 *   $scanCount → int (opcional — quantos scans esse QR já recebeu)
 */
use ArkhamFiles\QrCode;

/** @var QrCode $qr */
$qr = $qr;
$scanCount = $scanCount ?? 0;

[$expKey, $expParams] = $qr->expirationLabel();

ob_start();
?>
<main class="af-container af-container--narrow" style="padding-top:48px;padding-bottom:48px;max-width:640px">
    <div class="af-text-c">
        <?php $logoSize = 96; require dirname(__DIR__) . '/components/logo.php'; ?>
    </div>

    <div class="af-divider af-mt-6 af-mb-4"><span>◆</span></div>

    <div class="af-fs-10 af-track-4 af-gold af-text-c af-mb-3">
        <?= e(t('public.placeholder.kicker')) ?>
    </div>
    <div class="af-editorial af-w-500 af-text-c" style="font-size:32px;line-height:1.2;margin-bottom:8px">
        <?= e($qr->title) ?>
    </div>
    <div class="af-mono af-fs-11 af-track-2 af-mute af-text-c af-mb-6">
        <?= e(t('public.placeholder.dossier_id')) ?>
        <span class="af-phosphor"><?= e($qr->publicId) ?></span>
    </div>

    <div class="af-panel">
        <div class="af-fs-12 af-soft" style="line-height:1.7">
            <?= e(t('public.placeholder.body')) ?>
        </div>

        <table class="af-table" style="border:none;border-top:0.5px solid var(--af-border);margin-top:16px">
            <tbody>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;width:200px;vertical-align:top">
                        <?= e(t('public.placeholder.type_label')) ?>
                    </td>
                    <td>
                        <span class="af-badge af-badge--gold"><?= e(mb_strtoupper(t('qr_types.' . $qr->type))) ?></span>
                    </td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                        <?= e(t('public.placeholder.created_at')) ?>
                    </td>
                    <td class="af-mono"><?= e($qr->createdAt) ?></td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                        <?= e(t('public.placeholder.expires_at')) ?>
                    </td>
                    <td class="af-mono <?= $qr->isExpiring() ? 'af-gold' : 'af-soft' ?>">
                        <?= e(t($expKey, $expParams)) ?>
                    </td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                        <?= e(t('public.placeholder.scans_total')) ?>
                    </td>
                    <td class="af-mono"><?= e((string) $scanCount) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="af-divider" style="margin-top:48px"><span>◆</span></div>

    <div class="af-fs-9 af-track-2 af-mute af-text-c">
        <?= e(mb_strtoupper(t('public.placeholder.footer'))) ?>
    </div>
</main>
<?php
$bodyContent = ob_get_clean();
$title = $qr->title;
require dirname(__DIR__) . '/layout.php';
