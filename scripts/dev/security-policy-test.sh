#!/bin/sh
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
# No dependencies, network, app env or Docker socket inside the test container.
docker run --rm --user "$(id -u):$(id -g)" --network none --read-only --cap-drop ALL --security-opt no-new-privileges \
    --mount "type=bind,source=$script_dir,target=/checks,readonly" \
    --entrypoint sh notification-hub-php:dev -c '
    for check in /checks/*.php; do php -l "$check" >/dev/null || exit 1; done
    exec php /checks/security-policy-test.php
'
