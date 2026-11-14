<?php

// Executed on stdin inside /var/www. Does not boot Laravel or print secrets.
require 'vendor/autoload.php';

$path = '.env';
$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Create project_back/.env from .env.example first.\n");
    exit(1);
}
$values = Dotenv\Dotenv::parse($contents);
foreach (['MAIL_HOST' => 'mailpit', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => '/var/www/database/database.sqlite', 'REDIS_HOST' => 'redis', 'QUEUE_CONNECTION' => 'redis', 'CACHE_STORE' => 'redis'] as $key => $expected) {
    if (($values[$key] ?? null) !== $expected) {
        fwrite(STDERR, "Update project_back/.env: ".$key."=".$expected." (existing configuration preserved).\n");
        exit(1);
    }
}
foreach (['storage/app', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache', 'database'] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
        throw new RuntimeException('Cannot create directory: '.$directory);
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('Directory must be writable by the container user: '.$directory);
    }
}
if (!is_file('database/database.sqlite') && !touch('database/database.sqlite')) {
    throw new RuntimeException('Cannot create SQLite database.');
}
if (!is_writable('database/database.sqlite')) {
    throw new RuntimeException('SQLite database is not writable.');
}
if (empty($values['APP_KEY'])) {
    $line = 'APP_KEY=base64:'.base64_encode(random_bytes(32));
    $updated = preg_match('/^APP_KEY=.*$/m', $contents)
        ? preg_replace('/^APP_KEY=.*$/m', $line, $contents, 1)
        : rtrim($contents).PHP_EOL.$line.PHP_EOL;
    if (file_put_contents($path, $updated, LOCK_EX) === false) {
        throw new RuntimeException('Cannot write APP_KEY.');
    }
    echo "Generated missing APP_KEY.\n";
}
echo "Configuration, writable directories and SQLite are ready; existing key/data preserved.\n";
