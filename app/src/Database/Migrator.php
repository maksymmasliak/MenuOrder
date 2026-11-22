<?php

declare(strict_types=1);

namespace App\Database;

use App\Clock\Clock;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Applies SQL migrations from a directory in filename order.
 *
 * MySQL commits DDL implicitly, so a migration cannot be rolled back.
 * Each file is one logical change, its statements run one by one,
 * and the file is recorded in `migrations` only after all of them succeed.
 *
 * File format limits: statements end with ";" at the end of a line,
 * full-line comments start with "--". A ";" at the end of a line inside
 * a string literal, and DELIMITER / stored routines, are not supported.
 */
final readonly class Migrator
{
    private const string FILE_PATTERN = '/^\d{3}_[a-z0-9_]+\.sql$/';

    public function __construct(
        private PDO $pdo,
        private string $directory,
        private Clock $clock,
    ) {}

    /**
     * @return list<string> names of the files applied by this run
     */
    public function migrate(): array
    {
        $this->ensureTable();

        $applied = [];
        foreach ($this->pendingFiles() as $name) {
            $this->apply($name);
            $applied[] = $name;
        }

        return $applied;
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                name       VARCHAR(255) NOT NULL,
                applied_at DATETIME     NOT NULL,
                PRIMARY KEY (name)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci',
        );
    }

    /**
     * @return list<string>
     */
    private function pendingFiles(): array
    {
        if (!is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Migrations directory not found: %s', $this->directory));
        }

        $files = [];
        foreach (glob($this->directory . '/*.sql') ?: [] as $path) {
            $name = basename($path);
            if (preg_match(self::FILE_PATTERN, $name) !== 1) {
                throw new RuntimeException(sprintf(
                    'Invalid migration file name: %s (expected NNN_snake_case.sql).',
                    $name,
                ));
            }
            $files[] = $name;
        }
        sort($files, SORT_STRING);

        $stmt = $this->pdo->query('SELECT name FROM migrations');
        $done = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_diff($files, $done));
    }

    private function apply(string $name): void
    {
        $sql = file_get_contents($this->directory . '/' . $name);
        if ($sql === false) {
            throw new RuntimeException(sprintf('Cannot read migration %s.', $name));
        }

        $statements = self::splitStatements($sql);
        if ($statements === []) {
            throw new RuntimeException(sprintf('Migration %s contains no statements.', $name));
        }

        foreach ($statements as $index => $statement) {
            try {
                $this->pdo->exec($statement);
            } catch (PDOException $e) {
                // Earlier statements of this file are already committed (implicit DDL commit):
                // the message says where to start fixing the database by hand.
                throw new RuntimeException(sprintf(
                    'Migration %s failed at statement %d of %d: %s',
                    $name,
                    $index + 1,
                    count($statements),
                    $e->getMessage(),
                ), 0, $e);
            }
        }

        $this->pdo
            ->prepare('INSERT INTO migrations (name, applied_at) VALUES (:name, :applied_at)')
            ->execute([
                'name' => $name,
                'applied_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * @return list<string>
     */
    private static function splitStatements(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $code = implode("\n", array_filter(
            $lines,
            static fn(string $line): bool => !str_starts_with(ltrim($line), '--'),
        ));

        $parts = preg_split('/;[ \t]*$/m', $code) ?: [];

        return array_values(array_filter(
            array_map(trim(...), $parts),
            static fn(string $statement): bool => $statement !== '',
        ));
    }
}
