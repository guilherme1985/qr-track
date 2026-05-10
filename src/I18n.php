<?php
declare(strict_types=1);

namespace ArkhamFiles;

/**
 * Tradução simples baseada em arquivo PHP de strings.
 *
 * Uso (PHP):
 *   I18n::load('pt-br');
 *   echo I18n::t('admin.dashboard.title');
 *
 * Em templates, prefira a função global `t()` (definida em helpers.php):
 *   <?= t('admin.dashboard.title') ?>
 *   <?= t('common.expires_in_days', ['days' => 5]) ?>
 *
 * Convenção: chaves separadas por ponto, agrupadas por contexto:
 *   common.*           → reutilizadas
 *   admin.dashboard.*
 *   admin.login.*
 *   public.viewer.*
 */
final class I18n
{
    /** @var array<string,string> Flatten map de chaves para strings */
    private static array $strings = [];
    private static string $locale = 'pt-br';

    public static function load(string $locale): void
    {
        self::$locale = $locale;
        $file = dirname(__DIR__) . "/templates/strings/{$locale}.php";
        if (!is_readable($file)) {
            throw new \RuntimeException("Locale file not found: {$locale}");
        }

        $tree = require $file;
        if (!is_array($tree)) {
            throw new \RuntimeException("Locale file must return an array: {$file}");
        }

        self::$strings = self::flatten($tree);
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /**
     * Busca uma string pela chave. Se a chave não existir, retorna a
     * própria chave (útil pra detectar strings faltantes em dev).
     *
     * @param array<string,scalar> $params Substituições :placeholder
     */
    public static function t(string $key, array $params = []): string
    {
        $value = self::$strings[$key] ?? $key;

        if ($params === []) {
            return $value;
        }

        $replacements = [];
        foreach ($params as $name => $val) {
            $replacements[':' . $name] = (string) $val;
        }
        return strtr($value, $replacements);
    }

    /**
     * @param array<string,mixed> $array
     * @return array<string,string>
     */
    private static function flatten(array $array, string $prefix = ''): array
    {
        $out = [];
        foreach ($array as $key => $value) {
            $full = $prefix === '' ? $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $out += self::flatten($value, $full);
            } else {
                $out[$full] = (string) $value;
            }
        }
        return $out;
    }
}
