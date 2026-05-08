<?php
declare(strict_types=1);

namespace ArkhamFiles;

use PDO;
use Throwable;

/**
 * Applies versioned SQL migrations from a directory.
 *
 * Convention: migrations live in `migrations/NNNN_description.sql`,
 * where NNNN is a zero-padded sequence number (4+ digits). They are
 * applied in lexicographic order. Each runs inside its own transaction.
 */
final class Migrations
{
    public function __construct(
        private PDO $pdo,
        private string $migrationsDir
    ) {}

    /**
     * @return string[] List of versions applied in this run (empty if up-to-date).
     */
    public function run(): array
    {
        $this->ensureMigrationsTable();

        $applied = $this->getAppliedVersions();
        $available = $this->getAvailableMigrations();

        $newApplied = [];
        foreach ($available as $version => $file) {
            if (in_array($version, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Cannot read migration file: {$file}");
            }

            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sql);
                $stmt = $this->pdo->prepare(
                    'INSERT INTO _migrations (version, applied_at) VALUES (:v, :at)'
                );
                $stmt->execute([
                    ':v'  => $version,
                    ':at' => date('Y-m-d H:i:s'),
                ]);
                $this->pdo->commit();
                $newApplied[] = $version;
            } catch (Throwable $e) {
                $this->pdo->rollBack();
                throw new \RuntimeException(
                    "Migration {$version} failed: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }

        return $newApplied;
    }

    public function status(): array
    {
        $this->ensureMigrationsTable();
        $applied = $this->getAppliedVersions();
        $available = array_keys($this->getAvailableMigrations());

        return [
            'applied' => $applied,
            'pending' => array_values(array_diff($available, $applied)),
        ];
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS _migrations (
                version    TEXT     PRIMARY KEY,
                applied_at DATETIME NOT NULL
            )
        SQL);
    }

    /** @return string[] */
    private function getAppliedVersions(): array
    {
        $rows = $this->pdo
            ->query('SELECT version FROM _migrations ORDER BY version ASC')
            ->fetchAll(PDO::FETCH_COLUMN);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string,string> version => filepath */
    private function getAvailableMigrations(): array
    {
        $files = glob($this->migrationsDir . '/[0-9]*.sql');
        if ($files === false || $files === []) {
            return [];
        }
        sort($files, SORT_STRING);

        $migrations = [];
        foreach ($files as $file) {
            $basename = basename($file, '.sql');
            $version = explode('_', $basename, 2)[0];
            $migrations[$version] = $file;
        }
        return $migrations;
    }
}
