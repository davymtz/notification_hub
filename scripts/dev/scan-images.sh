#!/bin/sh
# Inspect local running image IDs; never push images or mount the Docker socket in containers.
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
cd "$project_dir"
umask 077
if [ -n "${NH_SECURITY_REPORT_DIR:-}" ]; then
    report_dir=$NH_SECURITY_REPORT_DIR
    [ -d "$report_dir" ] || { echo 'Security report directory must already exist.' >&2; exit 1; }
    [ ! -e "$report_dir/context.txt" ] || { echo 'Refusing to overwrite an existing scan.' >&2; exit 1; }
else
    report_dir="$(mktemp -d "$project_dir/artifacts-security.XXXXXXXX")"
fi
docker scout version > "$report_dir/scout-version.txt"
cat "$report_dir/scout-version.txt"
printf 'started_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$report_dir/context.txt"
overall_status=0
for service in app nginx redis mailpit; do
    container_id=$(docker compose ps -q "$service")
    if [ -z "$container_id" ]; then
        echo "INCONCLUSIVE: service $service is not running." >&2
        overall_status=1
        continue
    fi
    image_id=$(docker inspect --format '{{.Image}}' "$container_id")
    printf '\nScanning %s: %s\n' "$service" "$image_id"
    printf 'service=%s image=%s\n' "$service" "$image_id" >> "$report_dir/context.txt"
    docker image inspect --format 'ID={{.Id}} Architecture={{.Architecture}} RepoDigests={{json .RepoDigests}}' "$image_id" > "$report_dir/$service-image.txt"
    if ! docker scout sbom --format spdx --output "$report_dir/$service.spdx.json" "local://$image_id" > "$report_dir/$service-sbom.log" 2>&1; then
        echo "$service SBOM: ERROR (see $service-sbom.log)"
        overall_status=1
        continue
    fi
    if docker scout cves --exit-code --format sarif --output "$report_dir/$service-cves.sarif.json" "local://$image_id" > "$report_dir/$service-scan.log" 2>&1; then
        echo "$service: SBOM generated; CVE scan returned 0."
    else
        scan_status=$?
        if [ "$scan_status" -eq 2 ]; then
            echo "$service: CVEs require review (see $service-cves.sarif.json and $service-scan.log)."
            if [ "$overall_status" -eq 0 ]; then overall_status=2; fi
        else
            echo "$service: scan ERROR exit=$scan_status (see $service-scan.log)."
            overall_status=1
        fi
    fi
    # Hash only successfully generated reports; missing SARIF is an incomplete scan.
    set -- "$service.spdx.json"
    if [ -s "$report_dir/$service-cves.sarif.json" ]; then
        set -- "$@" "$service-cves.sarif.json"
    else
        echo "$service: no SARIF produced; vulnerability assessment is INCONCLUSIVE."
        overall_status=1
    fi
    if ! docker run --rm --network none --read-only --cap-drop ALL \
        --mount "type=bind,source=$report_dir,target=/reports,readonly" \
        --workdir /reports --entrypoint sha256sum notification-hub-php:dev \
        "$@" > "$report_dir/$service-sha256.txt"; then
        echo "$service: checksum generation failed."
        overall_status=1
    fi
done
printf 'finished_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" >> "$report_dir/context.txt"
printf '\nReports: %s\nOverall status: %s\n' "$report_dir" "$overall_status"
echo 'Exit 2 means findings to review; exit 1 means incomplete scanning. Neither is a clean security result.'
exit "$overall_status"
