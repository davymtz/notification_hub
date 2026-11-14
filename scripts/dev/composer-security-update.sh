#!/bin/sh
# Resolve the new Symfony Mime advisory without installing/executing dependency code.
set -eu
command -v docker >/dev/null 2>&1 || { echo "Run on the Docker host." >&2; exit 1; }
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
docker run --rm --read-only --cap-drop ALL --security-opt no-new-privileges \
    --tmpfs /tmp:rw,nosuid,nodev,mode=1777 \
    --env COMPOSER_HOME=/tmp/composer \
    --env COMPOSER_CACHE_DIR=/tmp/composer-cache \
    --mount "type=bind,source=$project_dir/project_back,target=/var/www" \
    --workdir /var/www --entrypoint sh notification-hub-php:dev -c '
        set -eu
        cp composer.json /tmp/composer.json.before
        cp composer.lock /tmp/composer.lock.before
        committed=0
        restore_metadata() {
            if [ "$committed" -eq 0 ]; then
                cp /tmp/composer.json.before composer.json
                cp /tmp/composer.lock.before composer.lock
                echo "Resolution/audit failed; previous Composer files restored." >&2
            fi
        }
        trap restore_metadata EXIT
        trap "exit 130" INT
        trap "exit 143" TERM
        composer --no-plugins --no-scripts update "symfony/mime:^7.4.22" \
            --with-all-dependencies --minimal-changes --no-install --no-interaction
        composer --no-plugins --no-scripts validate --strict --no-check-publish
        composer --no-plugins --no-scripts audit --locked --format=json
        committed=1
        echo "Symfony Mime update resolved and audited; vendor still needs installation from lockfile."
    '
