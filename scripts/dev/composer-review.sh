#!/bin/sh
# Audit metadata before executing any package plugin or project script.
set -eu
command -v docker >/dev/null 2>&1 || { echo "Run this script on the Docker host." >&2; exit 1; }
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
image_ref=notification-hub-php:dev
# No host credentials, SSH agent, Docker socket or home directory are mounted.
# Read-only backend: this step cannot alter composer.json, lockfile, vendor or .env.
docker run --rm --read-only --cap-drop ALL --security-opt no-new-privileges \
    --tmpfs /tmp:rw,nosuid,nodev,mode=1777 \
    --env COMPOSER_HOME=/tmp/composer \
    --env COMPOSER_CACHE_DIR=/tmp/composer-cache \
    --mount "type=bind,source=$project_dir/project_back,target=/var/www,readonly" \
    --workdir /var/www --entrypoint sh "$image_ref" -c '
        set -u
        composer --no-plugins --no-scripts validate --strict --no-check-publish
        validate_status=$?
        composer --no-plugins --no-scripts audit --locked --format=json
        audit_status=$?
        printf "\nvalidate_exit=%s audit_exit=%s\n" "$validate_status" "$audit_status"
        test "$validate_status" -eq 0 && test "$audit_status" -eq 0
    '
