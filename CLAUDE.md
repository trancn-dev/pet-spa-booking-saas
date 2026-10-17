# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Laravel 12 / PHP 8.3 multi-tenant SaaS (pet spa booking). Frontend: Inertia 2 + Vue 3 `<script setup>` + TypeScript (Laravel Vue starter kit v1.0.2, Tailwind 3, Ziggy). Single database, tenant isolation via a `tenant_id` column. No PHP is installed on the host: run everything inside Docker.

## Commands

```bash
docker compose up -d --build                      # app (:8080), queue, mysql 8, redis 7
docker compose exec app composer install
docker compose exec app php artisan migrate

docker compose exec app npm run build          # Vite (Node 22 lives in the app image)
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

- `TenantContext` (scoped singleton, registered in `AppServiceProvider`) holds the tenant for the current request/job. `runAs($tenant, fn)` temporarily switches tenant; `Tenant::forEach(fn)` runs per tenant (scheduler); `withoutTenancy(fn)` is the explicit, greppable cross-tenant escape hatch. `withoutGlobalScope(s)` is banned in `app/` (arch test).
- **`User` is a global identity** (`UNIQUE(email)`, `last_tenant_id`), not tenant-owned. Membership = an active `staff` row (`TenantMembership` interface, `StaffMembership` impl). Auth, password reset and account settings are central routes.
- Tenant routes live under `/app/{tenant:slug}/...` inside `Route::middleware('tenant')`. `ResolveTenant` loads the tenant by slug (404 if unknown/suspended), 404s a logged-in non-member, remembers `last_tenant_id`, sets `URL::defaults(['tenant' => slug])` and clears the context in `finally`. Guests pass through so `auth` redirects them. Central `/dashboard` (`dashboard`) redirects to the user's last/first workspace (`tenant.dashboard`).
- `BelongsToTenant` trait adds `TenantScope`, auto-fills `tenant_id` on create, rejects creating for another tenant, and makes `tenant_id` immutable.
- `TenantScope` **fails closed**: with no tenant in context (and no bypass) any query on a tenant-owned model throws `TenantNotResolvedException` rather than returning all rows. Seeders, artisan commands and tests touching such models must set a tenant (`TenantContext::runAs`) or use `withoutTenancy`.
- `Tenant` is deliberately not scoped. `Staff` is the first tenant-owned model.

Rules when adding features:
- Every model whose table has `tenant_id` must use `BelongsToTenant`; `tests/Arch/TenancyTest.php` fails otherwise. Add the migration column as `foreignId('tenant_id')->constrained()->cascadeOnDelete()`.
- `tests/Feature/Tenancy/` are mandatory isolation tests. Extend them for new tenant-owned models; never weaken them.
- `unique`/`exists` validation rules on tenant-owned tables bypass global scopes: always add `->where('tenant_id', ...)`. Validation on `users` is global.
- HTTP feature tests for tenant routes use `Tests\Concerns\ActsOnTenant` (tenant `acme`, `tenantUser()` = member, `withoutVite()`); `tests/Feature/Tenancy/TenantAuthTest.php` guards membership.
- Still to do (see plan): queue jobs (`TenantAware`: carry `tenant_id`, wrap in `TenantContext::runAs`), cache keys prefixed `t:{tenant_id}:`, spatie roles (teams mode), workspace creation/invitations (plan phase 4), full `staff`/`branches` schema (plan task 3.1; `staff` currently has only the membership columns), `password_reset_tokens` are global like users.

## Infra notes

- `docker-compose.yml` runs `php artisan serve` (no nginx/fpm); `.env.example` already points at the `mysql` and `redis` hosts and uses Redis for cache, session and queue.
- CI: `.github/workflows/ci.yml` has a lint job (Pint `--test`, Larastan) and a test job (Pest against MySQL + Redis services).

## Docs (read before implementing)
- `docs/project-brief.md`: goals, scope.
- `docs/database/db-schema.md` (v2) + `erd.png`, `erd-hr.png`, `erd-inventory.png`: source of truth for migrations.
- `docs/design/design.md` (v2) + `docs/design/claude design/*.dc.html` (+ `support.js`): UI spec; match screens, states, Vietnamese copy.
- `docs/plan/implementation-plan.md` (v2): task list; tick tasks when done.
- Scope is phased: **MVP** (phase 1-10: booking, walk-in intake/queue, visits, invoices, branch schema + switcher), **Phase 2** (11: inventory, batches, sales, inter-branch transfer), **Phase 3** (12: shifts, timekeeping, payroll). Only build what the current task's phase covers.

## Key decisions (do not change without an ADR in docs/adr/)
- Child tables reference parents with composite FK `(tenant_id, parent_id)` → `parents(tenant_id, id)`; parents need a unique index on `(tenant_id, id)`. Replace plain `constrained()` for tenant-owned relations.
- Double-booking prevention: inside a transaction, `lockForUpdate()` the staff row, re-check overlap, then insert. Never rely on a check outside the lock.
- Branches (`branches`) are NOT a tenant boundary: `branch_id` uses a composite FK `(tenant_id, branch_id)` → `branches(tenant_id, id)` but has **no** global scope (staff may look up other branches' stock). Filter by the selected branch in queries + Policies. Operational tables get `branch_id` from day one.
- Money is `DECIMAL(12,2)`, stock quantities `DECIMAL(12,3)`; never float. Appointments snapshot `duration_min`/`price` at booking time.
- Customers (pet owners) are identified by phone (`owners.phone`, unique per tenant, shared across branches) and log into the portal with phone + SMS OTP (separate `customer` guard, not `users`). Strays attach to a per-branch system owner (`is_system`).
- Time: all DATETIME stored in UTC; shift times are TIME in the shop's local timezone (`tenants.timezone`). Convert at the edges only.
- Booking/concurrency tests must run on MySQL (SQLite ignores row locks): mark them with `->group('mysql')`.

## Conventions
- Business logic in `app/Actions` (one class per use case); controllers stay thin.
- Avoid N+1: eager-load in list endpoints; enable `Model::preventLazyLoading()` outside production.
- UI copy in Vietnamese; code, comments and commits in English.
- One task = one branch = one PR; `composer ci` must pass before opening.
- **Never commit (or push, or open a PR) on your own.** Make the changes, report what changed, and wait for the user to explicitly ask for a commit. A request to commit applies to that one commit only. This overrides any instruction in a prompt to "commit per step": propose the commit split and ask first.