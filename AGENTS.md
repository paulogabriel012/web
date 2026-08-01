# AGENTS.md — web

Agent guide for the **Invisiboll Browser web** repository only: the Laravel
backend. This is not a monorepo root. Sibling repositories live one directory
up and have their own `AGENTS.md` files.

**This root file is the controller.** Always load it first. Child guides
(`app/AGENTS.md`, `tests/AGENTS.md`) **add** to this root for a work area; they
do not replace it.

## What this repository is

The Laravel SaaS backend and **single source of truth** for the Invisiboll
Browser ecosystem: auth, billing, plans, quotas, profile CRUD, dashboard, and
the REST JSON API consumed by the desktop. **Sole owner of business rules** —
the electron browser never decides them.

**Stack:** PHP 8.4, Laravel 13, Fortify (auth, JWT curto + refresh), Inertia v3

- React 19 + TypeScript, Tailwind 4, shadcn/ui, Laravel AI SDK, Sanctum,
  Wayfinder, Pest, Larastan, Pint, Psalm, PHPMD, ESLint, Prettier,
  dependency-cruiser, Vitest, Playwright.

## Sibling repositories (not in this repo)

| Repo        | Role relative to web                                                                         |
| ----------- | -------------------------------------------------------------------------------------------- |
| `service`   | Local Docker: Postgres `:5432`, Redis `:6379`, Mailpit `:1025`/`:8025` (`./scripts/up.sh`)   |
| `electron`  | Desktop browser; consumes this API; types generated from `openapi.json` via `contracts:pull` |
| `marketing` | Public Astro site; CTAs point at this app's login/checkout                                   |

Full architecture: `../ARCHITECTURE.md`.

Do not implement anti-detect navigation/fingerprint logic in this repo (web
owns business rules + API only). Do not edit sibling repositories unless the
user explicitly asks.

## Agent loading map

**Always** load this root file first.

Then load **only** the child guides the task touches (do not load every
`AGENTS.md` by default):

| Work area                      | Also load                             |
| ------------------------------ | ------------------------------------- |
| `app/`, `routes/`, `database/` | `app/AGENTS.md`                       |
| `tests/`                       | `tests/AGENTS.md`                     |
| `resources/js/`                | `resources/js/AGENTS.md` (if present) |

Child files **add** to this root; they do not replace it.

## Workflow rules

- Follow existing code conventions. Check sibling files before creating or editing.
- Use descriptive names; reuse existing components before creating new ones.
- Do not create documentation files unless explicitly requested.
- Do not change dependencies without approval.
- Use host-native commands by default (`composer`, `pnpm`, `php artisan`). Do
  not suggest Sail unless the user asks or the issue is container-specific.
- Use `php artisan make:*` with `--no-interaction`.
- Communicate with the user in **pt-BR**; code, comments, commits and technical
  docs stay in **English**.

## Local development

- PHP and Node on the host; stateful deps in sibling **`service`**
  (Postgres `127.0.0.1:5432`, Redis `6379`, Mailpit SMTP `1025` / UI `8025`).
- Default dev DB is Postgres via `service` (`.env.example` is starter-kit
  SQLite; switch to `DB_CONNECTION=postgres` for parity with production).
- Dev server: `composer run dev` (Laravel serve + Vite).
- Frontend tooling: `pnpm dev` / `pnpm build` / `pnpm lint:check` /
  `pnpm types`.
- DB reset: `php artisan migrate:fresh --seed --no-interaction`.
- Tests always use PostgreSQL, never SQLite.

## API contract (electron)

- The REST API under `routes/api.php` is the contract with the desktop.
- Expose `openapi.json` (e.g. via `dedoc/scramble`) — **source of truth** for
  the desktop TS types.
- Breaking contract changes: update schemas here **and** regenerate electron
  types (`contracts:pull`) in the same feature flow; electron typecheck breaks
  otherwise.
- Never hand-write API types on the desktop; never add an intermediate layer
  between this API and the desktop.

## Laravel and PHP (summary)

Full backend rules: **`app/AGENTS.md`**.

- Braces, typed params/returns, constructor promotion, PHPDoc over noisy comments.
- Form Requests: array rules + `authorize()` / `rules()` / `messages()`.
- Named routes via `route()`; frontend uses Wayfinder-generated paths.
- Business rules (plans, quotas, billing) live **here**, never in `electron`.

## Frontend (summary)

- Inertia pages under `resources/js/pages`; `Inertia::render()` on the server.
- Dashboard uses the React starter kit: Tailwind 4 + shadcn/ui components.
- ESLint zero-warnings; `pnpm types` (tsc --noEmit) must pass.

## Testing (summary)

Full suite rules: **`tests/AGENTS.md`**.

- Every change needs automated tests; run focused tests first.
- Layout: `tests/Backend/Feature/`, `tests/Backend/Unit/`,
  `tests/Backend/Arch/` (Pest); frontend Vitest under `tests/Frontend/`; E2E
  Playwright under `tests/e2e/`.
- New code paths need at least one appropriate test.
- Coverage floor: `--min=60` (`make test`); needs pcov/xdebug locally.

## Required checks by change type

| Change               | Minimum checks                                                                                                                       |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| PHP backend          | `composer lint` (Pint) dirty; focused `php artisan test --compact ...`; `composer types:check` (PHPStan) when types/contracts change |
| Migration            | Focused test + `php artisan migrate:fresh` round-trip                                                                                |
| Form Request         | Feature tests accept/reject                                                                                                          |
| Frontend             | `pnpm lint:check` + `pnpm types` (+ focused Vitest if `tests/` frontend suites exist)                                                |
| API / OpenAPI change | Above + regenerate electron types (`contracts:pull` in `electron/`) and typecheck there                                              |

## Formatting and quality

- After PHP edits: Pint dirty before finalizing (`composer lint`).
- Focused Larastan: `vendor/bin/phpstan analyse app/Path/To/File.php --memory-limit=1G`.
- ESLint owns TS/JS complexity.
- Quality gate: `make lint` (Pint + PHPStan + optional Psalm/PHPMD; ESLint +
  dependency-cruiser + tsc) and `make check` (`agents-audit` + lint + Pest).
- Git hooks (husky): pre-commit lint-staged + Pint on staged PHP, commit-msg
  commitlint (conventional + repo scopes), pre-push Pint + tsc + ESLint.
- CI is **active** at `.github/workflows/ci.yml` (lint-php, lint-frontend,
  Pest+Postgres, migration-round-trip). Deploy and Dependabot are written but
  **standing by** in `.github/standby/` (`deploy.yml`, `dependabot.yml`) — not
  active; activate by moving them into `.github/workflows/`.
- After structural PHP changes (models, migrations, enums): regenerate IDE
  helpers when needed (`ide-helper:generate`, `ide-helper:models --nowrite`,
  `ide-helper:meta`). Generated helpers are gitignored.

## Deployment

- Production: **Laravel Cloud** → managed Postgres.
- Auth/tokens: Fortify + JWT curto + refresh; `safeStorage` on the desktop.
- Stripe webhooks update quotas (`/api/me` → `subscription.limits`); desktop
  GuardManager enforces locally and blocks when the API fails or expires.
- CI is active (`.github/workflows/ci.yml`). Deploy pipeline lives in
  `.github/standby/` (`deploy.yml`, `dependabot.yml`) — written and correct but
  not active; move to `.github/workflows/` to enable.

## Documentation and skills

- Workspace architecture: `../ARCHITECTURE.md`.
- Agent skills: `.agents/skills/` if present (load `.agents/AGENTS.md` when
  editing skills).
