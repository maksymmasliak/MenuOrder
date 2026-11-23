<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Entity\NewOrder;
use App\Entity\NewOrderItem;
use App\Repository\OrderRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use Tests\Support\DatabaseTestCase;

final class OrderRepositoryTest extends DatabaseTestCase
{
    private const string IP_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const int FK_NO_PARENT_ROW = 1452;

    public function testSavesOrderAndReturnsItsId(): void
    {
        $tableId = $this->insertDiningTable();
        $dishId = $this->insertDish($this->insertCategory());

        $orderId = $this->repository()->save($this->order($tableId, [
            new NewOrderItem($dishId, 'Burger', 24500, 2),
        ], comment: 'No onion'));

        // The only row in the table, not "the row with the returned id": otherwise
        // the id assertion below would hold by construction.
        self::assertSame(1, $this->countRows('orders'));
        self::assertSame(
            [
                'id' => $orderId,
                'table_id' => $tableId,
                'total_kopecks' => 49000,
                'comment' => 'No onion',
                'client_ip_hash' => self::IP_HASH,
                'telegram_status' => 'pending',
                'telegram_attempts' => 0,
                'created_at' => '2026-11-23 10:00:00',
            ],
            $this->pdo->query(
                'SELECT id, table_id, total_kopecks, comment, client_ip_hash,
                        telegram_status, telegram_attempts, created_at
                 FROM orders',
            )->fetch(),
        );
    }

    public function testSavesItemsWithSnapshots(): void
    {
        $tableId = $this->insertDiningTable();
        $categoryId = $this->insertCategory();
        $burgerId = $this->insertDish($categoryId);
        $lemonadeId = $this->insertDish($categoryId);

        $orderId = $this->repository()->save($this->order($tableId, [
            new NewOrderItem($burgerId, 'Burger', 24500, 1),
            new NewOrderItem($lemonadeId, 'Lemonade', 6000, 2),
        ]));

        self::assertSame(
            [
                [
                    'dish_id' => $burgerId,
                    'name_snapshot' => 'Burger',
                    'price_snapshot_kopecks' => 24500,
                    'quantity' => 1,
                    'line_total_kopecks' => 24500,
                ],
                [
                    'dish_id' => $lemonadeId,
                    'name_snapshot' => 'Lemonade',
                    'price_snapshot_kopecks' => 6000,
                    'quantity' => 2,
                    'line_total_kopecks' => 12000,
                ],
            ],
            $this->pdo->query(
                "SELECT dish_id, name_snapshot, price_snapshot_kopecks, quantity, line_total_kopecks
                 FROM order_items WHERE order_id = {$orderId} ORDER BY id",
            )->fetchAll(),
        );
    }

    public function testNullCommentIsStoredAsNull(): void
    {
        $tableId = $this->insertDiningTable();
        $dishId = $this->insertDish($this->insertCategory());

        $orderId = $this->repository()->save($this->order($tableId, [
            new NewOrderItem($dishId, 'Burger', 24500, 1),
        ]));

        self::assertNull(
            $this->pdo->query("SELECT comment FROM orders WHERE id = {$orderId}")->fetchColumn(),
        );
    }

    public function testCreatedAtIsStoredInUtc(): void
    {
        $tableId = $this->insertDiningTable();
        $dishId = $this->insertDish($this->insertCategory());

        // Kyiv is UTC+2 in November.
        $orderId = $this->repository()->save($this->order(
            $tableId,
            [new NewOrderItem($dishId, 'Burger', 24500, 1)],
            createdAt: new DateTimeImmutable('2026-11-23 12:00:00', new DateTimeZone('Europe/Kyiv')),
        ));

        self::assertSame(
            '2026-11-23 10:00:00',
            $this->pdo->query("SELECT created_at FROM orders WHERE id = {$orderId}")->fetchColumn(),
        );
    }

    public function testFailedItemInsertLeavesNoOrderAndNoItems(): void
    {
        $tableId = $this->insertDiningTable();
        $dishId = $this->insertDish($this->insertCategory());

        try {
            // The first item is valid and gets inserted; the second breaks the dish FK.
            $this->repository()->save($this->order($tableId, [
                new NewOrderItem($dishId, 'Burger', 24500, 1),
                new NewOrderItem($dishId + 1000, 'Ghost', 100, 1),
            ]));
            self::fail('Expected the item insert to fail.');
        } catch (PDOException $e) {
            // Proves the failure came from order_items, i.e. after the order row was
            // inserted: only then do zero rows below demonstrate a rollback.
            self::assertSame(self::FK_NO_PARENT_ROW, $e->errorInfo[1] ?? null, $e->getMessage());
            self::assertStringContainsString('fk_order_items_dish', $e->getMessage());
        }

        self::assertSame(0, $this->countRows('orders'));
        self::assertSame(0, $this->countRows('order_items'));
        self::assertFalse($this->pdo->inTransaction());
    }

    private function repository(): OrderRepository
    {
        return new OrderRepository($this->pdo);
    }

    /**
     * @param list<NewOrderItem> $items
     */
    private function order(
        int $tableId,
        array $items,
        ?string $comment = null,
        ?DateTimeImmutable $createdAt = null,
    ): NewOrder {
        return new NewOrder(
            tableId: $tableId,
            items: $items,
            comment: $comment,
            clientIpHash: self::IP_HASH,
            createdAt: $createdAt ?? new DateTimeImmutable('2026-11-23 10:00:00', new DateTimeZone('UTC')),
        );
    }
}
