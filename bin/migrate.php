#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Migration runner CLI.
 *
 * Usage:
 *   php bin/migrate.php          # apply pending migrations
 *   php bin/migrate.php status   # show applied/pending without changes
 */

require __DIR__ . '/../src/Bootstrap.php';
require __DIR__ . '/../src/Config.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Migrations.php';
require __DIR__ . '/../src/Http.php';

use ArkhamFiles\Bootstrap;
use ArkhamFiles\Database;
use ArkhamFiles\Migrations;

$rootDir = dirname(__DIR__);

try {
    Bootstrap::init($rootDir);
} catch (\Throwable $e) {
    fwrite(STDERR, "Bootstrap failed: " . $e->getMessage() . "\n");
    exit(1);
}

$migrations = new Migrations(Database::pdo(), $rootDir . '/migrations');

$cmd = $argv[1] ?? 'run';

try {
    if ($cmd === 'status') {
        $s = $migrations->status();
        echo "Applied:\n";
        foreach ($s['applied'] as $v) {
            echo "  ✓ {$v}\n";
        }
        if (empty($s['applied'])) {
            echo "  (none)\n";
        }
        echo "\nPending:\n";
        foreach ($s['pending'] as $v) {
            echo "  · {$v}\n";
        }
        if (empty($s['pending'])) {
            echo "  (none)\n";
        }
        exit(0);
    }

    if ($cmd === 'run') {
        $applied = $migrations->run();
        if (empty($applied)) {
            echo "Nothing to migrate. Database is up to date.\n";
        } else {
            echo "Applied " . count($applied) . " migration(s):\n";
            foreach ($applied as $v) {
                echo "  ✓ {$v}\n";
            }
        }
        exit(0);
    }

    fwrite(STDERR, "Unknown command: {$cmd}\nUsage: migrate.php [run|status]\n");
    exit(2);
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    if (Bootstrap::class && \ArkhamFiles\Config::getBool('APP_DEBUG', false)) {
        fwrite(STDERR, $e->getTraceAsString() . "\n");
    }
    exit(1);
}
