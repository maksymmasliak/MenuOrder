<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class DatabaseTestCaseTest extends DatabaseTestCase
{
    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonTestDatabaseNames(): iterable
    {
        yield 'working database' => ['menuorder'];
        yield 'suffix not at the end' => ['menuorder_test_old'];
        yield 'no database selected' => [null];
    }

    #[DataProvider('nonTestDatabaseNames')]
    public function testGuardRejectsNonTestDatabase(mixed $name): void
    {
        $this->expectException(RuntimeException::class);

        self::assertTestDatabase($name);
    }

    public function testGuardAcceptsTestDatabase(): void
    {
        self::assertTestDatabase('menuorder_test');

        $this->addToAssertionCount(1);
    }

    public function testConnectionUsesTestDatabase(): void
    {
        self::assertSame('menuorder_test', $this->pdo->query('SELECT DATABASE()')->fetchColumn());
    }

    public function testResetDatabaseEmptiesTables(): void
    {
        $this->insertDish($this->insertCategory());
        $this->insertDiningTable();

        $this->resetDatabase();

        self::assertSame(0, $this->countRows('dishes'));
        self::assertSame(0, $this->countRows('categories'));
        self::assertSame(0, $this->countRows('dining_tables'));
    }

    public function testHelpersStoreGivenValues(): void
    {
        $categoryId = $this->insertCategory(name: 'Drinks', isActive: false);
        $dishId = $this->insertDish($categoryId, name: 'Lemonade', priceKopecks: 6000, isArchived: true);
        $tableId = $this->insertDiningTable(label: 'Terrace 2', isActive: false);

        self::assertSame(
            ['name' => 'Drinks', 'is_active' => 0],
            $this->row("SELECT name, is_active FROM categories WHERE id = {$categoryId}"),
        );
        self::assertSame(
            ['category_id' => $categoryId, 'name' => 'Lemonade', 'price_kopecks' => 6000, 'is_archived' => 1],
            $this->row("SELECT category_id, name, price_kopecks, is_archived FROM dishes WHERE id = {$dishId}"),
        );
        self::assertSame(
            ['label' => 'Terrace 2', 'is_active' => 0],
            $this->row("SELECT label, is_active FROM dining_tables WHERE id = {$tableId}"),
        );
    }

    public function testDiningTablesWithoutLabelDoNotCollide(): void
    {
        $this->insertDiningTable();
        $this->insertDiningTable();

        self::assertSame(2, $this->countRows('dining_tables'));
    }

    private function countRows(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    /**
     * @return array<string, mixed>|false
     */
    private function row(string $sql): array|false
    {
        return $this->pdo->query($sql)->fetch();
    }
}
