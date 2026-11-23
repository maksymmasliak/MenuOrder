<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\ConnectionFactory;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Base for tests that need real tables in the test database.
 *
 * Every test starts from empty tables. Cleanup uses DELETE, not a rolled-back
 * transaction: code under test (OrderRepository::save()) opens its own
 * transaction, and PDO has no nested transactions.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected const string FIXTURE_TIME = '2026-01-01 10:00:00';

    // Children before parents, so foreign keys never block a DELETE.
    // `migrations` is not here: the schema must survive the cleanup.
    private const array TABLES = [
        'order_items',
        'orders',
        'dishes',
        'categories',
        'dining_tables',
        'rate_limit_hits',
        'admins',
    ];

    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = ConnectionFactory::fromEnv()->create();
        // Before the test, not after: a failed test leaves its rows for inspection,
        // and the next test does not depend on how the previous one ended.
        $this->resetDatabase();
    }

    final protected function resetDatabase(): void
    {
        self::assertTestDatabase($this->pdo->query('SELECT DATABASE()')->fetchColumn());

        foreach (self::TABLES as $table) {
            $this->pdo->exec("DELETE FROM {$table}");
        }
    }

    /**
     * Refuses to go on unless the connection points to a *_test database:
     * a misconfigured environment must fail loudly, not wipe working data.
     */
    final protected static function assertTestDatabase(mixed $databaseName): void
    {
        if (!is_string($databaseName) || !str_ends_with($databaseName, '_test')) {
            throw new RuntimeException(sprintf(
                'Refusing to clean database %s: tests must run on a *_test database.',
                is_string($databaseName) ? "'{$databaseName}'" : '(none selected)',
            ));
        }
    }

    protected function insertCategory(string $name = 'Test category', bool $isActive = true): int
    {
        $this->pdo->prepare(
            'INSERT INTO categories (name, is_active, created_at) VALUES (:name, :is_active, :created_at)',
        )->execute([
            'name' => $name,
            'is_active' => (int) $isActive,
            'created_at' => self::FIXTURE_TIME,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function insertDish(
        int $categoryId,
        string $name = 'Test dish',
        int $priceKopecks = 10000,
        bool $isArchived = false,
        ?string $description = null,
    ): int {
        $this->pdo->prepare(
            'INSERT INTO dishes
                (category_id, name, description, price_kopecks, is_archived, created_at, updated_at)
             VALUES (:category_id, :name, :description, :price_kopecks, :is_archived, :created_at, :updated_at)',
        )->execute([
            'category_id' => $categoryId,
            'name' => $name,
            'description' => $description,
            'price_kopecks' => $priceKopecks,
            'is_archived' => (int) $isArchived,
            'created_at' => self::FIXTURE_TIME,
            'updated_at' => self::FIXTURE_TIME,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param string|null $label null generates a unique one: `label` is UNIQUE
     */
    protected function insertDiningTable(?string $label = null, bool $isActive = true): int
    {
        $this->pdo->prepare(
            'INSERT INTO dining_tables (label, is_active, created_at) VALUES (:label, :is_active, :created_at)',
        )->execute([
            'label' => $label ?? 'Table ' . bin2hex(random_bytes(4)),
            'is_active' => (int) $isActive,
            'created_at' => self::FIXTURE_TIME,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
