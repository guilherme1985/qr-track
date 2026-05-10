<?php
/**
 * Configurações — placeholder visual.
 * Funcionalidade real: PR 03 (auth) + PR 04 (2FA).
 */

$categoryTree   = [];
$activeCategory = '';
// $currentUser vem do middleware/index.php.

ob_start();
?>
<div class="af-admin-content__title-row">
    <div class="af-admin-content__title">CONFIGURAÇÕES</div>
</div>

<div class="af-panel af-mb-4">
    <div class="af-display af-fs-12 af-track-4 af-gold af-mb-3">━ CREDENCIAIS ━</div>
    <p class="af-soft af-fs-13 af-mb-4">
        Alterar senha e gerenciar autenticação de dois fatores.
    </p>
    <div class="af-flex af-gap-3">
        <button class="af-btn af-btn--sm" disabled>
            <?= e(mb_strtoupper('Alterar senha')) ?>
        </button>
        <button class="af-btn af-btn--ghost af-btn--sm" disabled>
            <?= e(mb_strtoupper('Configurar 2FA')) ?>
        </button>
    </div>
    <div class="af-fs-10 af-mute af-mt-3">
        ⚠ funcionalidade habilitada nos próximos releases
    </div>
</div>

<div class="af-panel af-mb-4">
    <div class="af-display af-fs-12 af-track-4 af-gold af-mb-3">━ AUDITORIA ━</div>
    <p class="af-soft af-fs-13 af-mb-3">
        Trilha de eventos administrativos, retidos por 30 dias.
    </p>
    <a href="#" class="af-btn af-btn--ghost af-btn--sm">
        <?= e(mb_strtoupper('Ver registro')) ?>
    </a>
</div>

<div class="af-panel">
    <div class="af-display af-fs-12 af-track-4 af-gold af-mb-3">━ SOBRE ━</div>
    <table class="af-table" style="border:none">
        <tbody>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0;width:140px">Versão</td>
                <td class="af-mono">v1.0.0</td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0">Build</td>
                <td class="af-mono">Containment Build · MMXXVI</td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0">Origem</td>
                <td class="af-mono">fork de <a href="https://github.com/tuxxin/qr-track">tuxxin/qr-track</a> · GPL-3.0</td>
            </tr>
            <tr style="border:none">
                <td class="af-mute af-fs-11" style="padding-left:0">Repositório</td>
                <td class="af-mono"><a href="https://github.com/guilherme1985/qr-track">guilherme1985/qr-track</a></td>
            </tr>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
$bodyContent = $content;
$title = 'Configurações';
require dirname(__DIR__) . '/layouts/admin.php';
