<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;

/**
 * Order aggregate before it is saved: it has no id yet, the database assigns
 * one in OrderRepository::save(). Telegram fields are not here: a new order
 * always starts with the schema defaults.
 */
final readonly class NewOrder
{
    public int $totalKopecks;

    /**
     * @param list<NewOrderItem> $items
     */
    public function __construct(
        public int $tableId,
        public array $items,
        public ?string $comment,
        public string $clientIpHash,
        public DateTimeImmutable $createdAt,
    ) {
        // Derived, never passed in: the total cannot disagree with the lines.
        $total = 0;
        foreach ($items as $item) {
            $total += $item->lineTotalKopecks;
        }
        $this->totalKopecks = $total;
    }
}
