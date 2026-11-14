#!/bin/sh
# Install only audited lockfile contents; run reviewed Laravel lifecycle hooks and tests.
set -eu
command -v docker >/dev/null 2>&1 || { echo "Run on the Docker host." >&2; exit 1; }
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
# A clean checkout has no ignored runtime directories. Laravel's Composer hook
# needs these before package:discover; prepare-runtime.php runs after installation.
mkdir -p "$project_dir/project_back/storage/app" \
    "$project_dir/project_back/storage/framework/cache/data" \
    "$project_dir/project_back/storage/framework/sessions" \
    "$project_dir/project_back/storage/framework/views" \
    "$project_dir/project_back/storage/logs" \
    "$project_dir/project_back/bootstrap/cache"
docker run --rm -i --read-only --cap-drop ALL --security-opt no-new-privileges \
    --tmpfs /tmp:rw,nosuid,nodev,mode=1777 \
    --env COMPOSER_HOME=/tmp/composer --env COMPOSER_CACHE_DIR=/tmp/composer-cache \
    --env APP_ENV=testing --env APP_DEBUG=false --env CACHE_STORE=array \
    --env QUEUE_CONNECTION=sync --env MAIL_MAILER=array --env SESSION_DRIVER=array \
    --env DB_CONNECTION=sqlite --env DB_DATABASE=:memory: \
    --mount "type=bind,source=$project_dir/project_back,target=/var/www" \
    --workdir /var/www --entrypoint sh notification-hub-php:dev -s <<'CONTAINER'
set -eu
# Ephemeral testing key: no writes to .env and no secret values in output.
APP_KEY=$(php -r 'echo "base64:".base64_encode(random_bytes(32));')
export APP_KEY
composer --no-plugins --no-scripts validate --strict --no-check-publish
composer --no-plugins --no-scripts audit --locked --format=json
composer --no-plugins --no-scripts install --prefer-dist --no-interaction
composer --no-plugins --no-scripts check-platform-reqs
# Remove only generated PHP manifests that can reference packages already removed.
php <<'PHP'
<?php
foreach (['bootstrap/cache/packages.php', 'bootstrap/cache/services.php', 'bootstrap/cache/config.php'] as $path) {
    if (is_file($path) && !unlink($path)) {
        fwrite(STDERR, "Cannot clear generated cache: ".$path.PHP_EOL);
        exit(1);
    }
}
PHP
# This reviewed project hook executes package:discover. Plugins remain disabled.
composer --no-plugins dump-autoload --optimize --no-interaction
php artisan about --only=environment
php artisan route:list --path=v1/notifications/dispatch
composer --no-plugins --no-scripts why guzzlehttp/guzzle
composer --no-plugins --no-scripts why mockery/mockery
composer --no-plugins --no-scripts why nunomaduro/collision
php artisan test
CONTAINER
