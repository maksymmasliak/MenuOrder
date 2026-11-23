<?php

declare(strict_types=1);

namespace Tests\Repository;

use App\Repository\DishRepository;
use Tests\Support\DatabaseTestCase;

final class DishRepositoryTest extends DatabaseTestCase
{
    public function testReturnsAvailableDishWithAllFields(): void
    {
        $categoryId = $this->insertCategory();
        $dishId = $this->insertDish(
            $categoryId,
            name: 'Бургер класичний',
            priceKopecks: 24500,
            description: 'Яловичина, чедер',
        );
        // The helper writes equal timestamps, no photo and sort_order 0: with those,
        // swapped columns in hydrate() would go unnoticed.
        $this->pdo->prepare(
            'UPDATE dishes SET photo_path = :photo_path, sort_order = :sort_order, updated_at = :updated_at
             WHERE id = :id',
        )->execute([
            'photo_path' => 'burger.webp',
            'sort_order' => 5,
            'updated_at' => '2026-01-02 11:00:00',
            'id' => $dishId,
        ]);

        $dishes = new DishRepository($this->pdo)->findAvailableByIds([$dishId]);

        self::assertCount(1, $dishes);
        self::assertArrayHasKey($dishId, $dishes);

        $dish = $dishes[$dishId];
        self::assertSame($dishId, $dish->id);
        self::assertSame($categoryId, $dish->categoryId);
        self::assertSame('Бургер класичний', $dish->name);
        self::assertSame('Яловичина, чедер', $dish->description);
        self::assertSame(24500, $dish->priceKopecks);
        self::assertSame('burger.webp', $dish->photoPath);
        self::assertFalse($dish->isArchived);
        self::assertSame(5, $dish->sortOrder);
        // format() prints in the object's own zone, so the zone is checked separately.
        self::assertSame('2026-01-01 10:00:00', $dish->createdAt->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $dish->createdAt->getTimezone()->getName());
        self::assertSame('2026-01-02 11:00:00', $dish->updatedAt->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $dish->updatedAt->getTimezone()->getName());
    }

    public function testDoesNotReturnArchivedDish(): void
    {
        $dishId = $this->insertDish($this->insertCategory(), isArchived: true);

        $dishes = new DishRepository($this->pdo)->findAvailableByIds([$dishId]);

        self::assertSame([], $dishes);
    }

    public function testDoesNotReturnDishFromInactiveCategory(): void
    {
        $dishId = $this->insertDish($this->insertCategory(isActive: false));

        $dishes = new DishRepository($this->pdo)->findAvailableByIds([$dishId]);

        self::assertSame([], $dishes);
    }

    public function testIgnoresMissingIds(): void
    {
        $dishId = $this->insertDish($this->insertCategory());

        $dishes = new DishRepository($this->pdo)->findAvailableByIds([$dishId, $dishId + 1000]);

        self::assertCount(1, $dishes);
        self::assertArrayHasKey($dishId, $dishes);
    }

    public function testReturnsEmptyArrayForEmptyIds(): void
    {
        self::assertSame([], new DishRepository($this->pdo)->findAvailableByIds([]));
    }

    public function testDuplicateIdsReturnOneDish(): void
    {
        $dishId = $this->insertDish($this->insertCategory());

        $dishes = new DishRepository($this->pdo)->findAvailableByIds([$dishId, $dishId]);

        self::assertSame([$dishId], array_keys($dishes));
    }
}
