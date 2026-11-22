<?php

declare(strict_types=1);

namespace App\Clock;

use DateTimeImmutable;
use DateTimeZone;

final readonly class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        // Explicit zone: does not depend on date.timezone or the default timezone.
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
