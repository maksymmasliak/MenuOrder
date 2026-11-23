<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;

final readonly class Dish
{
    public function __construct(
        public int $id,
        public int $categoryId,
        public string $name,
        public ?string $description,
        public int $priceKopecks,
        // Relative to public/uploads/.
        public ?string $photoPath,
        public bool $isArchived,
        public int $sortOrder,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
