<?php

declare(strict_types=1);

namespace Tests\Config;

use App\Config\Env;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvTest extends TestCase
{
    private const string FILE_ONLY = 'MENUORDER_ENV_TEST_FILE_ONLY';

    private string $dir;

    /** @var array<string, mixed> */
    private array $serverBackup;

    /** @var array<string, mixed> */
    private array $envBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->envBackup = $_ENV;
        $this->dir = sys_get_temp_dir() . '/menuorder-env-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
        file_put_contents(
            $this->dir . '/.env',
            "DB_HOST=from-dotenv\n" . self::FILE_ONLY . "=from-file\n",
        );
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_ENV = $this->envBackup;
        unlink($this->dir . '/.env');
        rmdir($this->dir);
    }

    public function testComposeVariableIsNotOverwrittenByDotenv(): void
    {
        $fromCompose = Env::get('DB_HOST');

        Env::load($this->dir);

        self::assertSame($fromCompose, Env::get('DB_HOST'));
        self::assertNotSame('from-dotenv', Env::get('DB_HOST'));
    }

    public function testVariableFromDotenvIsLoaded(): void
    {
        Env::load($this->dir);

        self::assertSame('from-file', Env::get(self::FILE_ONLY));
    }

    public function testMissingVariableThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing environment variable: MENUORDER_ENV_TEST_MISSING');

        Env::get('MENUORDER_ENV_TEST_MISSING');
    }
}
