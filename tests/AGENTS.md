# AGENTS.md — tests (web)

**Adds to:** repository root `AGENTS.md`.  
**Load when:** adding or changing tests under `tests/`. For feature work, also
load `app/AGENTS.md`.

## Suite layout

| Suite    | Path                     | Purpose                                                   |
| -------- | ------------------------ | --------------------------------------------------------- |
| Feature  | `tests/Backend/Feature/` | HTTP, auth, settings, API + DB refresh                    |
| Unit     | `tests/Backend/Unit/`    | Pure units (prefer no DB)                                 |
| Arch     | `tests/Backend/Arch/`    | Pest architectural rules (layers, finality, strict types) |
| Frontend | `tests/Frontend/`        | Vitest (jsdom) for React components                       |
| E2E      | `tests/e2e/`             | Playwright smoke flows                                    |

Test runner is **Pest** (configured in `phpunit.xml`); `tests/Pest.php`
extends the base Laravel test case for Feature/Unit/Arch suites.

## Backend (Pest)

- PostgreSQL only — never SQLite (use the `service` Postgres; `phpunit.xml`
  points at `browser_testing`, created by `scripts/db/create-test-database.sh`).
- Feature tests refresh DB via `RefreshDatabase`.
- Factories over hand-built models where available.
- New code paths need at least one appropriate test.
- API contract tests should assert the JSON shape the desktop consumes
  (matches `openapi.json`).
- Coverage floor: `--min=60` (`make test`); needs pcov/xdebug locally.

```bash
vendor/bin/pest --compact
vendor/bin/pest tests/Backend/Feature/DashboardTest.php
vendor/bin/pest --filter=SomeName
make test                # pest --min=60
make check               # agents-audit + lint + full Pest suite
```

Prefer host-native commands (not Sail) unless the user asks for containers.

## HTTP mocking

```php
Http::fake([
    'api.stripe.com/*' => Http::response([...]),
]);
```

Never hit real provider APIs (Stripe, etc.) from tests.

## Arch rules

`tests/Arch/ArchitectureTest.php` enforces: strict types on every `app/` file,
final controllers/actions, layer boundaries (controllers do not touch DB
directly, form requests extend `FormRequest`, notifications extend the base
class). When adding a new layer (e.g. Services), extend the arch rules instead
of weakening them.

## Frontend / E2E

- Vitest config lives at `tests/vitest.config.ts` (alias `@` →
  `resources/js`); run with `pnpm test`.
- Playwright smoke specs live at `tests/e2e/`; run with `pnpm e2e` (dev server
  must be up at `http://127.0.0.1:8000`).

## What to test

- Every new code path: at least one focused test.
- Business rules (quotas, plans) as Feature tests against the API.
- Stripe webhook handlers: `Http::fake` + signed-payload simulation.
- Migration changes: covered by feature tests that run against the fresh DB.
