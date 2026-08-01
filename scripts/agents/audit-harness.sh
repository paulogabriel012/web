#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

failures=0

check_for_stale_pattern() {
    local pattern="$1"
    local description="$2"
    shift 2

    local matches
    matches="$(rg -n --fixed-strings --glob '!scripts/agents/audit-harness.sh' "$pattern" "$@" 2>/dev/null || true)"

    if [[ -n "$matches" ]]; then
        printf 'Stale harness instruction found: %s\n' "$description" >&2
        printf '%s\n\n' "$matches" >&2
        failures=$((failures + 1))
    fi
}

check_for_stale_regex() {
    local pattern="$1"
    local description="$2"
    shift 2

    local matches
    matches="$(rg -n --glob '!scripts/agents/audit-harness.sh' "$pattern" "$@" 2>/dev/null || true)"

    if [[ -n "$matches" ]]; then
        printf 'Stale harness instruction found: %s\n' "$description" >&2
        printf '%s\n\n' "$matches" >&2
        failures=$((failures + 1))
    fi
}

active_harness_files=(
    AGENTS.md
    Makefile
    composer.json
    package.json
    commitlint.config.cjs
    .github
    .husky
    scripts
    docs
    app
    resources/js
)

check_for_stale_pattern "via Sail" "web development and checks are host-native unless container-specific" .github AGENTS.md Makefile
check_for_stale_pattern "invisiboll_platform" "use host-native artisan/composer commands for local dev" scripts "${active_harness_files[@]}"
check_for_stale_regex "DB_CONNECTION[[:space:]]*value=\"sqlite\"" "tests always use PostgreSQL, never SQLite" phpunit.xml
check_for_stale_regex "\.github/workflows/deploy\.yml" "deploy.yml lives in .github/standby/ until activated — reference standby, not workflows/" AGENTS.md Makefile scripts

if [[ "$failures" -gt 0 ]]; then
    printf 'Agent harness audit failed with %d stale instruction group(s).\n' "$failures" >&2
    exit 1
fi

printf 'Agent harness audit passed.\n'
