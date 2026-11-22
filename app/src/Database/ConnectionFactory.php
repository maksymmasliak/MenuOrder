<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Env;
use PDO;
use Pdo\Mysql;
use PDOException;
use RuntimeException;

final readonly class ConnectionFactory
{
    public function __construct(
        private string $host,
        private int $port,
        private string $dbName,
        private string $user,
        #[\SensitiveParameter]
        private string $password,
    ) {}

    public static function fromEnv(): self
    {
        $port = filter_var(
            Env::get('DB_PORT'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]],
        );

        if ($port === false) {
            throw new RuntimeException('DB_PORT must be an integer between 1 and 65535.');
        }

        return new self(
            Env::get('DB_HOST'),
            $port,
            Env::get('DB_NAME'),
            Env::get('DB_USER'),
            Env::get('DB_PASSWORD'),
        );
    }

    public function create(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->port,
            $this->dbName,
        );

        try {
            return new Mysql($dsn, $this->user, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // Runs on every connect: the session always works in UTC.
                Mysql::ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
            ]);
        } catch (PDOException $e) {
            // No host, user or password in the message: it may reach a response.
            // The original exception stays in $previous for the log.
            throw new RuntimeException('Database connection failed.', 0, $e);
        }
    }
}
