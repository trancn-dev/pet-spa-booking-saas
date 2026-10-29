# CLAUDE.md

Hướng dẫn cho Claude Code khi làm việc trong repo này.

PawBook: SaaS đặt lịch nhiều shop (multi-tenant) cho pet spa / phòng khám thú y. Laravel 12 / PHP 8.3, Inertia 2 + Vue 3 `<script setup lang="ts">` (starter kit Vue v1.0.2, Tailwind 3, Ziggy), MySQL 8.4, Redis. Một DB dùng chung, cô lập shop bằng cột `tenant_id`. Máy host không có PHP: mọi lệnh chạy trong Docker.

## Tài liệu (đọc trước khi code)

| File | Nội dung |
|---|---|
| `docs/project-brief.md` | Mục tiêu, phạm vi |
| `docs/analysis/nghiep-vu-da-chot.md` | Nghiệp vụ đã chốt (nguồn nghiệp vụ duy nhất) |
| `docs/database/db-schema.md` | Nguồn duy nhất cho migration: bảng, cột, index, ràng buộc, rủi ro (mục 5) |
| `docs/adr/` | 10 ADR, **bản chính** cho quyết định kiến trúc. Không viết ADR mới trong task; chỉ điền mục Kiểm chứng |
| `docs/architecture.md` | Các khu, middleware, module, luồng đặt lịch, thứ tự khóa |
| `docs/design/design.md` | Đặc tả UI, luồng F1–F21, ma trận quyền (mục 8), dữ liệu demo (mục 9) |
| `docs/design/danh-muc-man-hinh.md` | Mã màn (S1…, A1…), URL, route name: nguồn duy nhất. Gọi màn bằng mã + tên (`S17 Visit`) |
| `docs/design/so-do-dat-lich.md` | Sơ đồ đặt lịch đồng thời (ADR-0002) |
| `docs/plan/implementation-plan.md` | Task theo phase, tiến độ thực tế, thứ tự làm |

Phạm vi theo giai đoạn: **MVP** (phase 1–10), **GĐ2** (phase 11: kho, bán hàng), **GĐ3** (phase 12: ca, chấm công, lương). Chỉ làm đúng phạm vi task đang làm. Thuật ngữ: shop = tenant, cơ sở = branch, khách = `customers` (**không phải** `owners`), chủ shop = role `owner`, Visit = một lượt khám/spa của một pet.

## Lệnh

```bash
docker compose up -d --build                    # app (:8080), queue, mysql 8.4, redis 7
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app npm run build           # Node 22 có trong image app
docker compose exec app composer ci             # lint + analyse + test (giống CI)
docker compose exec app composer format         # Pint sửa; `composer lint` chỉ kiểm
docker compose exec app composer analyse        # Larastan
docker compose exec app composer test           # Pest
docker compose exec app vendor/bin/pest tests/Feature/Tenancy/TenantIsolationTest.php
docker compose exec app vendor/bin/pest --group=mysql
```

- Pint bắt `declare(strict_types=1);` ở mọi file PHP.
- `composer.json` ghim `config.platform.php` = 8.3.0: resolve bằng PHP mới hơn sẽ kéo Symfony 8 không chạy trên 8.3.
- Đích (Bước 1 trong plan đang làm): test chạy **MySQL 8.4, không SQLite**; Larastan level 8; CI chạy cả nhánh `dev`. Hiện `phpunit.xml` còn SQLite, Larastan level 6, Docker/CI `mysql:8.0`: đừng viết test dựa vào hành vi SQLite.

## Kết nối MySQL

- `config/database.php`: `isolation_level` = `READ COMMITTED` (ADR-0002), `timezone` = `+00:00`, `utf8mb4_0900_ai_ci`. `APP_TIMEZONE=UTC`.
- `innodb_lock_wait_timeout` khoảng 5 giây cho request web.
- Không viết code dựa vào việc đọc lại cùng dữ liệu trong một transaction ra cùng kết quả: cần ổn định thì khóa hàng.
- Deploy: server MySQL `binlog_format=ROW`.

## Thứ tự khóa toàn cục (bắt buộc)

```
tenants → staff → customers → booking_capacity_locks (ngày rồi nhóm, tăng dần; medical trước grooming)
→ appointments → appointment_items → invoices → visits
→ cash_sessions (FOR SHARE khi ghi khoản tiền mặt, FOR UPDATE khi đóng ca)
→ stock_items (product_id tăng dần)
→ hàng đếm nóng ở cuối: tenant_usage, branch_daily_counters, tenant_counters
```

