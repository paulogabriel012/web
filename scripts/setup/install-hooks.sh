#!/usr/bin/env bash
# Installs husky git hooks. Run after fresh clone.
source "$(dirname "$0")/../lib/_guard.sh"

info "Installing husky hooks"
pnpm exec husky
chmod +x .husky/pre-commit .husky/commit-msg .husky/pre-push 2>/dev/null || true
ok "Hooks installed"
