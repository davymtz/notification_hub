#!/bin/sh
# Integration verification in a temporary backend, project and data volume.
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
command -v docker >/dev/null 2>&1 || { echo "Run on the Docker host." >&2; exit 1; }
verify_dir=$(mktemp -d "${TMPDIR:-/tmp}/notification-hub-verify.XXXXXXXX")
verify_project="nhverify-$(date +%s)-$$"
export BACKEND_PATH="$verify_dir/backend"
# Override local development port settings; Docker assigns free host ports.
export HTTP_PORT=0 MAIL_UI_PORT=0
unset COMPOSE_FILE COMPOSE_PROFILES
compose() {
    docker compose --project-directory "$project_dir" -f "$project_dir/docker-compose.yml" -p "$verify_project" "$@"
}
cleanup() {
    verify_status=$?
    trap - EXIT INT TERM
    if [ "$verify_status" -ne 0 ]; then
        compose ps -a || true
        compose logs --tail=30 app worker nginx || true
    fi
    # This project was created exclusively here; never remove development volumes.
    if compose down --volumes --remove-orphans; then
        rm -rf "$verify_dir"
    else
        echo "Cleanup incomplete; temporary directory preserved: $verify_dir" >&2
        verify_status=1
    fi
    exit "$verify_status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
mkdir -p "$BACKEND_PATH"
tar -C "$project_dir/project_back" --exclude='./vendor' --exclude='./node_modules' \
    --exclude='./.env' --exclude='./.env.*' --exclude='./auth.json' \
    --exclude='./storage' --exclude='./bootstrap/cache' --exclude='*.sqlite*' \
    --exclude='./.phpunit.cache' --exclude='./.git' -cf - . | tar -C "$BACKEND_PATH" -xf -
(umask 077; cp "$project_dir/project_back/.env.example" "$BACKEND_PATH/.env")
mkdir -p "$BACKEND_PATH/storage/app" "$BACKEND_PATH/storage/framework/cache/data" \
    "$BACKEND_PATH/storage/framework/sessions" "$BACKEND_PATH/storage/framework/views" \
    "$BACKEND_PATH/storage/logs" "$BACKEND_PATH/bootstrap/cache"
compose config --quiet
compose run --rm --no-deps -T app composer --no-plugins --no-scripts validate --strict --no-check-publish
compose run --rm --no-deps -T app composer --no-plugins --no-scripts audit --locked --format=json
compose run --rm --no-deps -T app composer --no-plugins --no-scripts install --prefer-dist --no-interaction
compose run --rm --no-deps -T app composer --no-plugins dump-autoload --optimize --no-interaction
compose run --rm --no-deps -T app php < "$script_dir/prepare-runtime.php"
compose up -d --wait --wait-timeout 180 redis mailpit
compose run --rm --no-deps -T app php artisan migrate --no-interaction
compose up -d --wait --wait-timeout 180 app worker nginx
compose exec -T app php < "$script_dir/mailpit-ui-check.php"
compose exec -T app php < "$script_dir/smoke-mail.php"
compose exec -T app php < "$script_dir/operational-runtime.php"
# Inspect actual bindings without rendering application environment/secrets.
for service_port in nginx:80 mailpit:8025; do
    service=${service_port%:*}
    port=${service_port#*:}
    binding=$(compose port "$service" "$port")
    case "$binding" in 127.0.0.1:*) ;; *) echo "FAIL: non-loopback publication for $service" >&2; exit 1;; esac
done
for service in app worker redis; do
    container=$(compose ps -q "$service")
    bindings=$(docker inspect --format '{{json .HostConfig.PortBindings}}' "$container")
    case "$bindings" in '{}'|null) ;; *) echo "FAIL: unexpected host publication for $service" >&2; exit 1;; esac
done
compose exec -T -e VERIFY_MODE=capture app php < "$script_dir/persistence-probe.php"
# Repeat preparation while preserving .env, key and existing SQLite.
compose run --rm --no-deps -T app php < "$script_dir/prepare-runtime.php"
compose run --rm --no-deps -T app php artisan migrate --no-interaction
compose exec -T -e VERIFY_MODE=assert app php < "$script_dir/persistence-probe.php"
# Wait for the AOF everysec fsync policy before orderly service shutdown.
sleep 2
compose down
compose up -d --wait --wait-timeout 180
compose exec -T -e VERIFY_MODE=assert app php < "$script_dir/persistence-probe.php"
# A running HTTP service alone must not pass the delivery check without a worker.
compose stop worker
worker_container=$(compose ps -a -q worker)
worker_exit=$(docker inspect --format '{{.State.ExitCode}}' "$worker_container")
[ "$worker_exit" = 0 ] || { echo "FAIL: worker did not stop gracefully (exit $worker_exit)." >&2; exit 1; }
if compose exec -T -e SMOKE_TIMEOUT_SECONDS=5 app php < "$script_dir/smoke-mail.php"; then
    echo "FAIL: delivery succeeded without the project's worker." >&2
    exit 1
else
    negative_status=$?
    if [ "$negative_status" -ne 2 ]; then
        echo "FAIL: negative delivery check failed for a reason other than delivery timeout." >&2
        exit 1
    fi
fi
compose up -d --wait --wait-timeout 180 worker
compose exec -T app php < "$script_dir/smoke-mail.php"
# HTTP health must detect loss of its FPM dependency, not just a live nginx PID.
compose stop app
nginx_container=$(compose ps -q nginx)
attempt=0
while :; do
    health=$(docker inspect --format '{{.State.Health.Status}}' "$nginx_container")
    [ "$health" = unhealthy ] && break
    [ "$attempt" -lt 90 ] || { echo "FAIL: nginx did not detect stopped FPM within the 180-second polling budget." >&2; exit 1; }
    attempt=$((attempt + 1))
    sleep 2
done
compose up -d --wait --wait-timeout 180 app worker nginx
compose exec -T app php < "$script_dir/smoke-mail.php"
echo "PASS: isolated delivery, repeated preparation, persistent restart and stopped-worker negative check."
echo "PASS: loopback-only ports, effective timeouts, graceful worker stop and lost-FPM health detection."
