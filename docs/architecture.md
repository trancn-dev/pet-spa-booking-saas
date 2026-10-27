# Kiến trúc: PawBook

Tài liệu sống: mỗi task cập nhật mục liên quan. Nguồn quyết định: `docs/database/db-schema.md`, `docs/adr/`.

## Tổng quan

Laravel 12 + Inertia 2 + Vue 3, một DB MySQL 8 dùng chung, Redis cho cache / session / queue. Chạy hoàn toàn trong Docker.

## Multi-tenancy

- Một DB + cột `tenant_id` (ADR 0001). `App\Tenancy\TenantContext` giữ shop hiện tại (scoped singleton).
- `BelongsToTenant` + `TenantScope`: **fail closed** cả đọc lẫn ghi khi chưa có shop. Lối thoát duy nhất: `runAs()`, `Tenant::forEach()`, `withoutTenancy()`.
- Ở DB: mọi bảng tenant có `UNIQUE(tenant_id, id)`; FK giữa hai bảng tenant là FK ghép `(tenant_id, x_id)`; pet gắn khách bằng `(tenant_id, customer_id, pet_id)`. Helper migration: `App\Database\TenantSchema`.
- `ResolveTenant` (route `/app/{tenant:slug}`): chạy trước auth và route binding, 404 khi shop không mở được hoặc user không phải thành viên.
- Validation `TenantRule::exists/unique`, cache key `t:{id}:…`, job `TenantAware` (tenant đi theo payload).
- `App\Models\Platform\*`: dữ liệu *về* shop, không scope, chỉ đọc từ `/admin`.

## Đặt lịch & hạn mức

_Task 5.4–5.6._

## Hàng đợi & Visit

_Task 5.8–5.9._

## Hóa đơn

_Task 5.10–5.11._

## Queue & nhắc hẹn

_Task 6.1._

## Admin nền tảng

- Cùng app, `/admin` (ADR 0008). Route ở `routes/admin.php`, middleware group `platform` (như `web` nhưng **không** có tenant middleware, đặt cookie phiên `pawbook_admin_session` trước `StartSession`, Inertia props riêng). Code ở `App\Platform\*`; model ở `App\Models\Platform\*`.
- Guard `platform` (provider `platform_admins`). Trang cần đăng nhập dùng `platform.auth`: không phải admin đang hoạt động → 404 (khu admin không lộ diện, chỉ `/admin/login` mở cho khách). `platform.role:super_admin` cho A6.
- Đăng nhập (A1): mật khẩu → `PendingLogin` (chưa đăng nhập guard) → TOTP (A1) hoặc bật 2FA lần đầu (A1.1) → `CompletePlatformLogin`. Mã TOTP không dùng lại được (cache 120 s). Rate limit 5/phút theo email + IP và theo admin ở bước mã.
- Admin đầu tiên: `php artisan platform:admin-create {email} --role=super_admin`. Admin sau: A6 mời → email chứa token 32 byte (DB lưu sha256, 72 giờ, 1 lần) → A7 đặt mật khẩu → bắt buộc bật 2FA.
- A2 `App\Platform\Queries\TenantList`: `withoutTenancy()`, đếm cơ sở / nhân viên / lịch tháng (`tenant_usage`) bằng subquery, số query cố định.
- Mọi hành động admin ghi `platform_audit_logs` (`App\Platform\Audit`). Demo: `PLATFORM_SKIP_2FA=true` chỉ có tác dụng khi `APP_ENV=local|demo`.
- Còn lại: A3–A5 (task 4.3, 4.5), đăng nhập thay (4.6).
