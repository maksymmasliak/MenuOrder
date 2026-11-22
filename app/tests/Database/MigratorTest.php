<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Database\ConnectionFactory;
use App\Database\Migrator;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FrozenClock;

final class MigratorTest extends TestCase
{
    private const array FIXTURE_TABLES = ['migrator_test_a', 'migrator_test_b', 'migrator_test_c'];

    private PDO $pdo;
    private string $dir;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->pdo = ConnectionFactory::fromEnv()->create();
        $this->dir = sys_get_temp_dir() . '/migrator_test_' . bin2hex(random_bytes(6));
        mkdir($this->dir);
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-11-22 10:00:00'));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);

        foreach (self::FIXTURE_TABLES as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
        $this->pdo->exec("DELETE FROM migrations WHERE name LIKE '9%'");
    }

    public function testAppliesPendingFilesInOrderAndRecordsThem(): void
    {
        $this->write('902_second.sql', 'CREATE TABLE migrator_test_b (id INT);');
        $this->write('901_first.sql', <<<'SQL'
            -- full-line comments are skipped
            CREATE TABLE migrator_test_a (id INT PRIMARY KEY);
            INSERT INTO migrator_test_a (id) VALUES (1);
            SQL);

        $applied = $this->migrator()->migrate();

        self::assertSame(['901_first.sql', '902_second.sql'], $applied);
        self::assertSame(1, $this->pdo->query('SELECT COUNT(*) FROM migrator_test_a')->fetchColumn());
        self::assertSame(
            '2026-11-22 10:00:00',
            $this->pdo->query("SELECT applied_at FROM migrations WHERE name = '901_first.sql'")->fetchColumn(),
        );
    }

    public function testSecondRunAppliesNothing(): void
    {
        $this->write('901_first.sql', 'CREATE TABLE migrator_test_a (id INT);');

        $this->migrator()->migrate();

        self::assertSame([], $this->migrator()->migrate());
    }

    public function testFailedStatementIsReportedAndFileIsNotRecorded(): void
    {
        $this->write('901_broken.sql', <<<'SQL'
            CREATE TABLE migrator_test_a (id INT);
            CREATE TABLE migrator_test_a (id INT);
            CREATE TABLE migrator_test_c (id INT);
            SQL);

        try {
            $this->migrator()->migrate();
            self::fail('Expected migration failure.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('901_broken.sql failed at statement 2 of 3', $e->getMessage());
            self::assertNotNull($e->getPrevious());
        }

        self::assertFalse(
            $this->pdo->query("SELECT 1 FROM migrations WHERE name = '901_broken.sql'")->fetchColumn(),
        );
        // DDL is not transactional: statement 1 stays applied, statement 3 never ran.
        self::assertSame(1, $this->tableCount('migrator_test_a'));
        self::assertSame(0, $this->tableCount('migrator_test_c'));
    }

    public function testRejectsInvalidFileName(): void
    {
        $this->write('901-Bad Name.sql', 'SELECT 1;');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid migration file name: 901-Bad Name.sql');

        $this->migrator()->migrate();
    }

    public function testProjectMigrationsAreAlreadyApplied(): void
    {
        $migrator = new Migrator($this->pdo, dirname(__DIR__, 2) . '/migrations', $this->clock);

        self::assertSame([], $migrator->migrate());
    }

    private function migrator(): Migrator
    {
        return new Migrator($this->pdo, $this->dir, $this->clock);
    }

    private function write(string $name, string $sql): void
    {
        file_put_contents($this->dir . '/' . $name, $sql);
    }

    private function tableCount(string $table): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t',
        );
        $stmt->execute(['t' => $table]);

        return (int) $stmt->fetchColumn();
    }
}
