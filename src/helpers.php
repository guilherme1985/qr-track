<?php
/**
 * Funções globais para uso direto em templates.
 *
 * Esse arquivo NÃO declara namespace — as funções precisam estar no
 * namespace global pra ficarem chamáveis como e(...) e t(...).
 *
 * Carregado via require_once dentro do Bootstrap::init().
 */

declare(strict_types=1);

use ArkhamFiles\I18n;
use ArkhamFiles\View;

if (!function_exists('e')) {
    /**
     * Escape HTML de uma variável. Sempre use isso para qualquer valor
     * que vem de input do usuário ou do banco.
     *
     *   <?= e($titulo) ?>
     *   <?= e($_GET['q'] ?? '') ?>
     */
    function e(mixed $value): string
    {
        return View::escape($value);
    }
}

if (!function_exists('t')) {
    /**
     * Traduz uma chave do locale ativo, com substituição de placeholders.
     *
     *   <?= t('admin.dashboard.title') ?>
     *   <?= t('common.expires_in_days', ['days' => 5]) ?>
     *
     * Se a chave não existir, devolve a própria chave (útil pra ver
     * strings ausentes durante o desenvolvimento).
     *
     * @param array<string,scalar> $params
     */
    function t(string $key, array $params = []): string
    {
        return I18n::t($key, $params);
    }
}

if (!function_exists('icon')) {
    /**
     * Renderiza um ícone do sprite SVG.
     *
     *   <?= icon('leaf') ?>
     *   <?= icon('leaf', 'af-icon--lg af-phosphor') ?>
     *
     * @param array<string,string> $attrs
     */
    function icon(string $name, string $extraClass = '', array $attrs = []): string
    {
        return \ArkhamFiles\Icon::render($name, $extraClass, $attrs);
    }
}
