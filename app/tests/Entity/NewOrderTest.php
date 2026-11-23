<?php

declare(strict_types=1);

namespace Tests\Entity;

use App\Entity\NewOrder;
use App\Entity\NewOrderItem;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class NewOrderTest extends TestCase
{
    public function testTotalIsSumOfLineTotals(): void
    {
        // The example from the spec: 140,00 + 245,00 + 120,00 = 505,00.
        $order = new NewOrder(
            tableId: 7,
            items: [
                new NewOrderItem(7, 'Fries', 7000, 2),
                new NewOrderItem(12, 'Classic burger', 24500, 1),
                new NewOrderItem(31, 'Lemonade', 6000, 2),
            ],
            comment: null,
            clientIpHash: str_repeat('a', 64),
            createdAt: new DateTimeImmutable('2026-11-23 17:42:00', new DateTimeZone('UTC')),
        );

        self::assertSame(50500, $order->totalKopecks);
    }
}
