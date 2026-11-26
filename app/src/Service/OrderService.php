<?php

declare(strict_types=1);

namespace App\Service;

use App\Clock\Clock;
use App\Entity\NewOrder;
use App\Entity\NewOrderItem;
use App\Repository\DiningTableRepository;
use App\Repository\DishRepository;
use App\Repository\OrderRepository;

/**
 * Validates a guest order (R4), prices it from the database (R3) and saves it
 * through the atomic OrderRepository::save() (R5).
 *
 * Checks run in steps: table, then request shape and limits without the
 * database, then dish availability. A step runs only if the previous one found
 * no errors; within a step all errors are collected into one exception.
 */
final readonly class OrderService
{
    // Guards against huge requests before any merging is done.
    private const int MAX_RAW_ITEMS = 100;
    private const int MAX_DISTINCT_ITEMS = 30;
    private const int MIN_QUANTITY = 1;
    private const int MAX_QUANTITY = 20;
    private const int MAX_COMMENT_LENGTH = 200;

    // Control (Cc) and format (Cf) characters, except the newline and the
    // zero-width joiner, which composite emoji are built from.
    private const string FORBIDDEN_COMMENT_CHARS = '/(?![\n\x{200D}])[\p{Cc}\p{Cf}]/u';

    private const string MSG_ITEMS = 'Додайте від 1 до 30 різних страв';
    private const string MSG_ITEM = 'Некоректна позиція';
    private const string MSG_DISH_INVALID = 'Некоректна страва';
    private const string MSG_DISH_UNAVAILABLE = 'Страва недоступна';
    private const string MSG_QUANTITY = 'Кількість — від 1 до 20';
    private const string MSG_COMMENT = 'Коментар — до 200 символів без спецсимволів';

    public function __construct(
        private DishRepository $dishes,
        private OrderRepository $orders,
        private DiningTableRepository $tables,
        private Clock $clock,
    ) {}

    /**
     * @param array<mixed> $payload decoded JSON body; prices, table id and other
     *                              extra fields in it are ignored (R2, R3)
     *
     * @throws TableUnavailableException
     * @throws OrderValidationException
     */
    public function place(int $tableId, array $payload, string $clientIpHash): PlacedOrder
    {
        // Step 0: the table.
        $table = $this->tables->findById($tableId);
        if ($table === null || !$table->isActive) {
            throw new TableUnavailableException(sprintf('Table %d is unavailable.', $tableId));
        }

        // Step 1: shape and limits, no database.
        $errors = [];
        [$quantities, $positions] = $this->parseItems($payload['items'] ?? null, $errors);
        $comment = $this->parseComment($payload['comment'] ?? null, $errors);
        if ($errors !== []) {
            throw new OrderValidationException($errors);
        }

        // Step 2: availability, one query for all distinct dishes.
        $dishes = $this->dishes->findAvailableByIds(array_keys($quantities));
        foreach ($positions as $dishId => $indexes) {
            if (isset($dishes[$dishId])) {
                continue;
            }
            foreach ($indexes as $index) {
                $errors["items.{$index}.dishId"] = self::MSG_DISH_UNAVAILABLE;
            }
        }
        if ($errors !== []) {
            throw new OrderValidationException($errors);
        }

        // Names and prices are snapshots from the database, never from the request.
        $items = [];
        foreach ($quantities as $dishId => $quantity) {
            $dish = $dishes[$dishId];
            $items[] = new NewOrderItem($dish->id, $dish->name, $dish->priceKopecks, $quantity);
        }

        $order = new NewOrder($tableId, $items, $comment, $clientIpHash, $this->clock->now());
        $number = $this->orders->save($order);

        return new PlacedOrder($number, $order->totalKopecks);
    }

    /**
     * Validates every position on its own, then merges duplicates by dish id.
     *
     * @param array<string, string> $errors
     *
     * @return array{array<int, int>, array<int, list<int>>} merged quantity per
     *         dish id in order of first appearance, and every request index
     *         at which each dish id appears
     */
    private function parseItems(mixed $raw, array &$errors): array
    {
        if (!is_array($raw) || $raw === [] || !array_is_list($raw) || count($raw) > self::MAX_RAW_ITEMS) {
            $errors['items'] = self::MSG_ITEMS;

            return [[], []];
        }

        $quantities = [];
        $positions = [];
        foreach ($raw as $index => $item) {
            if (!is_array($item)) {
                $errors["items.{$index}"] = self::MSG_ITEM;
                continue;
            }

            $dishId = $item['dishId'] ?? null;
            $quantity = $item['quantity'] ?? null;
            // Strict int checks, no casting: "7" and 7.0 are rejected.
            $dishIdValid = is_int($dishId) && $dishId > 0;
            // Checked per position before merging: otherwise -3 and 10 of
            // the same dish would merge into a valid 7.
            $quantityValid = is_int($quantity)
                && $quantity >= self::MIN_QUANTITY
                && $quantity <= self::MAX_QUANTITY;

            if (!$dishIdValid) {
                $errors["items.{$index}.dishId"] = self::MSG_DISH_INVALID;
            }
            if (!$quantityValid) {
                $errors["items.{$index}.quantity"] = self::MSG_QUANTITY;
            }
            if (!$dishIdValid || !$quantityValid) {
                continue;
            }

            $quantities[$dishId] = ($quantities[$dishId] ?? 0) + $quantity;
            $positions[$dishId][] = $index;
        }

        foreach ($quantities as $dishId => $total) {
            if ($total > self::MAX_QUANTITY) {
                // Reported at the dish's first appearance in the request.
                $errors["items.{$positions[$dishId][0]}.quantity"] = self::MSG_QUANTITY;
            }
        }
        if (count($quantities) > self::MAX_DISTINCT_ITEMS) {
            $errors['items'] = self::MSG_ITEMS;
        }

        return [$quantities, $positions];
    }

    /**
     * @param array<string, string> $errors
     *
     * @return ?string normalised comment, or null when there is none
     */
    private function parseComment(mixed $raw, array &$errors): ?string
    {
        if ($raw === null) {
            return null;
        }
        if (!is_string($raw)) {
            $errors['comment'] = self::MSG_COMMENT;

            return null;
        }

        $comment = trim(str_replace("\r\n", "\n", $raw));
        if ($comment === '') {
            return null;
        }

        // preg_match() returns false on invalid UTF-8, so that is rejected too.
        // Length is checked only on valid UTF-8, where mb_strlen() is meaningful.
        if (preg_match(self::FORBIDDEN_COMMENT_CHARS, $comment) !== 0
            || mb_strlen($comment) > self::MAX_COMMENT_LENGTH
        ) {
            $errors['comment'] = self::MSG_COMMENT;

            return null;
        }

        return $comment;
    }
}
