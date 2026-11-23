<?php

declare(strict_types=1);

namespace Tests\Entity;

use App\Entity\NewOrderItem;
use PHPUnit\Framework\TestCase;

final class NewOrderItemTest extends TestCase
{
    public function testLineTotalIsPriceTimesQuantity(): void
    {
        $item = new NewOrderItem(
            dishId: 7,
            nameSnapshot: 'Fries',
            priceSnapshotKopecks: 7000,
            quantity: 2,
        );

        self::assertSame(14000, $item->lineTotalKopecks);
    }
}
