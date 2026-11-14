#!/bin/sh
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
cd "$project_dir"
command -v docker >/dev/null 2>&1 || { echo "Docker is required on the host." >&2; exit 1; }
docker compose version
if [ -n "${BACKEND_PATH:-}" ]; then
    echo "setup prepares project_back only; unset BACKEND_PATH and use verify for isolation." >&2
    exit 1
fi
: "${APP_UID:=$(id -u)}"
: "${APP_GID:=$(id -g)}"
export APP_UID APP_GID
if [ ! -f project_back/.env ]; then
    (umask 077; cp project_back/.env.example project_back/.env)
fi
docker compose config --quiet
docker compose build app nginx
# Audit before dependency code is executed. These scripts never rewrite .env.
sh scripts/dev/composer-install-check.sh
docker compose run --rm --no-deps -T app php < scripts/dev/prepare-runtime.php
docker compose up -d --wait --wait-timeout 180 redis mailpit
# Migration is explicit in setup; normal service startup does not migrate.
docker compose run --rm --no-deps -T app php artisan migrate --no-interaction
docker compose up -d --wait --wait-timeout 180 app worker nginx
docker compose ps
# Read actual bindings, including root .env and Docker-assigned ports.
printf '\nAPI: http://%s\nMailpit: http://%s\n' \
    "$(docker compose port nginx 80)" "$(docker compose port mailpit 8025)"
