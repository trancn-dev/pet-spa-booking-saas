# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Laravel 12 / PHP 8.3 multi-tenant SaaS (pet spa booking). Frontend: Inertia 2 + Vue 3 `<script setup>` + TypeScript (Laravel Vue starter kit v1.0.2, Tailwind 3, Ziggy). Single database, tenant isolation via a `tenant_id` column. No PHP is installed on the host: run everything inside Docker.

## Commands

```bash
docker compose up -d --build                      # app (:8080), queue, mysql 8, redis 7, mailpit (:8025)
docker compose exec app composer install
docker compose exec app php artisan migrate:fresh --seed   # = make fresh (see Makefile: up, down, sh, test, lint, analyse, ci)

docker compose exec app npm run build          # Vite (Node 22 lives in the app image)
docker compose exec app composer ci               # lint + analyse + test (same as CI)
docker compose exec app composer format           # Pint (fix); `composer lint` only checks
docker compose exec app composer analyse          # Larastan level 6
docker compose exec app composer test             # Pest

docker compose exec app vendor/bin/pest tests/Feature/Tenancy/TenantIsolationTest.php
docker compose exec app vendor/bin/pest --filter="fails closed"
```

- Pint enforces `declare_strict_types` (`pint.json`); every PHP file needs `declare(strict_types=1);`.
- Tests run on real MySQL 8, database `testing` (`phpunit.xml`): the schema relies on composite FKs, CHECK constraints, generated columns and row locks. `docker/mysql/init/` creates `testing` on a fresh volume; on an existing volume run once: `docker compose exec mysql mysql -uroot -psecret -e "CREATE DATABASE IF NOT EXISTS testing; GRANT ALL ON testing.* TO 'laravel'@'%';"`. CI uses MySQL 8 + Redis services. Test suites: `Unit`, `Feature`, `Arch`. `RefreshDatabase` is applied to `Feature` and `Arch` in `tests/Pest.php`, which also defines `inTenant($tenant, fn)`.
- MySQL session runs in UTC (`config/database.php` `timezone => +00:00`), collation `utf8mb4_0900_ai_ci`. `Model::shouldBeStrict()` outside production (lazy loading, discarded or missing attributes throw).
- `composer.json` pins `config.platform.php` to 8.3.0: resolving dependencies with a newer PHP (e.g. the `composer` image) pulls Symfony 8 packages that don't install on 8.3.

## Multi-tenancy architecture (`app/Tenancy/`)

The invariant: **tenant A must never see tenant B's data.** The pieces only make sense together:

- `TenantContext` (scoped singleton, registered in `AppServiceProvider`) holds the tenant for the current request/job. `runAs($tenant, fn)` temporarily switches tenant; `Tenant::forEach(fn)` runs per tenant (scheduler); `withoutTenancy(fn)` is the explicit, greppable cross-tenant escape hatch. `withoutGlobalScope(s)` is banned in `app/` (arch test).
- **`User` is a global identity** (`UNIQUE(email)`, `last_tenant_id`), not tenant-owned. Membership = an active `staff` row (`TenantMembership` interface, `StaffMembership` impl). Auth, password reset and account settings are central routes.
- Tenant routes live under `/app/{tenant:slug}/...` inside `Route::middleware('tenant')`. `ResolveTenant` loads the tenant by slug (404 if unknown/suspended), 404s a logged-in non-member, remembers `last_tenant_id`, sets `URL::defaults(['tenant' => slug])` and clears the context in `finally`. Guests pass through so `auth` redirects them. Central `/dashboard` (`dashboard`) redirects to the user's last/first workspace (`app.dashboard`).
- `BelongsToTenant` trait adds `TenantScope`, auto-fills `tenant_id` on create, rejects creating for another tenant, and makes `tenant_id` immutable.
- **Fails closed** on reads and writes: with no tenant in context (and no bypass) any query on a tenant-owned model throws `TenantNotResolvedException` rather than returning all rows, and so does creating one (even with an explicit `tenant_id`). Inside `withoutTenancy` creating needs an explicit `tenant_id`. Seeders, artisan commands, factories and tests must set a tenant (`TenantContext::runAs` / `inTenant()` in tests) or use `withoutTenancy`. `runAs` re-enables scoping even inside `withoutTenancy`.
- `Tenant` is deliberately not scoped. `App\Models\Platform\*` (subscriptions, impersonation, platform audit) is data *about* shops: has `tenant_id` but is never scoped, only read from `/admin` (arch test exempts that namespace only).
- `ResolveTenant` opens shops whose status `isAccessible()` (`trial`, `active`, `past_due`); `suspended` stays 404 until the read-only mode of task 4.5, `cancelled` is always 404.
- Helpers: `TenantRule::exists('pets')` / `TenantRule::unique('customers', 'phone')` for validation; `tenant()` and `tenant_cache_key('x')` → `t:{id}:x` for cache / lock / rate-limiter keys; queued jobs that touch shop data `use TenantAware` (the dispatching tenant rides in the payload; dispatching one with no tenant fails the job). Dispatch inside a closure body, never `runAs($t, fn () => Job::dispatch())`: `PendingDispatch` queues on destruct, after the context is restored.

