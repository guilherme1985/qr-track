<?php
/**
 * Viewer público de strain — substitui o placeholder do PR 06 quando um
 * QR do tipo 'strain' é acessado em /p/{public_id}.
 *
 * Tom: "dossiê botânico restrito" do Arkham — selo de classificação,
 * timeline horizontal das datas, cálculo de ciclo total.
 *
 * Variáveis esperadas:
 *   $qr       → QrCode (já validado: não expirou, não disabled/deleted)
 *   $strain   → Strain (já carregado)
 *   $category → Category|null
 */
use ArkhamFiles\Category;
use ArkhamFiles\QrCode;
use ArkhamFiles\Strain;

/** @var QrCode $qr */
$qr = $qr;
/** @var Strain $strain */
$strain = $strain;
/** @var Category|null $category */
$category = $category ?? null;

[$expKey, $expParams] = $qr->expirationLabel();

$phase = $strain->lifecyclePhase();
$daysVeg    = $strain->daysInVeg();
$daysFlower = $strain->daysInFlower();
$totalCycle = $strain->totalCycleDays();

// Função helper pra formatar data BR
$fmtDate = function (?string $date): string {
    if (!$date) return '';
    $t = strtotime($date);
    if (!$t) return $date;
    $months = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
    return date('d', $t) . '.' . $months[(int) date('n', $t) - 1] . '.' . date('y', $t);
};

