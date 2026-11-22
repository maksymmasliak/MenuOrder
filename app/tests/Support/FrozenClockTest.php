<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class FrozenClockTest extends TestCase
{
    public function testReturnsGivenTime(): void
    {
        $time = new DateTimeImmutable('2026-11-21 17:42:00', new DateTimeZone('UTC'));
        $clock = new FrozenClock($time);

        self::assertSame($time, $clock->now());
        self::assertSame($clock->now(), $clock->now());
    }
}