Rules when adding features:
- Every model whose table has `tenant_id` must use `BelongsToTenant`; `tests/Arch/TenancyTest.php` fails otherwise. Add the migration column as `foreignId('tenant_id')->constrained()->cascadeOnDelete()` (written out, not a macro: Larastan reads columns from migrations), then `App\Database\TenantSchema::key/foreign/unique/index/datetimes` for `UNIQUE(tenant_id,id)`, composite FKs, tenant-first indexes and DATETIME timestamps.
- MySQL forbids `CASCADE`/`SET NULL` on columns used by a CHECK or by a stored generated column: keep those FKs at the default (RESTRICT). CHECK constraints are added with `DB::statement('ALTER TABLE … ADD CONSTRAINT …')`.
- Models: `$guarded = ['id', 'tenant_id', …]` (tenant_id is never mass-assigned), enums in `app/Enums` (backed, cast in `casts()`), `HasPublicId` (ULID `public_id`, route key) for branches/customers/pets/appointments/visits/invoices, `AppendOnly` for payments, appointment_changes, activity/audit logs, addenda. Pivots with `tenant_id` use custom `Pivot` classes (`StaffBranch`, `BranchService`, `ServiceComboItem`) so attach() stamps the tenant. Lock/counter tables (`booking_capacity_locks`, `tenant_usage`, `branch_daily_counters`, `tenant_counters`) have no model: use the query builder in Actions.
- Factories are tenant-aware (`Database\Factories\Concerns\InTenant`): tenant_id defaults to the current tenant and parents are created in the same tenant; run them inside `inTenant()`.
- `tests/Feature/Tenancy/` are mandatory isolation tests. Extend them for new tenant-owned models; never weaken them.
- `unique`/`exists` validation rules on tenant-owned tables bypass global scopes: always add `->where('tenant_id', ...)`. Validation on `users` is global.
- HTTP feature tests for tenant routes use `Tests\Concerns\ActsOnTenant` (tenant `acme`, `tenantUser()` = member, `withoutVite()`); `tests/Feature/Tenancy/TenantAuthTest.php` guards membership.
- Still to do (see plan): shop provisioning / invitations (4.3–4.4; removes self-registration), Larastan level 8 + ESLint in CI (task 1.2), `assertTenantIsolated` helper once the first CRUD routes exist (5.1). `password_reset_tokens` are global like users.

## Roles & permissions (ADR 0009, `app/Authorization/`)

