#!/bin/sh
set -eu
umask 022
out=/home/builder/out
mkdir -p "$out/evidence"
# Go's authenticated checksum database binds the exact source module version.
# This fetches source/data only; no downloaded project code executes yet.
go version
node --version
npm --version
printf '%s\n' 'module notification-hub.invalid/source-fetch' 'go 1.26.0' > go.mod
go mod download -json github.com/axllent/mailpit@v1.32.0 > "$out/evidence/source-module.json"
source_dir="$GOPATH/pkg/mod/github.com/axllent/mailpit@v1.32.0"
test -f "$source_dir/go.mod" && test -f "$source_dir/package-lock.json"
cp -R "$source_dir/." ./source
chmod -R u+w ./source
cd source
# Exactly one direct module update; readonly builds reject unreviewed graph edits.
cp go.mod "$out/evidence/go.mod.before"
cp go.sum "$out/evidence/go.sum.before"
go mod edit -require=golang.org/x/net@v0.62.0
go mod download golang.org/x/net@v0.62.0
go mod download
go mod verify
cp go.mod "$out/evidence/go.mod"
cp go.sum "$out/evidence/go.sum"
# Install locked frontend dependencies without lifecycle hooks, then audit before
# invoking the reviewed upstream package/test scripts. High/critical blocks build.
npm ci --ignore-scripts --no-audit --no-fund
if ! npm audit --ignore-scripts --audit-level=high > "$out/evidence/npm-audit.txt" 2>&1; then
    cat "$out/evidence/npm-audit.txt"
    echo 'Frontend audit failed; review findings or query error before building.' >&2
    exit 1
fi
npm test
npm run package
# Upstream Dockerfile uses this exact license tool; no unpinned go install/latest.
mkdir -p internal/licenses
go run github.com/google/go-licenses/v2@v2.0.1 report ./... \
    --template .github/third-party-licenses.tpl --ignore github.com/axllent/mailpit \
    > internal/licenses/third-party.txt
go list -mod=readonly -deps ./... > "$out/evidence/compiled-packages.txt"
if grep -E '^golang.org/x/crypto/openpgp(/|$)' "$out/evidence/compiled-packages.txt"; then
    echo 'Unmaintained OpenPGP package remains in build graph; review GO-2026-5932.' >&2
    exit 1
fi
CGO_ENABLED=0 go test -mod=readonly ./...
CGO_ENABLED=0 go build -mod=readonly -trimpath -buildvcs=false \
    -ldflags '-s -w -X github.com/axllent/mailpit/config.Version=1.32.0+nh.xnet0.62.0' \
    -o "$out/mailpit" .
go version -m "$out/mailpit" > "$out/evidence/binary-modules.txt"
awk '$1 == "dep" && $2 == "golang.org/x/net" && $3 == "v0.62.0" { found=1 } END { exit !found }' \
    "$out/evidence/binary-modules.txt"
cp package-lock.json "$out/evidence/package-lock.json"
cp LICENSE "$out/evidence/LICENSE"
printf 'Go: %s\nNode: %s\nnpm: %s\n' "$(go version)" "$(node --version)" "$(npm --version)" > "$out/evidence/build-tools.txt"
sha256sum "$out/mailpit" > "$out/evidence/binary-sha256.txt"
(cd "$out/evidence" && for evidence in ./*; do
    [ "$evidence" = ./SHA256SUMS ] && continue
    sha256sum "$evidence"
done > SHA256SUMS)
echo 'PASS: locked Mailpit source, audited frontend, upstream tests and x/net 0.62.0 binary; no OpenPGP packages in build graph.'
