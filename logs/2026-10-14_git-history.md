# 2026-10-14 — Lịch sử git trong ngày

Repo: pet-spa-booking-saas · 8 commit · 2 nhánh (`main`, `feat/vue-starter-kit`)

| Giờ | Commit | Nhánh | Nội dung |
|---|---|---|---|
| 18:57:45 | `fc0765f` | main | Init Laravel 12 với Docker, Pint, Larastan, Pest và CI |
| 19:14:10 | `10baaf1` | main | Nền multi-tenant: BelongsToTenant, TenantScope, ResolveTenant, test cô lập |
| 22:47:13 | `2790b09` | main (= origin/main) | Thêm docs |
| 23:54:10 | `ef18d89` | feat/vue-starter-kit | Thêm Node 22 vào image app để build Vite |
| 23:54:11 | `727e2e0` | feat/vue-starter-kit | Thêm frontend Vue starter kit |
| 23:54:11 | `834734b` | feat/vue-starter-kit | Thêm Inertia + auth của starter kit, nối vào tenancy |
| 23:54:11 | `5a736c1` | feat/vue-starter-kit | Test auth theo tenant + test cô lập auth giữa tenant |
| 23:54:11 | `c3a7c55` | feat/vue-starter-kit (= origin/feat/vue-starter-kit) | Build frontend trong CI, tài liệu hóa Vue/tenancy |

`feat/vue-starter-kit` tách từ `main` tại `2790b09`, nhánh có 5 commit riêng. Cả 5 có cùng mốc giờ 23:54 nên thứ tự dựa theo lịch sử git.

---

## Nhánh `main`

### fc0765f — 18:57:45 — Init Laravel 12 with Docker, Pint, Larastan, Pest and CI
Khởi tạo dự án: Laravel 12 (63 file), Docker (compose + Dockerfile PHP), Pint (`declare_strict_types`), Larastan, Pest, workflow CI.
- Cấu hình: .dockerignore, .editorconfig, .env.example, .gitattributes, .gitignore, composer.json/lock, package.json, phpstan.neon, phpunit.xml, pint.json, docker-compose.yml, docker/php/Dockerfile, vite.config.js, README.md
- CI: .github/workflows/ci.yml
- App/bootstrap/config: app/Http/Controllers/Controller.php, app/Models/User.php, app/Providers/AppServiceProvider.php, artisan, bootstrap/*, config/*.php, routes/web.php, routes/console.php, public/*
- Database: migrations (users, cache, jobs), UserFactory, DatabaseSeeder
- Test: tests/Pest.php, tests/TestCase.php, tests/Unit/ExampleTest.php

### 10baaf1 — 19:14:10 — Add multi-tenant foundation
Nền multi-tenant một DB + `tenant_id`: scope fail closed, tự gán tenant_id, resolve tenant theo host.
- app/Tenancy/BelongsToTenant.php, CurrentTenant.php, TenantScope.php, TenantNotResolvedException.php (mới)
- app/Http/Middleware/ResolveTenant.php (mới), app/Models/Tenant.php (mới)
- app/Models/User.php, app/Providers/AppServiceProvider.php, bootstrap/app.php (sửa)
- config/tenancy.php (mới)
- database: TenantFactory (mới), UserFactory, migration tenants (mới), migration users (sửa)
- phpunit.xml, tests/Pest.php (sửa)
- tests/Arch/TenancyTest.php, tests/Feature/Tenancy/ResolveTenantMiddlewareTest.php, tests/Feature/Tenancy/TenantIsolationTest.php (mới)

### 2790b09 — 22:47:13 — add docs
Thêm tài liệu thiết kế vào repo.
- CLAUDE.md
- docs/project-brief.md
- docs/database/db-schema.md, docs/database/erd.png
- docs/design/design.md, docs/design/support.js, 8 file docs/design/PawBook *.dc.html (Chi tiết lịch hẹn, Hồ sơ thú cưng, Lịch hẹn, Portal đặt lịch, Style Guide, Tiêm phòng đến hạn, Tổng quan, Đặt lịch mới)
- docs/plan/implementation-plan.md

---

## Nhánh `feat/vue-starter-kit` (từ main@2790b09)

### ef18d89 — 23:54:10 — Add Node 22 to the app image for Vite builds
- docker/php/Dockerfile

### 727e2e0 — 23:54:11 — Add Vue starter kit frontend (Inertia 2, Vue 3, TypeScript, Tailwind 3)
Port từ laravel/vue-starter-kit v1.0.2 (bản cuối cho Laravel 12 / Inertia 2). 169 file, +10891/−315.
- Cấu hình: .prettierignore, .prettierrc, components.json, eslint.config.js, tailwind.config.js, tsconfig.json, vite.config.ts, package.json, package-lock.json
- Xóa: resources/js/app.js, resources/js/bootstrap.js, vite.config.js
- Mới: resources/js/app.ts, resources/css/app.css (sửa), resources/views/app.blade.php
- resources/js/components/* (AppShell, AppSidebar, AppHeader, NavMain, NavUser, ...) và components/ui/* (avatar, breadcrumb, button, card, checkbox, collapsible, ...)
- resources/js/composables/, layouts/ (app, auth, settings), lib/utils.ts, types/
- resources/js/pages/: Dashboard, Welcome, auth/* (Login, Register, ForgotPassword, ResetPassword, ConfirmPassword, VerifyEmail), settings/* (Appearance, Password, Profile)

### 834734b — 23:54:11 — Add Inertia and starter kit auth, wired into tenancy
- Resolve tenant trước auth (priority list) để tra user theo session được scope.
- Bọc toàn bộ route của starter kit trong middleware `tenant`.
- Validation unique và throttle đăng nhập theo tenant.
- Chia sẻ auth user lazy trong Inertia props.
- Files: app/Http/Controllers/Auth/* (8 controller), app/Http/Controllers/Settings/PasswordController.php, ProfileController.php, app/Http/Middleware/HandleInertiaRequests.php, app/Http/Requests/Auth/LoginRequest.php, app/Http/Requests/Settings/ProfileUpdateRequest.php, routes/auth.php, routes/settings.php, routes/web.php, bootstrap/app.php, composer.json, composer.lock

### 5a736c1 — 23:54:11 — Add tenant-aware auth tests and cross-tenant auth isolation tests
- tests/Concerns/ActsOnTenantHost.php (mới)
- tests/Feature/Auth/{Authentication,EmailVerification,PasswordConfirmation,PasswordReset,Registration}Test.php (mới)
- tests/Feature/DashboardTest.php, tests/Feature/Settings/{PasswordUpdate,ProfileUpdate}Test.php (mới)
- tests/Feature/Tenancy/TenantAuthTest.php (mới)
- tests/Pest.php (sửa)

### c3a7c55 — 23:54:11 — Build frontend in CI and document the Vue/tenancy setup
- .github/workflows/ci.yml (thêm setup-node 22 + `npm ci && npm run build`)
- CLAUDE.md (mô tả frontend, lệnh npm build, ghi chú tenancy)

---

Chi tiết commit 5a736c1 và c3a7c55 cũng có ở logs/2026-10-14_23-54-11_5a736c1.md và logs/2026-10-14_23-54-11_c3a7c55.md.
