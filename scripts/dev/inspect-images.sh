#!/bin/sh
# Read-only inspection: does not pull or run images, write configuration or print secrets.
set -eu
command -v docker >/dev/null 2>&1 || { echo "Docker is required on the host." >&2; exit 1; }
docker buildx version >/dev/null
if [ "$#" -eq 0 ]; then
    set -- docker.io/library/php:8.4.27-fpm-alpine
fi
for image_ref do
    case "$image_ref" in
        *:latest|*:edge|*:main|*:master) echo "Use a release tag or digest: $image_ref" >&2; exit 1 ;;
        *:*) ;;
        *) echo "Explicit tag or digest required: $image_ref" >&2; exit 1 ;;
    esac
    printf '\nImage: %s\n' "$image_ref"
    docker buildx imagetools inspect "$image_ref"
done
