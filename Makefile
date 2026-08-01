.DEFAULT_GOAL := help

.PHONY: help bootstrap doctor hooks dev test test-frontend e2e lint lint-php lint-js fmt build check wayfinder \
        migrate-round-trip db-fresh grep-logs agents-audit

help: ## List available web targets
	@awk 'BEGIN { printf "\nUsage:\n  make \033[36m<target>\033[0m\n\nTargets:\n" } /(^|^[a-zA-Z_\\:-]+):.*##/ { line=$$0; gsub(/\\:/, "@@", line); split(line, parts, ":.*##"); gsub(/@@/, ":", parts[1]); printf "  \033[36m%-22s\033[0m %s\n", parts[1], parts[2] }' $(MAKEFILE_LIST)

bootstrap: ## Install deps, start local infra, migrate, and seed
	./scripts/setup/bootstrap.sh

doctor: ## Verify local toolchain and infra prerequisites
	./scripts/setup/doctor.sh

hooks: ## Install husky git hooks
	./scripts/setup/install-hooks.sh

dev: ## Run Laravel, Vite, queue worker, scheduler, and logs
	composer run dev

test: ## Run Pest with the repository coverage floor
	./vendor/bin/pest --min=60

test-frontend: ## Run frontend Vitest tests
	pnpm run test

e2e: ## Run Playwright E2E tests
	pnpm run e2e

lint: lint-php lint-js ## Run PHP and frontend quality checks

lint-php: ## Run Pint, PHPStan, and optional PHP analyzers
	./vendor/bin/pint --test
	./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
	@if [ -x vendor/bin/psalm ]; then ./vendor/bin/psalm --taint-analysis --no-progress; else echo "psalm not installed - skipping"; fi
	@if [ -x vendor/bin/phpmd ]; then php -d error_reporting='E_ALL & ~E_DEPRECATED' ./vendor/bin/phpmd app text phpmd.xml; else echo "phpmd not installed - skipping"; fi

lint-js: ## Run frontend lint, architecture, and type checks
	pnpm run lint:check
	pnpm run arch:frontend
	pnpm run types

fmt: ## Format PHP and frontend resources
	./vendor/bin/pint
	pnpm run format

build: ## Build the Vite frontend assets
	pnpm run build

wayfinder: ## Regenerate Wayfinder TypeScript routes
	composer run wayfinder

migrate-round-trip: ## migrate → rollback → migrate → seed
	php artisan migrate --force --no-interaction \
		&& php artisan migrate:rollback --force --no-interaction \
		&& php artisan migrate --force --no-interaction \
		&& php artisan db:seed --force --no-interaction

db-fresh: ## Drop + migrate + seed local database (DEV)
	./scripts/db/fresh.sh

grep-logs: ## Grep Laravel logs. Usage: make grep-logs PATTERN=foo
	./scripts/ops/grep-logs.sh "$(PATTERN)"

agents-audit: ## Verify AGENTS, hooks, and scripts for stale repository instructions
	./scripts/agents/audit-harness.sh

check: agents-audit lint test ## Run the web app quality gate
