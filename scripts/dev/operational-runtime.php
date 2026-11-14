<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$retry = (int) config('queue.connections.redis.retry_after');
$smtp = (int) config('mail.mailers.smtp.timeout');
if (config('queue.default') !== 'redis' || $retry !== 90 || $smtp < 1 || $smtp >= 60) {
    throw new RuntimeException('Expected Redis retry_after=90 and SMTP timeout between 1 and 59 seconds.');
}
echo "PASS: effective Redis queue retry_after=90 and bounded SMTP timeout (<60).\n";