ob_start();
?>
<main class="af-container af-container--narrow"
      style="padding-top:48px;padding-bottom:64px;max-width:760px">

    <!-- Header institucional -->
    <div class="af-text-c">
        <?php $logoSize = 80; require dirname(__DIR__) . '/components/logo.php'; ?>
    </div>

    <div class="af-divider af-mt-6 af-mb-4"><span>◆</span></div>

    <div class="af-fs-10 af-track-4 af-gold af-text-c af-mb-3">
        <?= e(t('public.strain_viewer.kicker')) ?>
    </div>

    <!-- Aviso de expiração -->
    <?php if ($qr->isExpiring()): ?>
        <div class="af-text-c af-fs-10 af-track-2 af-gold af-mb-4"
             style="padding:8px 14px;border:0.5px dashed var(--af-gold);background:rgba(168,139,76,0.06)">
            <?= e(t('public.strain_viewer.expiring_warning', ['label' => t($expKey, $expParams)])) ?>
        </div>
    <?php endif; ?>

    <!-- Stamp de classificação rotacionado (decorativo) -->
    <div style="position:relative">
        <div style="position:absolute;right:-10px;top:-20px;transform:rotate(-12deg);
                    border:2px solid var(--af-blood);padding:6px 14px;
                    color:var(--af-blood);font-family:var(--af-font-mono);
                    font-size:11px;letter-spacing:0.18em;
                    opacity:0.65;pointer-events:none;
                    background:rgba(92,26,27,0.04)">
            ▲ <?= e(t('public.strain_viewer.class_badge')) ?> ▲
        </div>
    </div>

    <!-- Título principal -->
    <h1 class="af-editorial af-w-500 af-text-c"
        style="font-size:38px;line-height:1.15;margin:0 0 6px 0;letter-spacing:-0.01em">
        <?= e($qr->title) ?>
    </h1>

    <div class="af-mono af-fs-11 af-track-2 af-mute af-text-c af-mb-1">
        <?= e(t('public.strain_viewer.dossier_id')) ?>
        <span class="af-phosphor" style="text-transform:none"><?= e($qr->publicId) ?></span>
    </div>

    <div class="af-fs-9 af-track-3 af-faint af-text-c af-mb-6">
        — <?= e(t('public.strain_viewer.department')) ?> · <?= e(t('public.strain_viewer.department_short')) ?> —
    </div>

    <!-- Painel CLASSIFICAÇÃO -->
    <div class="af-panel af-mb-6" style="background:rgba(168,139,76,0.03)">
        <div class="af-fs-10 af-track-3 af-gold af-mb-3">
            ━━ <?= e(t('public.strain_viewer.classification')) ?> ━━
        </div>
        <table class="af-table" style="border:none">
            <tbody>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;width:200px;vertical-align:top">
                        <?= e(t('public.strain_viewer.specimen_label')) ?>
                    </td>
                    <td class="af-w-500"><?= e($strain->strainName) ?></td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                        <?= e(t('public.strain_viewer.genetics_label')) ?>
                    </td>
                    <td>
                        <span class="af-badge af-badge--gold"><?= e(mb_strtoupper(t('public.strain_viewer.genetics_' . $strain->genetics))) ?></span>
                    </td>
                </tr>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                        <?= e(t('public.strain_viewer.source_label')) ?>
                    </td>
                    <td><?= e(t('public.strain_viewer.source_' . $strain->source)) ?></td>
                </tr>
                <?php if ($strain->source === 'semente' && $strain->seedType): ?>
                    <tr style="border:none">
                        <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                            <?= e(t('public.strain_viewer.seed_type_label')) ?>
                        </td>
                        <td><?= e(t('public.strain_viewer.seed_' . $strain->seedType)) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($category): ?>
                    <tr style="border:none">
                        <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                            <?= e(t('public.strain_viewer.category_label')) ?>
                        </td>
                        <td>
                            <?php if ($category->color): ?>
                                <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:<?= e($category->color) ?>;vertical-align:middle;margin-right:4px"></span>
                            <?php endif; ?>
                            <?= e($category->name) ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr style="border:none">
                    <td class="af-mute af-fs-11" style="padding-left:0;vertical-align:top">
                        <?= e(mb_strtoupper(t('common.validity'))) ?>
                    </td>
                    <td class="af-mono <?= $qr->isExpiring() ? 'af-gold' : 'af-soft' ?>">
                        <?= e(t($expKey, $expParams)) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- TIMELINE BOTÂNICA -->
    <?php if ($strain->plantingDate || $strain->floweringDate || $strain->harvestDate): ?>
        <div class="af-fs-10 af-track-3 af-gold af-text-c af-mb-3">
            <?= e(t('public.strain_viewer.timeline_title')) ?>
        </div>

        <div class="af-strain-timeline">
            <!-- Linha conectora -->
            <div class="af-strain-timeline__line"></div>

            <!-- Eventos -->
            <div class="af-strain-timeline__row">
                <!-- Plantio -->
                <div class="af-strain-timeline__step">
                    <div class="af-strain-timeline__dot <?= $strain->plantingDate ? 'af-strain-timeline__dot--filled' : '' ?>">
                        <?= icon('seedling', 'af-icon--sm') ?>
                    </div>
                    <div class="af-strain-timeline__label"><?= e(mb_strtoupper(t('public.strain_viewer.planting'))) ?></div>
                    <div class="af-strain-timeline__date">
                        <?= $strain->plantingDate ? e($fmtDate($strain->plantingDate)) : e(t('public.strain_viewer.no_data')) ?>
                    </div>
                </div>

                <!-- Floração -->
                <div class="af-strain-timeline__step">
                    <div class="af-strain-timeline__dot <?= $strain->floweringDate ? 'af-strain-timeline__dot--filled' : '' ?>">
                        <?= icon('leaf', 'af-icon--sm') ?>
                    </div>
                    <div class="af-strain-timeline__label"><?= e(mb_strtoupper(t('public.strain_viewer.flowering'))) ?></div>
                    <div class="af-strain-timeline__date">
                        <?= $strain->floweringDate ? e($fmtDate($strain->floweringDate)) : e(t('public.strain_viewer.no_data')) ?>
                    </div>
                </div>

                <!-- Colheita -->
                <div class="af-strain-timeline__step">
                    <div class="af-strain-timeline__dot <?= $strain->harvestDate ? 'af-strain-timeline__dot--filled' : '' ?>">
                        <?= icon('check', 'af-icon--sm') ?>
                    </div>
                    <div class="af-strain-timeline__label"><?= e(mb_strtoupper(t('public.strain_viewer.harvest'))) ?></div>
                    <div class="af-strain-timeline__date">
                        <?= $strain->harvestDate ? e($fmtDate($strain->harvestDate)) : e(t('public.strain_viewer.no_data')) ?>
                    </div>
                </div>
            </div>

            <!-- Stats: dias em cada fase -->
            <?php if ($daysVeg !== null || $daysFlower !== null || $totalCycle !== null): ?>
                <div class="af-strain-timeline__stats">
                    <?php if ($daysVeg !== null): ?>
                        <div class="af-fs-10 af-track-1 af-soft">
                            <span class="af-phosphor af-mono af-fs-12"><?= e((string) $daysVeg) ?></span>
                            <span class="af-mute">dias em vegetação</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($daysFlower !== null): ?>
                        <div class="af-fs-10 af-track-1 af-soft">
                            <span class="af-gold af-mono af-fs-12"><?= e((string) $daysFlower) ?></span>
                            <span class="af-mute">dias em floração</span>
                        </div>
                    <?php endif; ?>
                    <?php if ($totalCycle !== null): ?>
                        <div class="af-fs-10 af-track-1 af-soft">
                            <span class="af-w-500 af-mono af-fs-12"><?= e((string) $totalCycle) ?></span>
                            <span class="af-mute">dias · ciclo total</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Phase badge -->
            <div class="af-strain-timeline__phase">
                <span class="af-fs-9 af-track-3">FASE ATUAL</span>
                <span class="af-badge af-strain-timeline__phase-badge af-strain-timeline__phase-badge--<?= e($phase) ?>">
                    <?= e(t('public.strain_viewer.phase_' . $phase)) ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Warning institucional -->
    <div class="af-fs-9 af-track-3 af-blood af-text-c af-mt-6"
         style="padding:10px 14px;border-top:0.5px solid var(--af-border);border-bottom:0.5px solid var(--af-border)">
        ▲ <?= e(mb_strtoupper(t('public.strain_viewer.warning_label'))) ?> ▲
    </div>

    <!-- Footer institucional -->
    <div class="af-divider" style="margin-top:48px"><span>◆</span></div>

    <div class="af-fs-9 af-track-2 af-faint af-text-c af-mb-2">
        <?= e(t('public.strain_viewer.document_accessed', ['datetime' => gmdate('d.m.Y · H:i') . ' UTC'])) ?>
    </div>
    <div class="af-fs-9 af-track-2 af-mute af-text-c">
        <?= e(mb_strtoupper(t('public.strain_viewer.footer'))) ?>
    </div>
