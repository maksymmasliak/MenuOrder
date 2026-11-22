<?php

declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;
use RuntimeException;

final class Env
{
    public static function load(string $dir): void
    {
        Dotenv::createImmutable($dir)->safeLoad();
    }

    public static function get(string $key): string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Missing environment variable: %s', $key));
        }

        return $value;
    }
}