- spatie/laravel-permission in teams mode, `team_id = tenant_id`, guard `web` only. `ResolveTenant` sets the team id per request (and resets it, the user's loaded roles and `CurrentStaff`); outside a request wrap checks in the team (`asTeam($tenant, fn)` in tests).
- The catalogue lives in `config/permissions.php` (code, Vietnamese label, module, `sensitive`, `assignable`, `owner`, `phase`). Never create permissions elsewhere; after changing it run `php artisan permissions:sync` (also run by `ReferenceDataSeeder`): new codes go to every shop's `owner` role only.
- `App\Models\Role` (`name` = stable code, `display_name` = shop label, custom roles `custom_{id}`; `{role}` binds within the current shop). Template roles come from `CreateDefaultRoles`; edits go through `SaveRole` / `DeleteRole` (owner role locked, `roles.manage` never assignable, sensitive grants need confirmation and log to `activity_logs`).
- Check abilities with Policies (`app/Policies`, `Concerns\ChecksPermissions`), never role names. No `Gate::before` owner bypass: clinical permissions (`owner => false`) need the `vet` role. Branch scope: `CurrentBranch` / `CurrentStaff` (staff branches unless `branches.all`).
- `ResolveTenant` drops the `{tenant}` route parameter after resolving it: controllers in tenant routes only receive their own parameters.
- Inertia shares `tenant` and `permissions` (codes) for the open shop (UI disables buttons; the server still authorises).

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
- Over-booking prevention (db-schema 5.1, ADR 0002): `INSERT IGNORE` the `booking_capacity_locks` rows *outside* the transaction, then inside it lock in this fixed order: `customers` row → `booking_capacity_locks` (date, group ascending; medical before grooming) → `tenant_usage`; re-check capacity, then insert. Invoices lock `invoices` → `visits` → `stock_items`. Never rely on a check outside the lock.
- Branches (`branches`) are NOT a tenant boundary: `branch_id` uses a composite FK `(tenant_id, branch_id)` → `branches(tenant_id, id)` but has **no** global scope (staff may look up other branches' stock). Filter by the selected branch in queries + Policies. Operational tables get `branch_id` from day one.
- Money is `DECIMAL(12,2)`, stock quantities `DECIMAL(12,3)`; never float. Appointment items snapshot `duration_min` / `reference_price` at booking time (display only; billing uses the price when added to the Visit).
- Customers (pet owners, table `customers`, never "owners": `owner` is the shop-owner role) are identified by phone (`customers.phone`, unique per tenant, shared across branches) and log into the Site with phone + SMS OTP (separate `customer` guard, not `users`). Strays attach to a per-branch system customer (`is_system`, `system_branch_id`).
- Time: all DATETIME stored in UTC; shift times are TIME in the shop's local timezone (`tenants.timezone`). Convert at the edges only.
- Counter tables use the column `last_value` (db-schema 2.10), a reserved word in MySQL 8: backtick it in every raw SQL statement.
- Platform admin area (`/admin`, task 4.1): `routes/admin.php` + middleware group `platform` (own session cookie `pawbook_admin_session`, no tenant middleware), guard `platform`, code in `App\Platform\*`, models in `App\Models\Platform\*`. Protected pages 404 for anyone but an active admin who passed TOTP. First admin: `php artisan platform:admin-create {email}`. Demo admins `admin@pawbook.test` / `support@pawbook.test` (password `password`) can skip 2FA only when `APP_ENV=local|demo` and `PLATFORM_SKIP_2FA=true`. `withoutTenancy()` is allowed only in `App\Platform` and `App\Tenancy` (arch test).
- Inertia pages live in `resources/js/pages` (lower case); `config/inertia.php` points the testing page finder there.
- Booking/concurrency tests must run on MySQL (SQLite ignores row locks): mark them with `->group('mysql')`.

## Conventions
- Business logic in `app/Actions` (one class per use case); controllers stay thin.
- Avoid N+1: eager-load in list endpoints; enable `Model::preventLazyLoading()` outside production.
- UI copy in Vietnamese; code, comments and commits in English.
- One task = one branch = one PR; `composer ci` must pass before opening.
- **Never commit (or push, or open a PR) on your own.** Make the changes, report what changed, and wait for the user to explicitly ask for a commit. A request to commit applies to that one commit only. This overrides any instruction in a prompt to "commit per step": propose the commit split and ask first.