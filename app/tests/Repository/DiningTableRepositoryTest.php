<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\DiningTableRepository;
use Tests\Support\DatabaseTestCase;

final class DiningTableRepositoryTest extends DatabaseTestCase
{
    public function testFindsExistingTable(): void
    {
        $id = $this->insertDiningTable(label: '7', isActive: false);

        $table = new DiningTableRepository($this->pdo)->findById($id);

        self::assertNotNull($table);
        self::assertSame($id, $table->id);
        self::assertSame('7', $table->label);
        self::assertFalse($table->isActive);
        self::assertSame('2026-01-01 10:00:00', $table->createdAt->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $table->createdAt->getTimezone()->getName());
    }

    public function testReturnsNullForMissingTable(): void
    {
        self::assertNull(new DiningTableRepository($this->pdo)->findById(999999));
    }
}