</main>

<style>
/* Timeline botânica — 3 etapas horizontais conectadas por linha.
   Isolado pra não interferir com outros viewers. */
.af-strain-timeline {
    max-width: 660px;
    margin: 0 auto 24px auto;
    padding: 24px 16px;
    border: 0.5px solid var(--af-border);
    background: rgba(125, 219, 79, 0.02);
    position: relative;
}

.af-strain-timeline__row {
    display: flex;
    justify-content: space-between;
    position: relative;
    z-index: 2;
}

.af-strain-timeline__line {
    position: absolute;
    top: 48px;
    left: 18%;
    right: 18%;
    height: 0.5px;
    background: linear-gradient(to right,
        var(--af-phosphor) 0%,
        var(--af-gold)  50%,
        var(--af-mute)  100%);
    opacity: 0.35;
    z-index: 1;
}

.af-strain-timeline__step {
    flex: 1;
    text-align: center;
    padding: 0 8px;
}

.af-strain-timeline__dot {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 0.5px solid var(--af-border);
    background: var(--af-bg);
    color: var(--af-faint);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px auto;
    position: relative;
    z-index: 3;
}

.af-strain-timeline__dot--filled {
    border-color: var(--af-phosphor);
    color: var(--af-phosphor);
    background: rgba(125, 219, 79, 0.06);
    box-shadow: 0 0 0 3px rgba(125, 219, 79, 0.08);
}

.af-strain-timeline__label {
    font-family: var(--af-font-mono);
    font-size: 9px;
    letter-spacing: 0.16em;
    color: var(--af-mute);
    margin-bottom: 4px;
}

.af-strain-timeline__date {
    font-family: var(--af-font-mono);
    font-size: 12px;
    color: var(--af-soft);
}

.af-strain-timeline__stats {
    display: flex;
    justify-content: space-around;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 0.5px dashed var(--af-border);
    flex-wrap: wrap;
    gap: 12px;
}

.af-strain-timeline__phase {
    text-align: center;
    margin-top: 14px;
    padding-top: 10px;
    color: var(--af-mute);
}

.af-strain-timeline__phase-badge {
    margin-left: 8px;
    padding: 3px 10px;
    font-family: var(--af-font-mono);
    font-size: 10px;
    letter-spacing: 0.15em;
    border: 0.5px solid currentColor;
}

.af-strain-timeline__phase-badge--planted   { color: var(--af-phosphor); }
.af-strain-timeline__phase-badge--flowering { color: var(--af-gold); }
.af-strain-timeline__phase-badge--harvested { color: var(--af-soft); }
.af-strain-timeline__phase-badge--unknown   { color: var(--af-faint); }
</style>
<?php
$bodyContent = ob_get_clean();
$title = $qr->title;
require dirname(__DIR__) . '/layout.php';
