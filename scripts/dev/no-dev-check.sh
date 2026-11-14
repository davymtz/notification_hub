#!/bin/sh
# Production dependency set in an isolated copy; never alters development vendor.
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
command -v docker >/dev/null 2>&1 || { echo "Run on the Docker host." >&2; exit 1; }
check_dir=$(mktemp -d "${TMPDIR:-/tmp}/notification-hub-nodev.XXXXXXXX")
cleanup() { rm -rf "$check_dir"; }
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
tar -C "$project_dir/project_back" --exclude='./vendor' --exclude='./node_modules' \
    --exclude='./.env' --exclude='./.env.*' --exclude='./auth.json' \
    --exclude='./storage' --exclude='./bootstrap/cache' --exclude='*.sqlite*' \
    --exclude='./.phpunit.cache' --exclude='./.git' -cf - . | tar -C "$check_dir" -xf -
mkdir -p "$check_dir/storage/app" "$check_dir/storage/framework/cache/data" \
    "$check_dir/storage/framework/sessions" "$check_dir/storage/framework/views" \
    "$check_dir/storage/logs" "$check_dir/bootstrap/cache"
docker run --rm -i --read-only --cap-drop ALL --security-opt no-new-privileges \
    --tmpfs /tmp:rw,nosuid,nodev,mode=1777 \
    --env COMPOSER_HOME=/tmp/composer --env COMPOSER_CACHE_DIR=/tmp/composer-cache \
    --env APP_ENV=testing --env APP_DEBUG=false --env CACHE_STORE=array \
    --env QUEUE_CONNECTION=sync --env MAIL_MAILER=array --env SESSION_DRIVER=array \
    --env DB_CONNECTION=sqlite --env DB_DATABASE=:memory: \
    --mount "type=bind,source=$check_dir,target=/var/www" \
    --workdir /var/www --entrypoint sh notification-hub-php:dev -s <<'CONTAINER'
set -eu
APP_KEY=$(php -r 'echo "base64:".base64_encode(random_bytes(32));')
export APP_KEY
composer --no-plugins --no-scripts validate --strict --no-check-publish
composer --no-plugins --no-scripts audit --locked --format=json
composer --no-plugins --no-scripts install --no-dev --prefer-dist --no-interaction
composer --no-plugins --no-scripts check-platform-reqs --no-dev
composer --no-plugins dump-autoload --no-dev --optimize --no-interaction
php artisan about --only=environment
php artisan route:list --path=v1/notifications/dispatch
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$registry = $app->make(App\Services\NotificationRegistry::class);
foreach (['email', 'slack', 'telegram'] as $channel) {
    if (!$registry->resolve($channel) instanceof App\Contracts\NotificationChannel) {
        throw new RuntimeException('Cannot resolve channel: '.$channel);
    }
}
if (is_file('vendor/bin/phpunit') || class_exists(PHPUnit\Framework\TestCase::class)) {
    throw new RuntimeException('Development test dependency found in no-dev installation.');
}
echo "PASS: no-dev installation boots, preserves dispatch route and resolves all channels.\n";
PHP
CONTAINER
