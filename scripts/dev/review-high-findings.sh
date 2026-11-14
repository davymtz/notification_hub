#!/bin/sh
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
cd "$project_dir"
mailpit_container=$(docker compose ps -q mailpit)
[ -n "$mailpit_container" ] || { echo 'Mailpit must be running to bind the review to its actual image.' >&2; exit 1; }
mailpit_image=$(docker inspect --format '{{.Image}}' "$mailpit_container")
# No token, host credential, Docker socket or additional mount is passed to app.
docker compose exec -T -e "NH_REVIEW_MAILPIT_IMAGE=$mailpit_image" app php < "$script_dir/review-high-findings.php"
