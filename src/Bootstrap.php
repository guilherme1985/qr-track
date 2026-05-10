<?php
declare(strict_types=1);

namespace ArkhamFiles;

/**
 * Application bootstrap. Single entry point shared by web and CLI.
 *
 * Responsibilities:
 *   - Composer autoload
 *   - .env loading
 *   - Timezone, error reporting, session config
 *   - Storage paths (creates directories on first run)
 *   - I18n preload
 */
final class Bootstrap
{
    private static bool $initialized = false;
    private static ?string $rootDir   = null;

    public static function init(string $rootDir): void
    {
        if (self::$initialized) {
            return;
        }
        self::$rootDir = $rootDir;

        // Composer autoload
        $autoload = $rootDir . '/vendor/autoload.php';
        if (!is_readable($autoload)) {
            throw new \RuntimeException(
                'Composer autoload not found. Run `composer install` first.'
            );
        }
        require $autoload;

        // Helpers globais (e(), t(), icon()) — ficam no namespace global
        // pra serem usáveis direto nos templates.
        require_once __DIR__ . '/helpers.php';

        // .env
        Config::load($rootDir . '/.env');

        // Timezone
        date_default_timezone_set(Config::get('APP_TIMEZONE', 'UTC'));

        // Error reporting
        $debug = Config::getBool('APP_DEBUG', false);
        if ($debug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        }

        // Garante existência dos diretórios de storage
        self::ensureDir(Config::get('STORAGE_PATH'));
        self::ensureDir(Config::get('STORAGE_PATH') . '/sessions');
        self::ensureDir(Config::get('STORAGE_PATH') . '/cache');
        self::ensureDir(Config::get('UPLOAD_PATH') . '/originals');
        self::ensureDir(Config::get('UPLOAD_PATH') . '/thumbs');

        // Session config (só relevante em request HTTP, mas seguro chamar em CLI)
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
            ini_set('session.save_path', Config::get('STORAGE_PATH') . '/sessions');
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '1');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.use_strict_mode', '1');
            session_name((string) Config::get('SESSION_NAME', 'arkham_session'));
        }

        // Configura View pra apontar pro diretório de templates
        View::setTemplatesDir($rootDir . '/templates');

        // Carrega strings PT-BR (única locale por enquanto)
        I18n::load('pt-br');

        self::$initialized = true;
    }

    public static function rootDir(): string
    {
        if (self::$rootDir === null) {
            throw new \RuntimeException('Bootstrap::init() not called yet');
        }
        return self::$rootDir;
    }

    private static function ensureDir(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        if (is_dir($path)) {
            return;
        }
        if (!mkdir($path, 0770, true) && !is_dir($path)) {
            throw new \RuntimeException("Cannot create directory: {$path}");
        }
    }
}
