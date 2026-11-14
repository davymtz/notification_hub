#!/bin/sh
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
cd "$project_dir"
docker compose ps -a
for service in app worker; do
    printf '\n%s PHP cURL runtime:\n' "$service"
    docker compose exec -T "$service" php -r 'echo curl_version()["version"], PHP_EOL;'
done
printf '\nNginx running image:\n'
container_id=$(docker compose ps -q nginx)
if [ -z "$container_id" ]; then echo 'Nginx is not running.' >&2; exit 1; fi
docker inspect --format 'Configured={{.Config.Image}} ImageID={{.Image}}' "$container_id"
printf '\nNginx cURL binary:\n'
docker compose exec -T nginx curl --version
printf '\nNginx APK packages (unfiltered):\n'
docker compose exec -T nginx apk info -v
