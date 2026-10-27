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

_Task 4.1, 4.5, 4.6._
