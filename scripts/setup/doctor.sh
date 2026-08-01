#!/usr/bin/env bash
# Verifies local toolchain versions and required ports/services.
source "$(dirname "$0")/../lib/_guard.sh"

errors=0

# set -e is on via _guard.sh; never use bare ((errors++)) when errors=0 (exit status 1).
bump_errors() {
    errors=$((errors + 1))
}

check_cmd() {
    local cmd="$1" min="$2"
    if ! command -v "$cmd" >/dev/null 2>&1; then
        warn "$cmd not found (need $min)"
        bump_errors
        return
    fi
    ok "$cmd $($cmd --version 2>&1 | head -n1)"
}

check_port_listening() {
    local port="$1" label="$2"
    # After `make bootstrap` / services up, in-use is healthy; free is a warn.
    if lsof -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1; then
        ok "Port $port listening ($label)"
    else
        warn "Port $port not listening ($label — start sibling services if needed)"
    fi
}

info "Toolchain"
check_cmd php "8.4.x"
check_cmd composer "2.7+"
check_cmd node "24.x"
check_cmd pnpm "11.x"
check_cmd docker "26+"
check_cmd git "2.40+"

if command -v php >/dev/null 2>&1; then
    php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
    if php -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4 ? 0 : 1);'; then
        ok "PHP $php_version (matches Laravel Cloud 8.4)"
    else
        warn "PHP $php_version — need 8.4.x to match Laravel Cloud"
        bump_errors
    fi
    if php -r 'exit(extension_loaded("redis") ? 0 : 1);' 2>/dev/null; then
        # Prefer extension_loaded() over `php -m | grep -q`: with pipefail, grep -q can
        # close the pipe early and make php exit non-zero even when redis is loaded.
        ok "PHP ext-redis loaded"
    else
        warn "PHP ext-redis missing (required for cache and local queue workers)"
        bump_errors
    fi
fi

if command -v node >/dev/null 2>&1; then
    node_major="$(node -p 'process.versions.node.split(".")[0]')"
    if [[ "$node_major" -eq 24 ]]; then
        ok "Node $(node -v) (matches Laravel Cloud 24)"
    else
        warn "Node $(node -v) — need 24.x to match Laravel Cloud"
        bump_errors
    fi
fi

if command -v pnpm >/dev/null 2>&1; then
    pnpm_version="$(pnpm --version)"
    if [[ "$pnpm_version" == "11.9.0" ]]; then
        ok "pnpm $pnpm_version (matches Laravel Cloud build)"
    else
        warn "pnpm $pnpm_version — need 11.9.0 to match Laravel Cloud build"
        bump_errors
    fi
fi

info "Ports (local services layout)"
check_port_listening 5432 postgres
check_port_listening 6379 redis
check_port_listening 1025 mailpit-smtp
check_port_listening 8025 mailpit-ui
check_port_listening 8000 laravel

info "Docker daemon"
if docker info >/dev/null 2>&1; then
    ok "docker running"
else
    warn "docker not running"
    bump_errors
fi

info "Local infra"
if SERVICES_DIR="$(find_services_dir)"; then
    COMPOSE_FILE="$SERVICES_DIR/docker-compose.yaml"
    if docker compose -f "$COMPOSE_FILE" ps --status running 2>/dev/null | grep -qE 'postgres|redis'; then
        ok "infra services up ($SERVICES_DIR)"
    else
        warn "infra not fully running — run 'make bootstrap' or start sibling services"
    fi
else
    warn "services checkout not found — set BROWSER_SERVICES_DIR or clone ../service"
    bump_errors
fi

[[ "$errors" -eq 0 ]] && ok "All good" || fail "$errors issue(s) found"
