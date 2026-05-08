<?php
/**
 * Arkham Files — Logo (variação Monograma).
 * Variável $logoSize em pixels. Default 96.
 *
 * @var int $logoSize
 */
$size = isset($logoSize) ? (int) $logoSize : 96;
?>
<svg viewBox="0 0 200 220" width="<?= $size ?>" height="<?= (int) round($size * 1.1) ?>" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <rect x="38" y="38" width="120" height="120" fill="none" stroke="#7DDB4F" stroke-width="1.5"/>
    <rect x="48" y="48" width="22" height="22" fill="#7DDB4F"/>
    <rect x="52" y="52" width="14" height="14" fill="#0F0E13"/>
    <rect x="56" y="56" width="6" height="6" fill="#7DDB4F"/>
    <rect x="126" y="48" width="22" height="22" fill="#7DDB4F"/>
    <rect x="130" y="52" width="14" height="14" fill="#0F0E13"/>
    <rect x="134" y="56" width="6" height="6" fill="#7DDB4F"/>
    <rect x="48" y="126" width="22" height="22" fill="#7DDB4F"/>
    <rect x="52" y="130" width="14" height="14" fill="#0F0E13"/>
    <rect x="56" y="134" width="6" height="6" fill="#7DDB4F"/>
    <text x="100" y="100" text-anchor="middle" dominant-baseline="central"
          font-family="Cinzel,Georgia,serif" font-size="36" font-weight="500"
          fill="#A88B4C" letter-spacing="-1">AF</text>
    <path d="M 142 142 L 178 178" stroke="#7DDB4F" stroke-width="3" fill="none" stroke-linecap="round"/>
    <path d="M 168 178 L 178 178 L 178 168" stroke="#7DDB4F" stroke-width="3" fill="none" stroke-linecap="round"/>
    <text x="100" y="208" text-anchor="middle" font-family="Cinzel,Georgia,serif"
          font-size="9" letter-spacing="4" fill="#A88B4C">QR · AF</text>
</svg>
