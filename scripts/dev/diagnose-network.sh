#!/bin/sh
# Read-only diagnostics. Inspect only status, project identity and network aliases.
set -eu
script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
project_dir=$(CDPATH= cd -- "$script_dir/../.." && pwd)
cd "$project_dir"
docker compose ps -a
for service in app nginx; do
    printf '\nService: %s\n' "$service"
    container_id=$(docker compose ps -a -q "$service")
    if [ -z "$container_id" ]; then
        echo 'No container found for this service in the selected Compose project.'
        continue
    fi
    docker inspect --format 'Name={{.Name}} Status={{.State.Status}} Restarting={{.State.Restarting}} Error={{.State.Error}} Project={{index .Config.Labels "com.docker.compose.project"}} Service={{index .Config.Labels "com.docker.compose.service"}}' "$container_id"
    docker inspect --format '{{range $name, $network := .NetworkSettings.Networks}}Network={{$name}} IP={{$network.IPAddress}} Aliases={{json $network.Aliases}}{{println}}{{end}}' "$container_id"
done
printf '\nRecent PHP service logs:\n'
docker compose logs --tail=40 app
