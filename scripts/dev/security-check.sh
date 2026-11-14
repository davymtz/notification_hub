#!/bin/sh
# Collect current evidence; inconclusive checks always prevent acceptance.
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
cd "$project_dir"
umask 077
report_dir=$(mktemp -d "$project_dir/artifacts-security.XXXXXXXX")
collection_status=0
collect() {
    output=$1
    shift
    if "$@" > "$report_dir/$output"; then
        printf 'COLLECTED: %s\n' "$output"
    else
        printf 'INCONCLUSIVE: %s\n' "$output" >&2
        collection_status=1
    fi
}
sh "$script_dir/security-policy-test.sh" || collection_status=1
for service in app worker; do
    collect "$service-runtime.json" docker compose exec -T "$service" php < "$script_dir/runtime-inventory.php"
    collect "$service-trust.json" docker compose exec -T "$service" php < "$script_dir/tls-trust-review.php"
    collect "$service-tls-chains.json" docker compose exec -T "$service" php < "$script_dir/tls-chain-check.php"
    collect "$service-composer-tls.json" docker compose exec -T "$service" php < "$script_dir/composer-tls-check.php"
done
collect composer-audit.json docker compose exec -T app composer --no-plugins --no-scripts audit --locked --format=json
collect composer-ca.json docker compose exec -T app php < "$script_dir/composer-ca-review.php"
collect composer-policy.json docker compose exec -T app php < "$script_dir/composer-policy.php"
collect high-findings-review.json sh "$script_dir/review-high-findings.sh"
collect fpm-settings.txt docker compose exec -T app sh -c 'php-fpm -i | awk '\''/^session.upload_progress.enabled =>|^openssl.cafile =>|^curl.cainfo =>/ { print }'\'''
# Read installation identity/integrity evidence on the actual Docker Desktop host.
# Merely collecting a signature does not establish the expected publisher identity.
if [ "$(uname -s)" = Darwin ]; then
    scout_path=$(docker info --format '{{range .ClientInfo.Plugins}}{{if eq .Name "scout"}}{{.Path}}{{end}}{{end}}') || scout_path=
    if [ -n "$scout_path" ] && [ -f "$scout_path" ]; then
        printf '%s\n' "$scout_path" > "$report_dir/scout-path.txt"
        collect scout-binary-sha256.txt shasum -a 256 "$scout_path"
        if ! codesign --verify --strict --verbose=2 "$scout_path" > "$report_dir/scout-signature-verification.txt" 2>&1; then
            collection_status=1
            echo 'INCONCLUSIVE: Scout signature verification failed; preserve the report.' >&2
        fi
        codesign --display --verbose=4 "$scout_path" > "$report_dir/scout-signature-identity.txt" 2>&1 || collection_status=1
    else
        echo 'INCONCLUSIVE: Docker did not expose a readable Scout binary path.' >&2
        collection_status=1
    fi
fi
scan_status=0
NH_SECURITY_REPORT_DIR="$report_dir" sh "$script_dir/scan-images.sh" || scan_status=$?
case "$scan_status" in 0|2) ;; *) collection_status=1;; esac
# This container reads only public checks and collected reports, with no network.
policy_status=0
docker run --rm --user "$(id -u):$(id -g)" --network none --read-only --cap-drop ALL --security-opt no-new-privileges \
    --mount "type=bind,source=$script_dir,target=/checks,readonly" \
    --mount "type=bind,source=$report_dir,target=/reports,readonly" \
    --entrypoint php notification-hub-php:dev /checks/security-evaluate.php || policy_status=$?
printf '\nSecurity evidence: %s\n' "$report_dir"
if [ "$collection_status" -ne 0 ] || [ "$policy_status" -ne 0 ]; then
    echo 'Security acceptance is incomplete; see explicit failures and pending controls above.' >&2
    exit 1
fi
echo 'PASS: security acceptance.'
