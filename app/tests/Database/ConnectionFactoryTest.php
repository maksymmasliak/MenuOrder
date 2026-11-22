<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Config\Env;
use App\Database\ConnectionFactory;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConnectionFactoryTest extends TestCase
{
    public function testConnectsToTestDatabase(): void
    {
        $pdo = ConnectionFactory::fromEnv()->create();

        self::assertSame(1, $pdo->query('SELECT 1')->fetchColumn());
        // Guard: tests must never touch the working database.
        self::assertSame('menuorder_test', $pdo->query('SELECT DATABASE()')->fetchColumn());
    }

    public function testSessionTimeZoneIsUtc(): void
    {
        $pdo = ConnectionFactory::fromEnv()->create();

        self::assertSame('+00:00', $pdo->query('SELECT @@session.time_zone')->fetchColumn());
    }

    public function testConnectionAttributes(): void
    {
        $pdo = ConnectionFactory::fromEnv()->create();

        self::assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        self::assertSame(PDO::FETCH_ASSOC, $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
        self::assertFalse($pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
    }

    public function testFailureDoesNotLeakCredentials(): void
    {
        $user = Env::get('DB_USER');
        $password = 'wrong-password-' . bin2hex(random_bytes(4));
        $factory = new ConnectionFactory(
            Env::get('DB_HOST'),
            (int) Env::get('DB_PORT'),
            Env::get('DB_NAME'),
            $user,
            $password,
        );

        try {
            $factory->create();
            self::fail('Expected connection failure.');
        } catch (RuntimeException $e) {
            self::assertSame('Database connection failed.', $e->getMessage());
            self::assertStringNotContainsString($password, $e->getMessage());
            self::assertStringNotContainsString($user, $e->getMessage());
            self::assertNotNull($e->getPrevious());
        }
    }
}
