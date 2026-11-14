#!/bin/sh
# Reevaluate existing evidence after a policy fix, without rescanning images.
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
[ "$#" -eq 1 ] || { echo 'Usage: sh scripts/dev/security-reevaluate.sh artifacts-security.DIRECTORY' >&2; exit 1; }
report_dir=$(CDPATH= cd -- "$1" && pwd -P)
case "$report_dir" in "$project_dir"/artifacts-security.*) ;; *) echo 'Expected a security report directory within this project.' >&2; exit 1;; esac
sh "$script_dir/security-policy-test.sh"
# Add the newly implemented Composer checks only when absent, and only if the
# running service still uses the exact PHP image of this evidence set.
expected_image=$(sed -n 's/^ID=\(sha256:[a-f0-9]*\) Architecture=.*/\1/p' "$report_dir/app-image.txt")
for service in app worker; do
    if [ ! -s "$report_dir/$service-composer-tls.json" ] || ! grep -q '"check_version": 2' "$report_dir/$service-composer-tls.json"; then
        container=$(docker compose --project-directory "$project_dir" -f "$project_dir/docker-compose.yml" ps -q "$service")
        [ -n "$container" ] || { echo "Missing running service: $service" >&2; exit 1; }
        actual_image=$(docker inspect --format '{{.Image}}' "$container")
        [ "$actual_image" = "$expected_image" ] || { echo 'Image changed; collect a fresh security report instead.' >&2; exit 1; }
        if [ -s "$report_dir/$service-composer-tls.json" ]; then
            cp "$report_dir/$service-composer-tls.json" "$report_dir/$service-composer-tls.previous.json"
        fi
        composer_status=0
        docker compose --project-directory "$project_dir" -f "$project_dir/docker-compose.yml" exec -T "$service" php \
            < "$script_dir/composer-tls-check.php" > "$report_dir/$service-composer-tls.json" || composer_status=$?
        printf 'Composer TLS check %s: exit %s (report saved).\n' "$service" "$composer_status"
    fi
done
docker run --rm --user "$(id -u):$(id -g)" --network none --read-only --cap-drop ALL --security-opt no-new-privileges \
    --mount "type=bind,source=$script_dir,target=/checks,readonly" \
    --mount "type=bind,source=$report_dir,target=/reports,readonly" \
    --entrypoint php notification-hub-php:dev /checks/security-evaluate.php
