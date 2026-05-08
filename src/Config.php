<?php
declare(strict_types=1);

namespace ArkhamFiles;

/**
 * Loads configuration from a .env file and exposes typed accessors.
 * Minimal implementation; no need for a full vlucas/phpdotenv dependency.
 */
final class Config
{
    /** @var array<string,string> */
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(string $envFile): void
    {
        if (self::$loaded) {
            return;
        }

        if (!is_readable($envFile)) {
            throw new \RuntimeException("Cannot read .env file: {$envFile}");
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new \RuntimeException("Failed to read .env file: {$envFile}");
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);
            // Strip wrapping quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
        }
        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$values[$key] ?? $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = self::$values[$key] ?? null;
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['true', '1', 'yes', 'on'], true);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $v = self::$values[$key] ?? null;
        return $v === null ? $default : (int) $v;
    }

    /** @return string[] */
    public static function getList(string $key, string $separator = ','): array
    {
        $v = self::$values[$key] ?? '';
        if ($v === '') {
            return [];
        }
        return array_map('trim', explode($separator, $v));
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}
