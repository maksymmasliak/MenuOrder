<?php

declare(strict_types=1);

namespace App\Service;

use InvalidArgumentException;

final readonly class TableSigner
{
    private const int MIN_SECRET_LENGTH = 32;
    private const int SIGNATURE_LENGTH = 16;

    public function __construct(
        #[\SensitiveParameter]
        private string $secret,
    ) {
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Table signer secret must be at least %d characters long.',
                self::MIN_SECRET_LENGTH,
            ));
        }
    }

    public function sign(int $tableId): string
    {
        $message = 'table:' . $tableId;
        $hash = hash_hmac('sha256', $message, $this->secret);

        return substr($hash, 0, self::SIGNATURE_LENGTH);
    }

    public function verify(int $tableId, string $sig): bool
    {
        return hash_equals($this->sign($tableId), $sig);
    }
}
