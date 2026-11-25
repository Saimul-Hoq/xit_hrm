<?php

declare(strict_types=1);

// TEMPORARY - delete after Step 6. CLI only.
// Usage: printf '%s' "$ADMIN_PW" | php step6-test.php admin@your-email

use App\Core\Clock;
use App\Core\Config;
use App\Core\Database;

/** @var Config $config */
$config = require __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('CLI only.');
}

$email    = $argv[1] ?? '';
$password = (string) stream_get_contents(STDIN);

$clock    = new Clock($config->get('app.timezone'));
$database = new Database(
    host:     $config->get('database.host'),
    port:     $config->get('database.port'),
    name:     $config->get('database.name'),
    user:     $config->get('database.user'),
    password: $config->get('database.password'),
    charset:  $config->get('database.charset'),
    clock:    $clock,
);

$connection = $database->connection();

echo "Tables:\n";
foreach ($connection->query('SHOW TABLES')->fetch_all() as [$table]) {
    echo "  {$table}\n";
}

$departments = array_column($connection->query('SELECT name FROM departments ORDER BY id')->fetch_all(), 0);
echo "\nDepartments: ", implode(', ', $departments), "\n";

// The project's first prepared statement: the email is NEVER concatenated into SQL.
$statement = $connection->prepare('SELECT id, name, password FROM admins WHERE email = ?');
$statement->bind_param('s', $email);
$statement->execute();
$admin = $statement->get_result()->fetch_assoc();

if ($admin === null) {
    echo "\nNo admin found with email '{$email}'.\n";
    exit(1);
}

echo "\nAdmin found      : #{$admin['id']} {$admin['name']}\n";
echo 'Password matches : ', password_verify($password, $admin['password']) ? 'YES' : 'NO', "\n";
echo 'Hash algorithm   : ', password_get_info($admin['password'])['algoName'], "\n";