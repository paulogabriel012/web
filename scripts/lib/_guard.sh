#!/usr/bin/env bash
# Shared safety guards for destructive web scripts. Source this file.
# Usage: source "$(dirname "$0")/../lib/_guard.sh"

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

color() { printf "\033[%sm%s\033[0m\n" "$1" "$2"; }
info()  { color "36" "→ $*"; }
ok()    { color "32" "✓ $*"; }
warn()  { color "33" "! $*"; }
fail()  { color "31" "✗ $*"; exit 1; }

# Hard fail (bootstrap / compose helpers).
services_dir() {
    local dir
    dir="$(find_services_dir || true)"
    if [[ -n "$dir" ]]; then
        echo "$dir"
        return 0
    fi

    fail "Local infra not found. Clone the service repository or set BROWSER_SERVICES_DIR."
}

# Soft resolve for doctor / optional tooling (no exit).
find_services_dir() {
    if [[ -n "${BROWSER_SERVICES_DIR:-}" && -f "${BROWSER_SERVICES_DIR}/docker-compose.yaml" ]]; then
        cd "${BROWSER_SERVICES_DIR}" && pwd
        return 0
    fi

    local candidate="$ROOT_DIR/../service"
    if [[ -f "$candidate/docker-compose.yaml" ]]; then
        cd "$candidate" && pwd
        return 0
    fi

    return 1
}

services_compose_file() {
    echo "$(services_dir)/docker-compose.yaml"
}

services_env_file() {
    echo "$(services_dir)/.env"
}

app_env() {
    local env_file="$ROOT_DIR/.env"
    if [[ ! -f "$env_file" ]]; then
        echo "local"
        return
    fi

    grep -E '^APP_ENV=' "$env_file" | head -n1 | cut -d= -f2 | tr -d '"' | tr -d "'" || echo "local"
}

refuse_production() {
    local env
    env="$(app_env)"
    if [[ "$env" == "production" ]]; then
        fail "Refusing to run in production (.env has APP_ENV=production)"
    fi
}

confirm() {
    local prompt="${1:-Are you sure?}"
    if [[ "${FORCE:-0}" == "1" ]]; then
        warn "FORCE=1 set, skipping confirmation"
        return 0
    fi

    read -r -p "$(color 33 "${prompt} [y/N]: ")" reply
    [[ "$reply" =~ ^[Yy]$ ]] || fail "Aborted by user"
}
