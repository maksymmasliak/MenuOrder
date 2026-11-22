<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Database\ConnectionFactory;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Constraints from migrations/001_init.sql that the database itself must enforce.
 * Each test runs inside a transaction that is rolled back (DML only).
 */
final class SchemaTest extends TestCase
{
    private const int CHECK_VIOLATED = 3819;
    private const int FK_PARENT_ROW = 1451;

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = ConnectionFactory::fromEnv()->create();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }

    public function testDishPriceMustBePositive(): void
    {
        $categoryId = $this->insertCategory();

        $this->assertMysqlError(self::CHECK_VIOLATED, fn() => $this->insertDish($categoryId, 0));
    }

    public function testOrderItemQuantityIsLimitedTo20(): void
    {
        $dishId = $this->insertDish($this->insertCategory(), 24500);
        $this->pdo->exec(
            "INSERT INTO dining_tables (label, created_at) VALUES ('schema-test', '2026-11-22 10:00:00')",
        );
        $tableId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec(
            "INSERT INTO orders (table_id, total_kopecks, client_ip_hash, created_at)
             VALUES ({$tableId}, 24500, REPEAT('a', 64), '2026-11-22 10:00:00')",
        );
        $orderId = (int) $this->pdo->lastInsertId();

        $this->assertMysqlError(self::CHECK_VIOLATED, fn() => $this->pdo->exec(
            "INSERT INTO order_items
                (order_id, dish_id, name_snapshot, price_snapshot_kopecks, quantity, line_total_kopecks)
             VALUES ({$orderId}, {$dishId}, 'Dish', 24500, 21, 514500)",
        ));
    }

    public function testCategoryWithDishesCannotBeDeleted(): void
    {
        $categoryId = $this->insertCategory();
        $this->insertDish($categoryId, 24500);

        $this->assertMysqlError(
            self::FK_PARENT_ROW,
            fn() => $this->pdo->exec("DELETE FROM categories WHERE id = {$categoryId}"),
        );
    }

    private function insertCategory(): int
    {
        $this->pdo->exec("INSERT INTO categories (name, created_at) VALUES ('Schema test', '2026-11-22 10:00:00')");

        return (int) $this->pdo->lastInsertId();
    }

    private function insertDish(int $categoryId, int $price): int
    {
        $this->pdo->prepare(
            'INSERT INTO dishes (category_id, name, price_kopecks, created_at, updated_at)
             VALUES (:category_id, :name, :price, :now, :now2)',
        )->execute([
            'category_id' => $categoryId,
            'name' => 'Schema test dish',
            'price' => $price,
            'now' => '2026-11-22 10:00:00',
            'now2' => '2026-11-22 10:00:00',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function assertMysqlError(int $code, callable $action): void
    {
        try {
            $action();
            self::fail(sprintf('Expected MySQL error %d.', $code));
        } catch (PDOException $e) {
            self::assertSame($code, $e->errorInfo[1] ?? null, $e->getMessage());
        }
    }
}
