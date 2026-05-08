<?php
declare(strict_types=1);

namespace ArkhamFiles;

use PDO;

/**
 * SQLite connection singleton.
 * Opens once per request, applies safe pragmas for our concurrency profile.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $path = Config::get('DB_PATH');
            if ($path === null || $path === '') {
                throw new \RuntimeException('DB_PATH is not configured in .env');
            }

            $dir = dirname($path);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0770, true) && !is_dir($dir)) {
                    throw new \RuntimeException("Cannot create database directory: {$dir}");
                }
            }

            self::$pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // WAL: melhor concorrência leitor/escritor
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            // Foreign keys precisam ser ativadas explicitamente em SQLite
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            // NORMAL é seguro com WAL e mais rápido que FULL
            self::$pdo->exec('PRAGMA synchronous = NORMAL');
            // 5s antes de retornar SQLITE_BUSY
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
        }

        return self::$pdo;
    }

    /** Close connection. Mainly useful for tests. */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
