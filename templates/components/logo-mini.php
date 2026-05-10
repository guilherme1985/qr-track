<?php
/**
 * Logo compacto do header — versão simplificada do monograma
 * (usa o anel + AF, sem o tail do QR).
 *
 * @var int $logoSize
 */
$size = isset($logoSize) ? (int) $logoSize : 32;
?>
<svg viewBox="0 0 200 200" width="<?= $size ?>" height="<?= $size ?>" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <circle cx="100" cy="100" r="92" fill="none" stroke="#7DDB4F" stroke-width="6" opacity="0.85"/>
    <circle cx="100" cy="100" r="78" fill="none" stroke="#7DDB4F" stroke-width="2" opacity="0.4"/>
    <text x="100" y="100" font-family="Cinzel,Georgia,serif" font-size="80" font-weight="500"
          fill="#7DDB4F" text-anchor="middle" dominant-baseline="central">AF</text>
</svg>
