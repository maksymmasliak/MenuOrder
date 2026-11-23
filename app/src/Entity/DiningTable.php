<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;

final readonly class DiningTable
{
    public function __construct(
        public int $id,
        public string $label,
        public bool $isActive,
        public DateTimeImmutable $createdAt,
    ) {}
}
