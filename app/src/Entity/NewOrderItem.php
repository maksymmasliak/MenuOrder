<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Order line before it is saved. Name and price are snapshots taken from the
 * dish at order time, so later menu changes do not rewrite order history.
 */
final readonly class NewOrderItem
{
    public int $lineTotalKopecks;

    public function __construct(
        public int $dishId,
        public string $nameSnapshot,
        public int $priceSnapshotKopecks,
        public int $quantity,
    ) {
        // Derived, never passed in: a line cannot disagree with its own price and quantity.
        $this->lineTotalKopecks = $priceSnapshotKopecks * $quantity;
    }
}
