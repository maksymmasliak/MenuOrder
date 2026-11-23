<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Dish;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final readonly class DishRepository
{
    public function __construct(
        private PDO $pdo,
    ) {}

    /**
     * Dishes a guest can order: not archived, in an active category.
     * Unknown and unavailable ids are simply absent; the caller decides what that means.
     *
     * @param list<int> $ids
     * @return array<int, Dish> keyed by dish id
     */
    public function findAvailableByIds(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            // "IN ()" is a syntax error in MySQL.
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT d.id, d.category_id, d.name, d.description, d.price_kopecks, d.photo_path,
                    d.is_archived, d.sort_order, d.created_at, d.updated_at
             FROM dishes d
             JOIN categories c ON c.id = d.category_id
             WHERE d.id IN ({$placeholders})
               AND d.is_archived = 0
               AND c.is_active = 1",
        );
        $stmt->execute($ids);

        $dishes = [];
        foreach ($stmt->fetchAll() as $row) {
            $dish = $this->hydrate($row);
            $dishes[$dish->id] = $dish;
        }

        return $dishes;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Dish
    {
        $utc = new DateTimeZone('UTC');

        return new Dish(
            id: (int) $row['id'],
            categoryId: (int) $row['category_id'],
            name: (string) $row['name'],
            description: $row['description'] === null ? null : (string) $row['description'],
            priceKopecks: (int) $row['price_kopecks'],
            photoPath: $row['photo_path'] === null ? null : (string) $row['photo_path'],
            isArchived: (bool) $row['is_archived'],
            sortOrder: (int) $row['sort_order'],
            createdAt: new DateTimeImmutable((string) $row['created_at'], $utc),
            updatedAt: new DateTimeImmutable((string) $row['updated_at'], $utc),
        );
    }
}
