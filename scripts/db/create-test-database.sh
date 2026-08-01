#!/usr/bin/env bash
# Ensures the Postgres test database exists (idempotent).
# Uses the sibling service container; falls back to psql if available.
source "$(dirname "$0")/../lib/_guard.sh"

COMPOSE_FILE="$(services_compose_file)"
DB_NAME="${TEST_DB_NAME:-browser_testing}"

if docker compose -f "$COMPOSE_FILE" ps --status running postgres >/dev/null 2>&1; then
    docker compose -f "$COMPOSE_FILE" exec -T postgres psql -U browser -tc "SELECT 1 FROM pg_database WHERE datname = '$DB_NAME'" | grep -q 1 || \
        docker compose -f "$COMPOSE_FILE" exec -T postgres createdb -U browser "$DB_NAME"
    ok "Test database '$DB_NAME' ready"
else
    if command -v psql >/dev/null 2>&1; then
        PGPASSWORD=browser psql -h 127.0.0.1 -U browser -tc "SELECT 1 FROM pg_database WHERE datname = '$DB_NAME'" | grep -q 1 || \
            PGPASSWORD=browser createdb -h 127.0.0.1 -U browser "$DB_NAME"
        ok "Test database '$DB_NAME' ready (psql)"
    else
        fail "Postgres container not running and psql not found — start sibling services first"
    fi
fi
