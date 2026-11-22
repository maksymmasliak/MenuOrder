<?php

declare(strict_types=1);

// Creates an admin account.
// Usage: php bin/create-admin.php email [password]
// Without the password argument it is read from STDIN (hidden on a terminal),
// so it does not end up in shell history or the process list.

use App\Clock\SystemClock;
use App\Database\ConnectionFactory;

require dirname(__DIR__) . '/src/bootstrap.php';

const MIN_PASSWORD_LENGTH = 12;
const MYSQL_DUPLICATE_KEY = 1062;

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function readHidden(string $prompt): string
{
    echo $prompt;
    shell_exec('stty -echo');
    try {
        $line = fgets(STDIN);
    } finally {
        shell_exec('stty echo');
        echo "\n";
    }

    return $line === false ? '' : rtrim($line, "\r\n");
}

function readPassword(): string
{
    if (!stream_isatty(STDIN)) {
        $line = fgets(STDIN);

        return $line === false ? '' : rtrim($line, "\r\n");
    }

    $password = readHidden('Password: ');
    if ($password !== readHidden('Repeat password: ')) {
        fail('Passwords do not match.');
    }

    return $password;
}

$email = mb_strtolower(trim((string) ($argv[1] ?? '')));
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fail('Usage: php bin/create-admin.php email [password] (a valid email is required).');
}

$password = isset($argv[2]) && $argv[2] !== '' ? $argv[2] : readPassword();
if (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
    fail(sprintf('Password must be at least %d characters.', MIN_PASSWORD_LENGTH));
}

try {
    $pdo = ConnectionFactory::fromEnv()->create();
    $pdo->prepare(
        'INSERT INTO admins (email, password_hash, created_at) VALUES (:email, :hash, :created_at)',
    )->execute([
        'email' => $email,
        'hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => new SystemClock()->now()->format('Y-m-d H:i:s'),
    ]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? null) === MYSQL_DUPLICATE_KEY) {
        fail(sprintf('Admin with email %s already exists.', $email));
    }
    fail('Database error: ' . $e->getMessage());
} catch (Throwable $e) {
    fail($e->getMessage());
}

echo sprintf("Admin %s created.\n", $email);
