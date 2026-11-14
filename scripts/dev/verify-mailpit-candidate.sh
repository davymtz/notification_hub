#!/bin/sh
# Test the exact candidate in an isolated project; preserve running development.
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
cd "$project_dir"
[ "$#" -le 1 ] || { echo 'Usage: verify-mailpit-candidate.sh [local-image-reference]' >&2; exit 1; }
candidate_ref=${1:-notification-hub-mailpit:candidate}
# Resolve once; use only this immutable local ID for all following operations.
candidate=$(docker image inspect --format '{{.Id}}' "$candidate_ref")
printf 'Testing candidate: %s\n' "$candidate"
docker run --rm --network none --read-only --cap-drop ALL --security-opt no-new-privileges \
    --entrypoint sh "$candidate" -c '
    set -eu
    cd /usr/share/notification-hub/mailpit-build
    sha256sum -c SHA256SUMS
    binary_hash=$(awk '\''{print $1}'\'' binary-sha256.txt)
    [ "$binary_hash" = 27fbc5717fc3e8cd37e648adf88a1eed62cb5d74224c76f4585907668070b07b ] || { echo "Candidate binary differs from the reviewed and tested build." >&2; exit 1; }
    printf "%s  /mailpit\n" "$binary_hash" | sha256sum -c -
    awk '\''$1 == "dep" && $2 == "golang.org/x/net" && $3 == "v0.62.0" { found=1 } END { exit !found }'\'' binary-modules.txt
    if grep -E "^golang.org/x/crypto/openpgp(/|$)" compiled-packages.txt; then exit 1; fi
    cat build-tools.txt
    echo "PASS: candidate binary and build evidence hashes, corrected x/net and OpenPGP graph check."
'
# Override is limited to child processes; Compose defaults remain upstream.
MAILPIT_IMAGE="$candidate" sh "$script_dir/verify.sh"
umask 077
report_dir=$(mktemp -d "$project_dir/artifacts-security.XXXXXXXX")
docker scout version > "$report_dir/scout-version.txt"
docker image inspect --format 'ID={{.Id}} Architecture={{.Architecture}} RepoDigests={{json .RepoDigests}}' "$candidate" > "$report_dir/mailpit-image.txt"
for evidence in binary-modules.txt source-module.json compiled-packages.txt build-tools.txt; do
    docker run --rm --network none --read-only --cap-drop ALL --entrypoint cat "$candidate" \
        "/usr/share/notification-hub/mailpit-build/$evidence" > "$report_dir/mailpit-$evidence"
done
printf 'started_utc=%s\nservice=mailpit image=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$candidate" > "$report_dir/context.txt"
if ! docker scout sbom --format spdx --output "$report_dir/mailpit.spdx.json" "local://$candidate" > "$report_dir/mailpit-sbom.log" 2>&1; then
    echo "INCONCLUSIVE: candidate SBOM failed. Reports: $report_dir" >&2
    exit 1
fi
scan_status=0
docker scout cves --exit-code --format sarif --output "$report_dir/mailpit-cves.sarif.json" "local://$candidate" > "$report_dir/mailpit-scan.log" 2>&1 || scan_status=$?
[ -s "$report_dir/mailpit-cves.sarif.json" ] || { echo "INCONCLUSIVE: no candidate SARIF. Reports: $report_dir" >&2; exit 1; }
docker run --rm --network none --read-only --cap-drop ALL \
    --mount "type=bind,source=$report_dir,target=/reports,readonly" \
    --workdir /reports --entrypoint sha256sum notification-hub-php:dev \
    mailpit.spdx.json mailpit-cves.sarif.json > "$report_dir/mailpit-sha256.txt"
printf 'finished_utc=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" >> "$report_dir/context.txt"
printf '\nPASS: candidate functional verification.\nReports: %s\nScan exit: %s\n' "$report_dir" "$scan_status"
case "$scan_status" in
    0) echo 'Candidate scan returned no findings; review provenance before activation.';;
    2) echo 'Candidate has advisories to review; development Mailpit has not been replaced.';;
    *) echo 'Candidate scan incomplete.' >&2; exit 1;;
esac
exit "$scan_status"
