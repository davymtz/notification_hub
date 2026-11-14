#!/bin/sh
# Read-only security inventory; no phpinfo dump, .env contents or Docker config dump.
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
cd "$project_dir"
for service in app worker; do
    printf '\nRuntime inventory: %s\n' "$service"
    docker compose exec -T "$service" php < "$script_dir/runtime-inventory.php"
done
printf '\nFPM configuration (selected public settings only):\n'
docker compose exec -T app sh -c 'php-fpm -i 2>/dev/null | grep -E "^(PHP Version|session.upload_progress.enabled|openssl.cafile|openssl.capath|curl.cainfo)"'
for service in app nginx redis mailpit; do
    printf '\nImage and system packages: %s\n' "$service"
    image_id=$(docker compose images -q "$service")
    docker image inspect --format 'ID={{.Id}} Architecture={{.Architecture}} RepoDigests={{json .RepoDigests}}' "$image_id"
    docker compose exec -T "$service" apk info -v
 done
printf '\nComposer trust configuration:\n'
docker compose exec -T app composer --no-plugins --no-scripts config secure-http
docker compose exec -T app composer --no-plugins --no-scripts config disable-tls
docker compose exec -T app composer --no-plugins --no-scripts config allow-plugins
printf '\nInventory collected. This is not a vulnerability or TLS-chain acceptance test.\n'
