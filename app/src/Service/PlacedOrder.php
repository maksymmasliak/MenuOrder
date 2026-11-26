<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Result of a successfully placed order. Formatting for the guest
 * ("505.00", "accepted") belongs to the HTTP layer.
 */
final readonly class PlacedOrder
{
    public function __construct(
        // Order id, shown to the guest as the order number.
        public int $number,
        public int $totalKopecks,
    ) {}
}
