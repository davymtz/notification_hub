#!/bin/sh
# Full preparation in a fresh project copy. Never copies local env/vendor/data.
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
command -v docker >/dev/null 2>&1 || { echo 'Run on the Docker host.' >&2; exit 1; }
accept_dir=$(mktemp -d "${TMPDIR:-/tmp}/notification-hub-acceptance.XXXXXXXX")
accept_original_project=${COMPOSE_PROJECT_NAME-}
accept_project="nhaccept-$(date +%s)-$$"
export COMPOSE_PROJECT_NAME="$accept_project" HTTP_PORT=0 MAIL_UI_PORT=0
unset BACKEND_PATH COMPOSE_FILE COMPOSE_PROFILES
compose() {
    docker compose --project-directory "$accept_dir" -f "$accept_dir/docker-compose.yml" -p "$accept_project" "$@"
}
cleanup() {
    accept_status=$?
    trap - EXIT INT TERM
    if compose down --volumes --remove-orphans; then
        rm -rf "$accept_dir"
    else
        echo "Cleanup incomplete; preserved temporary copy: $accept_dir" >&2
        accept_status=1
    fi
    exit "$accept_status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
# Copy only known public tooling and backend inputs; no host dot-directories.
cp "$project_dir/docker-compose.yml" "$project_dir/Makefile" "$project_dir/README.md" "$accept_dir/"
for directory in docker scripts docs; do
    mkdir -p "$accept_dir/$directory"
    tar -C "$project_dir/$directory" -cf - . | tar -C "$accept_dir/$directory" -xf -
done
mkdir -p "$accept_dir/project_back"
tar -C "$project_dir/project_back" --exclude='./vendor' --exclude='./node_modules' \
    --exclude='./.env' --exclude='./.env.*' --exclude='./auth.json' \
    --exclude='./storage' --exclude='./bootstrap/cache' --exclude='*.sqlite*' \
    --exclude='./.phpunit.cache' --exclude='./.git' --exclude='*.key' -cf - . \
    | tar -C "$accept_dir/project_back" -xf -
cp "$project_dir/project_back/.env.example" "$accept_dir/project_back/.env.example"
cd "$accept_dir"
# Two actual setup invocations, rather than just repeating its PHP helper.
sh scripts/dev/setup.sh
compose exec -T -e VERIFY_MODE=capture app php < scripts/dev/persistence-probe.php
sh scripts/dev/setup.sh
compose exec -T -e VERIFY_MODE=assert app php < scripts/dev/persistence-probe.php
sh scripts/dev/no-dev-check.sh
sh scripts/dev/verify.sh
sh scripts/dev/security-policy-test.sh
# Security collection uses the original root so its reports survive copy cleanup.
echo 'PASS: clean-copy setup twice, dev/no-dev bootstrap, integration and security policy fixtures.'
cd "$project_dir"
if [ -n "$accept_original_project" ]; then
    COMPOSE_PROJECT_NAME=$accept_original_project
    export COMPOSE_PROJECT_NAME
else
    unset COMPOSE_PROJECT_NAME
fi
echo 'Running security acceptance on current development services; reports stay in the project root.'
sh scripts/dev/security-check.sh
