#!/usr/bin/env bash
# Greps Laravel log files for a pattern.
# Usage: ./scripts/ops/grep-logs.sh PATTERN
source "$(dirname "$0")/../lib/_guard.sh"

PATTERN="${1:-}"
if [[ -z "$PATTERN" ]]; then
    fail "Usage: make grep-logs PATTERN=foo"
fi

rg --color=auto -i "$PATTERN" "$ROOT_DIR/storage/logs" || {
    warn "No matches in $ROOT_DIR/storage/logs"
    exit 1
}
