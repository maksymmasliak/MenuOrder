<?php

declare(strict_types=1);

namespace App\Service;

use DomainException;

/**
 * The order breaks R4. Errors are keyed by their path in the request
 * ("items", "items.1.dishId", "comment") and hold messages ready for the guest.
 */
final class OrderValidationException extends DomainException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct('Order validation failed.');
    }
}