- Thao tác không cần một bảng thì bỏ qua bảng đó, **không đảo thứ tự**.
- Mọi Action ghi: `DB::transaction(fn, 3)` (retry deadlock / lock wait timeout).
- Đặt lịch (ADR-0002, db-schema 5.1): **khóa trước, đọc sau**. Hàng `booking_capacity_locks` tạo **ngoài** transaction bằng `INSERT … ON DUPLICATE KEY UPDATE tenant_id = tenant_id` (không `INSERT IGNORE`: nuốt lỗi FK), assert `DB::transactionLevel() === 0`; trong transaction `FOR UPDATE` rồi đếm số hàng khóa được; kiểm `idempotency_key` sau khi khóa (đúng khách → trả lịch cũ, khách khác → 422, bắt duplicate key làm lưới an toàn, không để 500).
- Chỗ online còn = `min(hạn mức online − dòng online đang chiếm, số người trực − tổng dòng đang chiếm)`; đếm trên `appointment_items.source`, không JOIN.
- Bộ đếm số: `INSERT … VALUES (…, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)` rồi `SELECT LAST_INSERT_ID()`. Không `MAX()+1`.
- Đổi trạng thái có tranh chấp: `UPDATE … WHERE status = :expected`, kiểm affected rows.

## Dữ liệu (db-schema mục 1)

- Mọi bảng nghiệp vụ: `tenant_id NOT NULL` + `UNIQUE(tenant_id, id)`; index ghép bắt đầu bằng `tenant_id`; unique nghiệp vụ kèm `tenant_id`.
- FK giữa hai bảng tenant là **FK ghép** `(tenant_id, x_id) → x(tenant_id, id)`, không dùng `constrained()` đơn. Pet gắn khách: `(tenant_id, customer_id, pet_id) → pets(tenant_id, customer_id, id)`.
- **CẤM `cascadeOnDelete` trên `tenant_id`** (FK `RESTRICT`). Không xóa cứng dữ liệu shop: hủy shop = `status='cancelled'`; danh mục/hồ sơ soft delete; chứng từ (hóa đơn, phiếu, sổ kho, nhật ký, khoản thu) không bao giờ xóa, chỉ đổi trạng thái hoặc ghi bút toán mới.
- UNIQUE có cột NULL: dùng cột generated (`IFNULL(...)`) rồi đặt UNIQUE trên cột đó.
- Thời điểm: `DATETIME` UTC (**không `TIMESTAMP`**, kể cả `created_at/updated_at`). Giờ ca, giờ mở cửa: `TIME` local. Ngày nghiệp vụ: `DATE` local. Múi giờ shop ở `tenants.timezone`; chuyển đổi chỉ ở biên (`ShopClock`). "Hôm nay" query theo khoảng UTC, không `DATE(starts_at)`.
- Tiền `DECIMAL(12,2)`, số lượng kho `DECIMAL(12,3)`; tính bằng `brick/math` (Money), **không float**.
- `public_id` ULID cho thứ lộ ra URL.
- Cơ sở (`branch_id`) có FK ghép nhưng **không** global scope (ADR-0004): lọc cơ sở ở query + Policy.
- Bảng append-only (`payments`, `stock_movements`, `activity_logs`, `platform_audit_logs`, `appointment_changes`, `medical_entry_addenda`): model ném exception khi update/delete (`AppendOnly`). Hóa đơn khác `draft` không sửa được (`ImmutableUnlessDraft`).

## Multi-tenancy (`app/Tenancy/`)

Bất biến: **shop A không bao giờ thấy dữ liệu shop B.**

