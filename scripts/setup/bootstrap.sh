#!/usr/bin/env bash
# Full first-time setup: deps, .env, keys, infra, migrate, seed.
# Idempotent — safe to re-run.
source "$(dirname "$0")/../lib/_guard.sh"

info "Installing JS deps"
pnpm install

info "Installing PHP deps"
composer install --no-interaction

if [[ ! -f .env && -f .env.example ]]; then
    cp .env.example .env
    ok "Created .env"
fi

php artisan key:generate --ansi --force
php artisan storage:link 2>/dev/null || true

SERVICES_DIR="$(services_dir)"
COMPOSE_FILE="$(services_compose_file)"

info "Starting local infra via $SERVICES_DIR"
docker compose -f "$COMPOSE_FILE" up -d --remove-orphans

info "Waiting for Postgres to accept connections"
until docker compose -f "$COMPOSE_FILE" exec -T postgres pg_isready -U browser >/dev/null 2>&1; do
    sleep 1
done
ok "Postgres ready"

info "Ensuring test database exists"
"$ROOT_DIR/scripts/db/create-test-database.sh"

info "Migrating"
php artisan migrate --force

info "Seeding"
php artisan db:seed --force

info "Installing git hooks"
pnpm exec husky install || true

ok "Setup complete. Run 'make dev'"
