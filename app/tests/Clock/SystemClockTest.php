<?php

declare(strict_types=1);

namespace Tests\Clock;

use App\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function testReturnsCurrentTimeInUtc(): void
    {
        $before = time();
        $now = new SystemClock()->now();
        $after = time();

        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $now->getTimestamp());
        self::assertLessThanOrEqual($after, $now->getTimestamp());
    }
}
