# AGENTS.md — app (web)

**Adds to:** repository root `AGENTS.md`.  
**Load when:** editing `app/`, `routes/`, or `database/` backend code.

## Models

- Prefer Eloquent models with typed casts (`protected function casts(): array`).
- UUID string primary keys via a shared concern when the pattern requires it.
- Organize with section comments when useful: relationships, scopes, helpers.

## Enums

- String-backed: `enum X: string`.
- PascalCase case names.
- Prefer business helpers (`isOperational()`, etc.) and `match` over `switch`.

## Layout

The app currently follows the Laravel 13 starter-kit layout. When domains grow,
group by domain instead of dumping files at layer roots:

- Controllers / Form Requests / Models / Services under `{Domain}/` where a
  domain exists (Auth, Settings, Profiles, Billing, Subscription, …).
- Migrations: `database/migrations/{domain}/` once domains appear.

## Controllers

- Thin controllers: delegate to Services/Models, return Inertia responses or
  API JSON.
- Form Requests for validation (`authorize()` / `rules()` / `messages()`);
  messages in **Portuguese** (user-facing copy).
- API controllers (`routes/api.php`) return JSON that matches the OpenAPI
  contract consumed by the desktop.

## Routes

- Dashboard/Inertia routes in `routes/web.php`; API routes in `routes/api.php`
  (`api` middleware, `/api` prefix).
- Names mirror structure: `{module}.{action}` or `{module}.{feature}.{action}`.
- Wayfinder-generated TS paths follow the same dots.

## Business rules ownership

- Plans, quotas, billing, and profile CRUD rules live **here** — never in
  `electron`.
- The API must expose ready-to-consume JSON (`GET /api/me` →
  `subscription.limits`); the desktop only obeys.

## Migrations

- Keep migrations in `database/migrations/`.
- Test with Postgres (never SQLite) — run `php artisan migrate:fresh --seed
--no-interaction` locally against the `service` Postgres.
- Risky DDL: verify a migrate round-trip (fresh migrate up and back) before
  merge.

## Complexity (PHPMD/PHPStan)

- Keep methods small: cyclomatic ≤ 10, method ≤ 65 lines, ≤ 6 parameters.
- Prefer DTOs / Form Requests over long parameter lists.
- `composer types:check` (PHPStan) must pass.

## Internationalization (backend)

- Flash messages, notification copy, email subjects, enum labels: `__()` /
  `trans()`.
- New keys in **every** supported locale file.
