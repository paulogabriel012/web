#!/usr/bin/env bash
# Drop + migrate + seed the local database (DEV ONLY).
source "$(dirname "$0")/../lib/_guard.sh"

refuse_production
confirm "Drop the local database and re-migrate with seed? (FORCE=1 skips)"

php artisan migrate:fresh --seed --force --no-interaction
ok "Database reset complete"
