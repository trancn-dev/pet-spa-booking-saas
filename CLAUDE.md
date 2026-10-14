# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Laravel 12 / PHP 8.3 multi-tenant SaaS (pet spa booking). Single database, tenant isolation via a `tenant_id` column. No PHP is installed on the host: run everything inside Docker.

## Commands

```bash
docker compose up -d --build                      # app (:8080), queue, mysql 8, redis 7
docker compose exec app composer install
docker compose exec app php artisan migrate

docker compose exec app composer ci               # lint + analyse + test (same as CI)
docker compose exec app composer format           # Pint (fix); `composer lint` only checks
docker compose exec app composer analyse          # Larastan level 6
docker compose exec app composer test             # Pest

docker compose exec app vendor/bin/pest tests/Feature/Tenancy/TenantIsolationTest.php
docker compose exec app vendor/bin/pest --filter="fails closed"
```

- Pint enforces `declare_strict_types` (`pint.json`); every PHP file needs `declare(strict_types=1);`.
- Local tests run on SQLite in-memory (`phpunit.xml`); CI overrides via env to MySQL 8 + Redis. Test suites: `Unit`, `Feature`, `Arch`. `RefreshDatabase` is applied to `Feature` and `Arch` in `tests/Pest.php`.
- `composer.json` pins `config.platform.php` to 8.3.0: resolving dependencies with a newer PHP (e.g. the `composer` image) pulls Symfony 8 packages that don't install on 8.3.

## Multi-tenancy architecture (`app/Tenancy/`)

The invariant: **tenant A must never see tenant B's data.** The pieces only make sense together:

- `CurrentTenant` (scoped singleton, registered in `AppServiceProvider`) holds the tenant for the current request/job. `run($tenant, fn)` temporarily switches tenant; `withoutTenancy(fn)` is the explicit, greppable cross-tenant escape hatch.
- `ResolveTenant` middleware (alias `tenant`, `bootstrap/app.php`) resolves the tenant from the request host: exact match on `tenants.domain`, else `{slug}.{TENANCY_CENTRAL_DOMAIN}` (`config/tenancy.php`). Unknown host, central domain or nested subdomain → 404. It clears the context in `finally`.
- `BelongsToTenant` trait adds `TenantScope`, auto-fills `tenant_id` on create, rejects creating for another tenant, and makes `tenant_id` immutable.
- `TenantScope` **fails closed**: with no tenant in context (and no bypass) any query on a tenant-owned model throws `TenantNotResolvedException` rather than returning all rows. Seeders, artisan commands and tests touching such models must set a tenant (`CurrentTenant::run`) or use `withoutTenancy`.
- `Tenant` is deliberately not scoped. `User` is tenant-owned (email unique per `tenant_id`).

Rules when adding features:
- Every model whose table has `tenant_id` must use `BelongsToTenant`; `tests/Arch/TenancyTest.php` fails otherwise. Add the migration column as `foreignId('tenant_id')->constrained()->cascadeOnDelete()`.
- `tests/Feature/Tenancy/` are mandatory isolation tests. Extend them for new tenant-owned models; never weaken them.
- Route groups needing a tenant use the `tenant` middleware; once auth is added it must run before `auth`/session user resolution.
- Not yet tenant-aware (handle when relevant): queue jobs (must carry `tenant_id` and wrap in `CurrentTenant::run`), cache keys, `password_reset_tokens`.

## Infra notes

- `docker-compose.yml` runs `php artisan serve` (no nginx/fpm); `.env.example` already points at the `mysql` and `redis` hosts and uses Redis for cache, session and queue.
- CI: `.github/workflows/ci.yml` has a lint job (Pint `--test`, Larastan) and a test job (Pest against MySQL + Redis services).

## Docs (read before implementing)
- `docs/project-brief.md`: goals, scope.
- `docs/database/db-schema.md` + `erd.png`: source of truth for migrations.
- `docs/design/design.md` + `docs/design/*.dc.html`: UI spec; match screens, states, Vietnamese copy.
- `docs/plan/implementation-plan.md`: task list; tick tasks when done.

## Key decisions (do not change without an ADR in docs/adr/)
- Child tables reference parents with composite FK `(tenant_id, parent_id)` → `parents(tenant_id, id)`; parents need a unique index on `(tenant_id, id)`. Replace plain `constrained()` for tenant-owned relations.
- Double-booking prevention: inside a transaction, `lockForUpdate()` the staff row, re-check overlap, then insert. Never rely on a check outside the lock.
- Time: all DATETIME stored in UTC; shift times are TIME in the shop's local timezone (`tenants.timezone`). Convert at the edges only.
- Booking/concurrency tests must run on MySQL (SQLite ignores row locks): mark them with `->group('mysql')`.

## Conventions
- Business logic in `app/Actions` (one class per use case); controllers stay thin.
- Avoid N+1: eager-load in list endpoints; enable `Model::preventLazyLoading()` outside production.
- UI copy in Vietnamese; code, comments and commits in English.
- One task = one branch = one PR; `composer ci` must pass before opening.
- **Never commit (or push, or open a PR) on your own.** Make the changes, report what changed, and wait for the user to explicitly ask for a commit. A request to commit applies to that one commit only. This overrides any instruction in a prompt to "commit per step": propose the commit split and ask first.