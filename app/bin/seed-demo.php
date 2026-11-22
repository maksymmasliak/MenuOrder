<?php

declare(strict_types=1);

// Fills an empty database with a demo menu: 3 categories, 12 dishes, 10 tables.
// Usage: php bin/seed-demo.php
// Refuses to run in production or when the menu or tables already have data.

use App\Clock\SystemClock;
use App\Config\Env;
use App\Database\ConnectionFactory;

require dirname(__DIR__) . '/src/bootstrap.php';

/**
 * Category name => list of [dish name, description, price in kopecks].
 *
 * @var array<string, list<array{string, string|null, int}>>
 */
$menu = [
    'Бургери' => [
        ['Бургер класичний', 'Яловичина, чедер, соус', 24500],
        ['Чізбургер подвійний', 'Дві котлети, подвійний чедер', 31000],
        ['Курячий бургер', 'Куряче філе, салат, часниковий соус', 22000],
        ['Веган-бургер', 'Нутова котлета, овочі, хумус', 23500],
    ],
    'Закуски' => [
        ['Картопля фрі', null, 7000],
        ['Батат фрі', 'З соусом айолі', 9500],
        ['Цибулеві кільця', null, 8000],
        ['Курячі крильця', '6 шт., соус барбекю', 16500],
    ],
    'Напої' => [
        ['Лимонад', 'Домашній, 0,4 л', 6000],
        ['Кола', '0,33 л', 4500],
        ['Американо', null, 5000],
        ['Капучино', null, 6500],
    ],
];
$tableCount = 10;

try {
    if (Env::get('APP_ENV') === 'prod') {
        throw new RuntimeException('Refusing to seed demo data in production (APP_ENV=prod).');
    }

    $pdo = ConnectionFactory::fromEnv()->create();

    $hasData = $pdo->query(
        'SELECT EXISTS (SELECT 1 FROM categories)
             OR EXISTS (SELECT 1 FROM dishes)
             OR EXISTS (SELECT 1 FROM dining_tables)',
    )->fetchColumn();
    if ((int) $hasData === 1) {
        throw new RuntimeException('Categories, dishes or tables already contain data: nothing seeded.');
    }

    $now = new SystemClock()->now()->format('Y-m-d H:i:s');

    $insertCategory = $pdo->prepare(
        'INSERT INTO categories (name, sort_order, is_active, created_at)
         VALUES (:name, :sort_order, 1, :created_at)',
    );
    $insertDish = $pdo->prepare(
        'INSERT INTO dishes (category_id, name, description, price_kopecks, sort_order, created_at, updated_at)
         VALUES (:category_id, :name, :description, :price, :sort_order, :created_at, :updated_at)',
    );
    $insertTable = $pdo->prepare(
        'INSERT INTO dining_tables (label, is_active, created_at) VALUES (:label, 1, :created_at)',
    );

    $pdo->beginTransaction();
    try {
        $categorySort = 0;
        foreach ($menu as $categoryName => $dishes) {
            $insertCategory->execute([
                'name' => $categoryName,
                'sort_order' => $categorySort += 10,
                'created_at' => $now,
            ]);
            $categoryId = (int) $pdo->lastInsertId();

            $dishSort = 0;
            foreach ($dishes as [$name, $description, $price]) {
                $insertDish->execute([
                    'category_id' => $categoryId,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'sort_order' => $dishSort += 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        for ($i = 1; $i <= $tableCount; $i++) {
            $insertTable->execute(['label' => (string) $i, 'created_at' => $now]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("Seeding failed: %s\n", $e->getMessage()));
    exit(1);
}

$dishTotal = array_sum(array_map(count(...), $menu));
echo sprintf("Seeded %d categories, %d dishes, %d tables.\n", count($menu), $dishTotal, $tableCount);
