#!/usr/bin/env bash
set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repository_root"

docker compose -f compose.build.yaml build
docker compose -f compose.build.yaml run --rm crm-build