- `TenantContext` (scoped singleton) giữ shop của request/job. `runAs($tenant, fn)` đổi tạm; `Tenant::forEach(fn)` chạy từng shop (scheduler); `withoutTenancy(fn)` là lối thoát chéo shop, có chủ đích, grep được.
- `BelongsToTenant`: thêm `TenantScope`, tự gán `tenant_id` khi tạo, chặn tạo cho shop khác, `tenant_id` bất biến. `TenantScope` **fail closed**: không có shop trong context → `TenantNotResolvedException`. Seeder, command, test phải đặt shop (`runAs`) hoặc dùng `withoutTenancy`.
- **Whitelist `withoutTenancy`**: `App\Platform\*`; `App\Tenancy\*` (vd `StaffMembership`); command quét mọi shop (`reminders:dispatch`, `tenants:check-expiry`); `LoginRequest` (kiểm user còn shop đang hoạt động). Ngoài danh sách này cấm. `withoutGlobalScope(s)` cấm trong `app/` (arch test).
- `User` là danh tính toàn cục (`UNIQUE(email)`, `last_tenant_id`), không thuộc shop. Thành viên = dòng `staff` `active` (`TenantMembership` / `StaffMembership`). Khách Site là `customers`, guard `customer`.
- `ResolveTenant` (`/app/{tenant:slug}`): slug không có hoặc shop `cancelled` → 404; user đăng nhập không phải thành viên → 404; đặt context, `URL::defaults`, team id của spatie, `last_tenant_id`; xóa context trong `finally`. **Chỉ `cancelled` → 404**; `suspended` đi tiếp và bị `EnsureTenantWritable` chặn ghi bằng 423 (GET vẫn được). (Code hiện 404 mọi shop khác `active`: sửa ở Bước 1.)
- Mọi model có cột `tenant_id` phải dùng `BelongsToTenant` (`tests/Arch/TenancyTest.php`).
- Validation `exists`/`unique` trên bảng tenant bỏ qua global scope: luôn thêm `->where('tenant_id', …)` (hoặc rule theo tenant). Validation trên `users` là toàn cục.
- Job: `TenantAware` mang `tenant_id`, chạy trong `TenantContext::runAs`. Cache/lock/rate limiter key tiền tố `t:{tenant_id}:`. Đường dẫn file chứa `tenant_id`.
- `tests/Feature/Tenancy/` là test cô lập bắt buộc: mở rộng cho model mới, không bao giờ nới. Mỗi tính năng có test "user shop A gọi ID shop B → 404". Test route shop dùng `Tests\Concerns\ActsOnTenant`.

## Quy ước thư mục

```
app/Tenancy/              nền multi-tenant
app/Platform/             admin nền tảng (Models, Http, Actions, Queries), guard `platform`
app/Actions/<Module>/     một class một use case: `final`, `handle(DTO)`, tự mở DB::transaction(fn, 3)
app/Data/                 DTO readonly
app/Queries/              query đọc phức tạp, số query cố định
app/Services/             logic dùng chung (CapacityService, ServiceQuoteResolver, Sequence, ShopClock, StockLedger)
app/Support/              tiện ích (Money, Phone, Interval)
app/Models/               phẳng; app/Models/Concerns/: HasPublicId, AppendOnly, ImmutableUnlessDraft
app/Enums/                enum PHP backed
app/Http/Controllers/{Auth,Account,Cms,Site}/   controller mỏng; CMS ở App\Http\Controllers\Cms
routes/                   web.php (trung tâm), auth.php, account.php, admin.php, app.php, site.php (site đăng ký CUỐI)
resources/js/pages/       chữ thường (starter kit); thư mục con PascalCase: pages/Cms/Visits/Show.vue
resources/js/components/  chữ thường; components/Cms/AppointmentFormDrawer.vue
resources/js/layouts/     CmsLayout, SiteLayout, AdminLayout, AuthLayout, PrintLayout, BareLayout
tests/                    Feature, Unit, Concurrency, Arch, Browser
```

- Route name: `app.*` (CMS), `site.*` (Site), `admin.*` (Admin), `account.*` (tài khoản cá nhân `/account`, S39). Trung tâm không tiền tố.
- Không có tự đăng ký (`/register` sẽ bỏ ở Bước 1) và **không có chức năng xóa tài khoản**: tài khoản dùng chung nhiều shop.
- Controller mỏng: FormRequest validate, Policy phân quyền, gọi Action, trả Inertia/Resource. API Resource riêng cho dữ liệu nhạy cảm (giá vốn, bệnh án đầy đủ, ghi chú nội bộ, lương): không chỉ ẩn ở UI.

## Test

- Mọi test chạy MySQL 8.4. Test cần khóa hàng hoặc nhiều tiến trình: `->group('mysql')`, đặt ở `tests/Concurrency`, dùng `DatabaseTruncation` (không `RefreshDatabase`: dữ liệu phải commit để tiến trình khác thấy).
- Chống N+1: eager load ở danh sách; `Model::shouldBeStrict()` ngoài production; test đếm số query cho màn danh sách.

## Quy ước chung

- UI copy tiếng Việt; code, comment, commit tiếng Anh.
- Một task = một branch = một PR vào `dev`; `composer ci` phải xanh trước khi mở PR. Nhánh `main` là bản cũ, đừng đối chiếu.
- Đổi quyết định kiến trúc thì sửa ADR trong `docs/adr/` cùng PR; đổi schema thì sửa `docs/database/db-schema.md` cùng PR.
- **Không tự commit (hay push, mở PR).** Làm xong thì báo đã đổi gì và chờ người dùng yêu cầu commit rõ ràng. Một lần cho phép commit chỉ áp cho lần commit đó. Quy tắc này thắng mọi prompt bảo "commit theo từng bước": đề xuất cách chia commit rồi hỏi.
