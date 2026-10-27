# ADR-0001: Một DB dùng chung, phân tách bằng `tenant_id` + FK ghép

- **Trạng thái:** Chấp nhận
- **Ngày:** 2026-10-21
- **Giai đoạn:** MVP

## Bối cảnh
PawBook là SaaS cho pet spa / phòng khám thú y. Khách hàng là shop nhỏ (1–5 cơ sở, vài chục nghìn bản ghi mỗi năm), số shop có thể lên hàng trăm. Một người (`users`) có thể làm ở nhiều shop. Admin nền tảng cần số liệu tổng hợp mọi shop. Rủi ro lớn nhất của SaaS là lộ dữ liệu giữa các shop.

## Quyết định
- Một database MySQL. Mọi bảng nghiệp vụ có `tenant_id NOT NULL` và `UNIQUE(tenant_id, id)`.
- FK giữa hai bảng tenant là **FK ghép** `(tenant_id, x_id) → x(tenant_id, id)`. Code sai cũng không gắn được bản ghi shop A vào shop B. Pet gắn khách bằng `(tenant_id, customer_id, pet_id)` nên "pet phải của đúng khách" được DB bảo đảm.
- Mọi index ghép và unique nghiệp vụ bắt đầu bằng `tenant_id`.
- Tự viết lớp tenancy nhẹ, không dùng `stancl/tenancy`: `TenantContext` (scoped singleton), trait `BelongsToTenant` với global scope **fail closed** (query hoặc create khi chưa có tenant thì ném `TenantNotResolvedException`), middleware `ResolveTenant` theo slug `/app/{slug}` và `/{slug}`.
- Chạy không có tenant phải có chủ đích: `TenantContext::runAs()`, `Tenant::forEach()`, `TenantContext::withoutTenancy()` (chỉ `App\Platform` và command trong whitelist).
- `users` là danh tính toàn cục; thành viên shop qua `staff`.

## Phương án đã cân nhắc
| Phương án | Ưu | Nhược | Lý do loại |
|---|---|---|---|
| DB riêng mỗi shop | Cô lập vật lý, backup/xóa một shop dễ | Migration chạy N lần, kết nối động, báo cáo tổng hợp cho admin phải gom N DB, user làm nhiều shop phải tra chéo DB, tốn tài nguyên cho shop nhỏ | Chi phí vận hành lớn hơn lợi ích ở quy mô này |
| `stancl/tenancy` (single-DB mode) | Có sẵn nhiều tính năng | Nhiều "magic", khó giải thích từng lớp chặn, phụ thuộc vào package cho phần quan trọng nhất | Mục tiêu dự án là chứng minh hiểu cơ chế; lớp tự viết chỉ vài trăm dòng |
| Global scope nhưng không FK ghép | Đơn giản hơn | Chỉ chặn ở tầng ứng dụng; một câu `withoutGlobalScopes()` hoặc raw query là lộ | Cần một lớp ở DB không phụ thuộc code |

## Hệ quả
- Một bộ migration, một kết nối, báo cáo admin bằng một query.
- Rủi ro quên lọc tenant được chặn bằng 6 lớp: middleware → global scope fail closed → FK ghép → `Rule::exists` kèm `tenant_id` → cache/lock/file key có tiền tố `t:{tenant_id}` → test kiến trúc cấm `withoutGlobalScopes()` ngoài whitelist.
- Job mang `tenant_id` (trait `TenantAware`), scheduler lặp từng shop.
- Shop lớn có thể làm chậm shop khác (noisy neighbor). Chấp nhận ở V1; index bắt đầu bằng `tenant_id` giữ query của mỗi shop chỉ quét phần của nó.
- Xóa shop = `status = cancelled` (404), không xóa dữ liệu. Xuất dữ liệu một shop phải lọc theo `tenant_id` trên từng bảng.
- Bảng có cột đa hình (`attachments`, `reminders`, `activity_logs`) không có FK: Action phải kiểm đối tượng cùng tenant trước khi ghi.

## Kiểm chứng
- Test fail closed: query/create khi chưa có context → exception.
- Helper `assertTenantIsolated(route, factory)`: user shop A gọi show/update/delete với ID shop B → 404, áp cho mọi resource.
- Test DB: insert thẳng bản ghi với `customer_id` của shop khác → lỗi FK.
- PHPStan rule báo lỗi khi gọi `withoutGlobalScope(s)` ngoài whitelist.

## Liên quan
`design/db-schema.md` mục 1, 5.2 · ADR-0004 · ADR-0008.
