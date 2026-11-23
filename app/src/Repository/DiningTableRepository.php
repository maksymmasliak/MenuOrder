<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DiningTable;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final readonly class DiningTableRepository
{
    public function __construct(
        private PDO $pdo,
    ) {}

    /**
     * Returns inactive tables too: whether an inactive table may order is a rule (R1),
     * not a storage concern.
     */
    public function findById(int $id): ?DiningTable
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, label, is_active, created_at FROM dining_tables WHERE id = :id',
        );
        $stmt->execute(['id' => $id]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): DiningTable
    {
        return new DiningTable(
            id: (int) $row['id'],
            label: (string) $row['label'],
            isActive: (bool) $row['is_active'],
            createdAt: new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')),
        );
    }
}
