<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\NewOrder;
use DateTimeZone;
use PDO;
use Throwable;

final readonly class OrderRepository
{
    public function __construct(
        private PDO $pdo,
    ) {}

    /**
     * Saves the order with all its items atomically (R5): either everything
     * is stored or nothing is. Validation (R4) is the caller's job.
     *
     * @return int order id, which is also the order number shown to the guest
     */
    public function save(NewOrder $order): int
    {
        $this->pdo->beginTransaction();

        try {
            // Telegram columns are omitted: a new order starts with the schema defaults.
            $this->pdo->prepare(
                'INSERT INTO orders (table_id, total_kopecks, comment, client_ip_hash, created_at)
                 VALUES (:table_id, :total_kopecks, :comment, :client_ip_hash, :created_at)',
            )->execute([
                'table_id' => $order->tableId,
                'total_kopecks' => $order->totalKopecks,
                'comment' => $order->comment,
                'client_ip_hash' => $order->clientIpHash,
                // The database stores UTC whatever zone the caller used.
                'created_at' => $order->createdAt
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d H:i:s'),
            ]);
            // Read before inserting items: each item insert changes lastInsertId().
            $orderId = (int) $this->pdo->lastInsertId();

            $insertItem = $this->pdo->prepare(
                'INSERT INTO order_items
                    (order_id, dish_id, name_snapshot, price_snapshot_kopecks, quantity, line_total_kopecks)
                 VALUES (:order_id, :dish_id, :name_snapshot, :price_snapshot_kopecks, :quantity, :line_total_kopecks)',
            );
            foreach ($order->items as $item) {
                $insertItem->execute([
                    'order_id' => $orderId,
                    'dish_id' => $item->dishId,
                    'name_snapshot' => $item->nameSnapshot,
                    'price_snapshot_kopecks' => $item->priceSnapshotKopecks,
                    'quantity' => $item->quantity,
                    'line_total_kopecks' => $item->lineTotalKopecks,
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            // Guarded: if the transaction is already gone, a failing rollBack()
            // would replace the real error with a misleading one.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }

        return $orderId;
    }
}
