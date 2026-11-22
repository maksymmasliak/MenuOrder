<?php

declare(strict_types=1);

// Applies pending SQL migrations from migrations/ to the database in DB_NAME.
// Usage: php bin/migrate.php

use App\Clock\SystemClock;
use App\Database\ConnectionFactory;
use App\Database\Migrator;

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $pdo = ConnectionFactory::fromEnv()->create();
    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo sprintf("Database: %s\n", is_string($database) ? $database : '?');

    $applied = new Migrator($pdo, dirname(__DIR__) . '/migrations', new SystemClock())->migrate();

    if ($applied === []) {
        echo "Nothing to migrate.\n";
    }
    foreach ($applied as $name) {
        echo sprintf("Applied: %s\n", $name);
    }
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("Migration failed: %s\n", $e->getMessage()));
    exit(1);
}
