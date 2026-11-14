<?php

// Only invoked against the temporary backend created by verify.sh.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$statePath = 'storage/framework/verification-state.json';
$db = $app['db']->connection();
$redis = $app['redis']->connection();
$mode = getenv('VERIFY_MODE');
if ($mode === 'capture') {
    $marker = bin2hex(random_bytes(20));
    $key = 'development-verification:'.$marker;
    $db->statement('CREATE TABLE IF NOT EXISTS development_verification (marker TEXT PRIMARY KEY)');
    $db->insert('INSERT INTO development_verification (marker) VALUES (?)', [$marker]);
    $redis->setex($key, 3600, $marker);
    $state = ['marker' => $marker, 'redis_key' => $key,
        'env_hash' => hash_file('sha256', '.env'),
        'key_hash' => hash('sha256', (string) config('app.key'))];
    if (file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('Cannot save verification state.');
    }
    echo "Verification markers created in isolated SQLite and Redis.\n";
} elseif ($mode === 'assert') {
    $state = json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
    if (!hash_equals($state['env_hash'], hash_file('sha256', '.env'))
        || !hash_equals($state['key_hash'], hash('sha256', (string) config('app.key')))
        || $redis->get($state['redis_key']) !== $state['marker']
        || !$db->select('SELECT marker FROM development_verification WHERE marker = ?', [$state['marker']])) {
        throw new RuntimeException('Configuration, key or persistent marker changed unexpectedly.');
    }
    echo "PASS: .env, APP_KEY, SQLite and Redis preserved.\n";
} else {
    throw new RuntimeException('VERIFY_MODE must be capture or assert.');
}
