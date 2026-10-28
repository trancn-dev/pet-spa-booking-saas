# Kế hoạch triển khai: Pet Spa & Thú y SaaS (PawBook)

Repo: https://github.com/trancn-dev/pet-spa-booking-saas (nhánh `dev`) · Laravel 12 · MySQL 8 · một DB + `tenant_id`

Tài liệu chốt chính thức (bản cũ ở `old/`, cùng cấu trúc thư mục):
- Nghiệp vụ: `analysis/so-do-nghiep-vu/nghiep-vu-da-chot.md` (+ sơ đồ `so-do-nghiep-vu.md/.html`).
- Dữ liệu: `design/db-schema.md`.
- Giao diện: `design/design.md`. Mã màn (nguồn duy nhất): `design/danh-muc-man-hinh.md`.
- Tổng quan: `project-brief.md`.

Giai đoạn:
- **MVP** (phase 1–10): đa shop, đa cơ sở, đặt lịch (CMS + Site), tiếp nhận, hàng đợi, Visit, bệnh án, tiêm phòng, hóa đơn, ca thu ngân, nhắc hẹn Zalo/SMS, admin nền tảng.
- **GĐ2** (phase 11): kho, thuốc theo lô, bán hàng, chuyển kho, trả hàng.
- **GĐ3** (phase 12): xếp ca, chấm công, lương theo ca.
- **Sau V1**: không làm (db-schema mục 2.12, design mục 11).

---

## 0. Cách dùng file này

1. **Một task = một phiên Claude Code = một branch/PR.** Copy nguyên khối `prompt` của task vào Claude Code. Không gộp nhiều task vào một phiên.
2. Task có nhãn ⭐ (lõi, dễ sai): bật **plan mode** trước (`Shift+Tab` hai lần), đọc kế hoạch, sửa rồi mới cho chạy.
3. Mọi prompt giả định repo đã có `CLAUDE.md` và `docs/` (task 1.3). Khi tài liệu trong project đổi, copy lại cả bộ vào `docs/`.
4. Sau mỗi task, tự kiểm theo mục **"Xong khi"**. Chưa đạt thì yêu cầu sửa tiếp trong cùng phiên, đừng merge.
5. Thứ tự phase là thứ tự phụ thuộc. Trong cùng phase, task đánh số theo thứ tự nên làm.
6. Ưu tiên **làm sâu** phase 2, 5, 6 (multi-tenant, đặt lịch, hàng đợi, hóa đơn). Phase 9 có thể cắt bớt.

Tham chiếu trong prompt: "db-schema 2.5" = `docs/database/db-schema.md` mục 2.5; "design S2" = màn S2 trong `docs/design/design.md`; "F3" = luồng F3 (design mục 7).

> **Tiến độ thực tế (nhánh `dev`, 2026-10-27):** đã xong phase 1 (Laravel 12 + Docker + Pint/Larastan/Pest + CI, Makefile, mailpit, MySQL UTC), phase 2 + "Kiểm tra & bổ sung" (fail closed cả đọc lẫn ghi, `TenantRule`, `tenant_cache_key`, job `TenantAware`, route binding 404 chéo shop, `Model::shouldBeStrict`, test chạy MySQL), starter kit Vue/Inertia + auth với `users` toàn cục. **Task 3.1: xong** (migration + model + enum + factory cho toàn bộ bảng MVP, test ràng buộc DB ở `tests/Feature/Database/SchemaConstraintsTest.php`; `composer ci` xanh, 83 test). Còn nợ của task 1.2: Larastan level 8, ESLint/Prettier + coverage trong CI. Nhánh `main` là bản cũ: **đừng dựa vào `main`**. **Task 3.2: xong** (`ReferenceDataSeeder` + `DemoSeeder` theo design mục 9; đăng nhập demo `khoa@pawhouse.test` / `password`; roles để task 4.2). **Task 4.1: xong** (khu `/admin`: guard + cookie riêng, đăng nhập + TOTP bắt buộc, `platform:admin-create`, A2 danh sách shop, A6/A7 mời admin; admin demo `admin@pawbook.test`). **Task 4.2: xong** (spatie teams mode `team_id = tenant_id`, danh mục `config/permissions.php` + `php artisan permissions:sync`, 7 role mẫu qua `CreateDefaultRoles`, Policy cho model tenant + phạm vi cơ sở `CurrentBranch`, màn S12.3 `/app/{shop}/settings/roles`, quyền chia sẻ xuống Inertia; test ma trận `tests/Feature/Authorization/`). **Task 4.3: xong** (`ProvisionTenant` là đường duy nhất tạo shop, DemoSeeder dùng nó; `/invite/{token}` S13; bỏ đăng ký; luật đăng nhập khi không còn shop; A3 wizard + A4 tab Tổng quan (gửi lại / sao chép link); khung CMS theo design 3.1, 3.4: token màu + dark mode, workspace switcher, branch switcher `CurrentBranch`; Cài đặt › Cơ sở có kiểm `max_branches`; checklist lần đầu). **Task 4.4: xong** (S11 Nhân viên + tab Lời mời, S11.1 hồ sơ có khối Chuyên môn khi có role Bác sĩ; mời nhiều role + cơ sở chính/phụ, gửi lại, thu hồi; nhân viên không tài khoản; vô hiệu / kích hoạt lại; `StaffQuota` đếm tài khoản active + lời mời còn hạn, khóa hàng `tenants` khi mời, nhận, kích hoạt lại). **Task 4.5: xong** (shop tạm khóa = chỉ đọc: GET được, ghi trả 423 qua `EnsureTenantActive`, chỉ `cancelled` → 404; banner hạn dùng / quá hạn / tạm khóa; `ExtendSubscription` / `SuspendTenant` / `ResumeTenant` đổi status kiểu compare-and-set (`TenantStatusChange`), mỗi thao tác 1 dòng audit; `tenants:check-expiry` hằng ngày (nhắc 7 ngày, quá hạn, tạm khóa sau 7 ngày ân hạn) + container `scheduler`; A4 tab Gói & gia hạn, Nhật ký, modal Tạm khóa; A5). Còn lại cho task sau: Site 'tạm ngưng nhận lịch' (7.1) và job nhắc khách bỏ qua shop tạm khóa (6.1, dùng `TenantStatus::sendsReminders()`). **Task 4.6: xong** (A4 'Đăng nhập như chủ shop' có lý do → token 1 lần 60s → `/app/{slug}/impersonate/{token}`; phiên 30 phút; `ImpersonationGuard` chặn route nhạy cảm, chỉ shop đang hỗ trợ, ghi `impersonation.write`; `activity_logs.impersonation_session_id`; banner đỏ đếm ngược + Thoát; tab Truy cập hỗ trợ ở A4 và Cài đặt › Truy cập hỗ trợ của chủ shop). Phase 4 xong. **Task 5.1: xong** (S10 Khách: SĐT chuẩn hóa E.164 qua value object `App\Support\Phone`, unique theo shop, tạo lại SĐT của hồ sơ đã xóa thì khôi phục hồ sơ đó; tìm theo tên (không phân biệt dấu), tên pet, SĐT đủ, đầu số (`LIKE '+84…%'`) và 4 số cuối (`phone_suffix`) qua `App\Queries\CustomerSearch`; danh sách 20/trang đếm pet, lần đến gần nhất, tổng chi bằng subquery (test đếm query); API combobox `app.customers.search` (10 khách + pet + cảnh báo); hồ sơ khách có pet, lịch hẹn, hóa đơn; pet thêm/sửa trong ngữ cảnh khách, gợi ý cỡ `PetSizeSuggester` (cân nặng thắng, rồi cỡ của giống, có lý do); badge an toàn `PetAlertBadges`; S4 tab Tổng quan + Lịch sử (ghi cơ sở), Bệnh án / Tiêm phòng là chỗ trống cho phase 6; nút 'Thú hoang' tạo 'Thú hoang #n' ở cơ sở đang chọn (số không dùng lại), tab Thú hoang; xóa mềm, chặn khi còn lịch sắp tới; helper test `assertTenantIsolated`). **Task 5.2: xong** (S9 danh sách theo nhóm, form dịch vụ / combo spa (thứ tự dịch vụ con, gợi ý thời lượng = tổng, tổng giá lẻ tham khảo), cơ sở áp dụng (không chọn = mọi cơ sở, scope `Service::availableAt`), chi tiết có bảng quy tắc + ô Thử tính; `SaveService` chặn combo không phải spa, chứa dịch vụ khám, lồng combo, dịch vụ của shop khác, và chặn đổi dịch vụ con sang Khám/combo; `SavePriceRule` tự chặn trùng khi có cột NULL (UNIQUE của MySQL không chặn); `App\Pricing\ServiceQuoteResolver` một query, cụ thể nhất thắng theo từng giá trị (giống 4, loài 2, cỡ 1), `PetProfile` cho Site, `Quote` có explanation "90 phút · áp dụng cho Chó cỡ L"; `phpunit.xml` memory_limit 512M). **Task 5.3: xong** (`App\Scheduling\ShopClock` đổi giờ shop ↔ UTC theo từng ngày (test DST America/New_York); S8 lưới tuần theo người ở cơ sở đang chọn, khung màu theo nhóm trực, khung ở cơ sở khác hiện mờ, ai cũng xem được dòng của mình; `SaveWorkingHour` khóa hàng `staff`, chặn khung chồng giờ kể cả khác cơ sở và khi khoảng hiệu lực giao nhau, chỉ cho cơ sở người đó làm; `App\Scheduling\Roster` đếm người trực theo nhóm của khung (người 2 role đếm 1 lần), bỏ khung bị nghỉ phủ kín, ngày đóng cửa = 0; ngày nghỉ nhập giờ shop lưu UTC, báo số lịch đã gán bị ảnh hưởng (không tự hủy); ngày đóng cửa theo cơ sở hoặc cả shop (cần `branches.all`), chặn trùng kể cả `branch_id` NULL; Cài đặt › Cơ sở › Tỉ lệ đặt online (`OnlineRates`: thứ + giờ → thứ → giờ → mặc định; tối đa = floor(người trực × tỉ lệ) có bù sai số lưu 3 chữ số để 3 × 1/3 = 1 như design S12)). Việc tiếp theo: 5.4 tính chỗ trống theo hạn mức.

### Lộ trình gợi ý (buổi tối/cuối tuần)

| Tuần | Phase |
|---|---|
| 1 | 1. Init + tooling (đã xong) · 1.3 docs |
| 2 | 2. Nền multi-tenant (đã xong, chạy kiểm tra) · 3. Migration + seed |
| 3–4 | 4. Admin → tạo shop → phân quyền (4.1–4.6) |
| 5 | 5a. Khách, pet, dịch vụ, lịch trực (5.1–5.3) |
| 6–7 | 5b. ⭐ Hạn mức + đặt lịch + test concurrency + UI lịch (5.4–5.7) |
| 8–9 | 5c. Tiếp nhận, hàng đợi, Visit, hóa đơn, ca thu ngân (5.8–5.11) |
| 10 | 6. Nhắc hẹn, bệnh án, tiêm phòng |
| 11 | 7. Site của shop |
| 12 | 9. Dashboard, hiệu năng · 10. Deploy, README, ADR |
| 13–15 | **GĐ2** Phase 11: kho, bán hàng, chuyển kho, trả hàng |
| 16–17 | **GĐ3** Phase 12: ca, chấm công, lương |

Phase 8 (thu phí gói tự động) để sau MVP.

---

## Phase 1: Khởi tạo & tooling

### Task 1.1: Khởi tạo Laravel + Docker

```prompt
Repo hiện chỉ có README và LICENSE. Khởi tạo dự án:

- Laravel 12 với starter kit Vue (Inertia 2 + Vue 3 `<script setup>` + TypeScript + Tailwind), cài vào thư mục gốc repo, giữ LICENSE.
- Docker cho môi trường dev bằng docker compose (Laravel Sail cũng được, nhưng service phải có): app (PHP 8.3+), mysql:8.0, redis:7, mailpit. Queue worker chạy riêng sau khi có Horizon (để TODO trong compose).
- Thời gian: `config/app.php` timezone = 'UTC'; connection mysql thêm `'timezone' => '+00:00'`; charset utf8mb4.
- `.env.example` đủ biến cho docker (DB_HOST=mysql, REDIS_HOST=redis, MAIL_HOST=mailpit), QUEUE_CONNECTION=redis, CACHE_STORE=redis, SESSION_DRIVER=redis.
- `Makefile` (hoặc composer scripts): up, down, sh, test, lint, analyse, fresh (migrate:fresh --seed).
- README: mục "Chạy local" ngắn.

Chạy `php artisan migrate` và `npm run build` trong container để chứng minh chạy được. Commit theo từng bước hợp lý.
```

**Xong khi:** `docker compose up -d && make fresh` chạy sạch; đăng nhập được; `SELECT @@session.time_zone` từ app trả `+00:00`.

### Task 1.2: Chất lượng code & CI

```prompt
Thêm tooling chất lượng cho repo Laravel 12 hiện tại:

- Pest 3 (+ pest-plugin-laravel, pest-plugin-arch). Test chạy trên **MySQL thật** (DB `testing` riêng), không SQLite: dự án dựa vào FK ghép, lock, cột generated và hành vi DATETIME của MySQL.
- Larastan level 8, `phpstan.neon` baseline rỗng. Fix lỗi hiện có thay vì baseline.
- Laravel Pint (preset laravel) + ESLint/Prettier cho resources/js.
- Arch test cơ bản: không dùng `dd`, `dump`, `ray`; `App\Models` extend Model; `declare(strict_types=1)` cho app/.
- GitHub Actions `.github/workflows/ci.yml`: job lint (pint --test, eslint), analyse (phpstan), test (service mysql:8.0 + redis, `pest --parallel --coverage --min=70`), build assets. Cache composer + npm.
- AppServiceProvider (không phải production): `Model::shouldBeStrict()`.
- Badge CI vào README.
```

**Xong khi:** PR có CI xanh cả 3 job; cố tình viết 1 lazy load trong test thì test fail.

### Task 1.3: CLAUDE.md + tài liệu thiết kế trong repo

> Trước khi chạy: copy từ project vào repo (Claude Code trên máy bạn không đọc được thư mục project): `design/db-schema.md` → `docs/database/`, `design/design.md` và `design/danh-muc-man-hinh.md` → `docs/design/`, `plan/implementation-plan.md` → `docs/plan/`, `analysis/so-do-nghiep-vu/nghiep-vu-da-chot.md` → `docs/analysis/`, `project-brief.md` → `docs/`. Xóa trong `docs/` các bản cũ (ảnh ERD cũ, file đặt tên theo phiên bản). ERD nằm dạng mermaid trong db-schema.md mục 4.

```prompt
Thư mục docs/ có database/db-schema.md, design/design.md, design/danh-muc-man-hinh.md, plan/implementation-plan.md, analysis/nghiep-vu-da-chot.md, project-brief.md. Tạo hoặc cập nhật:

1. `CLAUDE.md` ở gốc repo (tiếng Việt, ngắn, gạch đầu dòng):
   - Mô tả dự án 3 dòng, link tới docs/.
   - Lệnh: test, lint, phpstan, migrate fresh (theo Makefile).
   - Quy ước dữ liệu (lấy từ docs/database/db-schema.md mục 1): mọi bảng tenant có tenant_id + UNIQUE(tenant_id,id); FK giữa 2 bảng tenant là FK ghép (tenant_id, x_id) → (tenant_id, id); pet gắn khách bằng FK ghép (tenant_id, customer_id, pet_id); index ghép bắt đầu bằng tenant_id; DATETIME lưu UTC, giờ ca TIME local, ngày nghiệp vụ DATE local, một múi giờ mỗi shop (tenants.timezone); tiền DECIMAL(12,2), số lượng kho DECIMAL(12,3); public_id ULID cho thứ lộ ra URL; soft delete cho danh mục/hồ sơ, chứng từ không bao giờ xóa; cơ sở (branch_id) không có global scope.
   - Quy ước code: PHP 8.3 typed, `declare(strict_types=1)`, logic nghiệp vụ trong `app/Actions/*` (một class một use case, method `handle`), DTO readonly class, query phức tạp trong Query class, Controller mỏng, FormRequest validate, Policy phân quyền, API Resource riêng cho dữ liệu nhạy cảm (giá vốn, bệnh án đầy đủ, lương). Vue 3 `<script setup lang="ts">`, Inertia.
   - Quy tắc tenant: không gọi `withoutGlobalScope(s)` ngoài whitelist; validation `exists` kèm where tenant_id; cache/lock key prefix `t:{tenant_id}:`; job TenantAware.
   - Thuật ngữ: shop = tenant, cơ sở = branch, khách = customers (không phải owners), chủ shop = role owner, Visit = lượt khám/spa.
   - Thứ tự khóa (db-schema mục 1): customers → booking_capacity_locks → tenant_usage; invoices → visits → stock_items. Không INSERT IGNORE hàng khóa trong transaction.
   - Màn hình gọi bằng mã + tên theo docs/design/danh-muc-man-hinh.md (vd `S17 Visit`).
   - Mỗi tính năng có Pest test, gồm test cô lập tenant (user shop A truy cập ID shop B → 404).
   - Không sửa migration đã lên production; tạo migration mới.
2. `docs/adr/0000-template.md` (Context, Decision, Alternatives, Consequences) và `docs/adr/README.md` liệt kê 8 ADR ở db-schema mục 6 (đánh số 0001–0008, trạng thái "chưa viết" tới khi task tương ứng viết).
3. `docs/architecture.md` với các mục trống: Tổng quan, Multi-tenancy, Đặt lịch & hạn mức, Hàng đợi & Visit, Hóa đơn, Queue & nhắc hẹn, Admin nền tảng.
```

**Xong khi:** mở phiên Claude Code mới, hỏi "quy ước FK giữa 2 bảng tenant là gì" và "khách lưu ở bảng nào" → trả lời đúng mà không cần đọc lại docs.

---

## Phase 2: Nền multi-tenant ⭐

### Task 2.1: Tenant context + BelongsToTenant

```prompt
Đọc CLAUDE.md, docs/database/db-schema.md mục 1, 2.1 (tenants) và 5.2. Làm nền multi-tenant (một DB + tenant_id), KHÔNG dùng stancl/tenancy:

- Migration `tenants` đúng schema (public_id ULID, slug UNIQUE, timezone IANA, locale, currency, plan_id, status, plan_expires_at…). Model Tenant.
- `App\Tenancy\TenantContext` (scoped singleton): set/get/clear/has, `require()` ném `TenantNotResolvedException`. Helper `tenant()`.
- Trait `BelongsToTenant`: global scope `TenantScope` lọc theo tenant hiện tại; tự gán tenant_id khi `creating`; **fail closed**: query hoặc create khi chưa có tenant context thì ném exception. Quan hệ `tenant()`.
- Chạy có chủ đích không có tenant (scheduler, admin): `TenantContext::runAs(Tenant $t, callable)`, `Tenant::forEach(callable)`, `TenantContext::withoutTenancy(callable)` (chỉ cho App\Platform và command được liệt kê).
- Middleware `ResolveTenant`: route nhóm `/app/{tenant:slug}`; check user là thành viên (dòng `staff` active); set TenantContext; lưu users.last_tenant_id.
- Octane/queue: context được clear giữa request/job.
- Test Pest: scope lọc đúng, fail closed, auto gán tenant_id, không ghi đè tenant_id khác context (ném exception), runAs khôi phục context cũ kể cả khi callable ném lỗi.
- `docs/adr/0001-single-db-tenant-id.md` (so với DB riêng mỗi shop / stancl/tenancy); cập nhật mục Multi-tenancy trong docs/architecture.md.
```

**Xong khi:** test xanh; đọc trait thấy không có đường nào trả dữ liệu khi thiếu context.

### Task 2.2: Lưới an toàn chống rò rỉ tenant

```prompt
Đọc CLAUDE.md và docs/database/db-schema.md mục 5.2. Thêm các lớp bảo vệ:

- Rule `TenantExists::in('pets')` (hoặc macro `Rule::tenantExists`) = Rule::exists(...)->where('tenant_id', tenant()->id).
- Route model binding: model BelongsToTenant resolve qua scope → ID shop khác trả 404 (test).
- Cấm `withoutGlobalScope`/`withoutGlobalScopes` ngoài whitelist: ưu tiên PHPStan custom rule ở `tools/phpstan`.
- Helper test: `actingAsStaffOf($tenant, role)` và `assertTenantIsolated(routeName, factory)`: tạo bản ghi ở shop B, user shop A gọi show/update/delete → 404.
- Cache: `tenant_cache_key(string $key)` → `t:{id}:{key}`; rate limiter key cũng prefix tenant.
- Job: trait `TenantAware` + job middleware set TenantContext từ tenant_id đã serialize; dispatch trong context thì tự gắn tenant_id. Test: job dispatch ở shop A chạy đúng context A dù worker không có context.
```

**Xong khi:** helper dùng được trong 1 dòng; PHPStan báo lỗi khi thêm `withoutGlobalScopes()` vào controller.

**Kiểm tra & bổ sung (phase 1–2 đã làm trên `dev`):**

```prompt
Đọc CLAUDE.md và docs/database/db-schema.md mục 1, 5.2. Repo đã có nền multi-tenant. Đối chiếu code hiện tại với các yêu cầu sau, liệt kê đạt / thiếu, rồi bổ sung phần thiếu kèm test: global scope fail closed; auto gán tenant_id; runAs/forEach/withoutTenancy thay cho withoutGlobalScope; middleware resolve tenant theo slug + check membership qua staff; Rule exists theo tenant; route binding 404 chéo shop; chặn withoutGlobalScopes bằng PHPStan/arch; job TenantAware; cache key prefix tenant; Model::shouldBeStrict; test chạy trên MySQL; CI xanh.
```

---

## Phase 3: Migration, model, seed

### Task 3.1: Migration toàn bộ schema MVP ⭐

```prompt
Đọc CLAUDE.md và docs/database/db-schema.md (toàn bộ, đặc biệt mục 1, 2.1–2.8, 2.10, 4.1). Nhánh dev có thể còn migration nghiệp vụ cũ (bảng owners, appointments 1 pet…): xóa/viết lại để khớp db-schema hiện tại (chưa có production). Tạo migration + model + factory cho TẤT CẢ bảng MVP: mục 2.1 (trừ bảng spatie: task 4.2), 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.10. Mục 2.9 (GĐ2) làm ở phase 11, 2.11 (GĐ3) ở phase 12. Cột GĐ2/GĐ3 nằm trong bảng MVP (vd visit_items.product_id, branches.allowed_ips, staff.pin_hash) vẫn tạo, nullable, FK tới bảng GĐ sau thêm ở migration của phase đó.

Yêu cầu:
- Đúng kiểu cột, enum, index, unique, CHECK như tài liệu. DATETIME (không TIMESTAMP) cho mọi thời điểm; `created_at/updated_at` cũng `datetime` (macro Blueprint `datetimesUtc()` nếu cần).
- Mọi bảng tenant: `UNIQUE(tenant_id, id)` và FK ghép tới bảng tenant khác: `$table->foreign(['tenant_id','customer_id'])->references(['tenant_id','id'])->on('customers')`. FK tới bảng dùng chung (species, breeds, users, plans) là FK đơn.
- `pets` có `UNIQUE(tenant_id, customer_id, id)`; `appointment_items` và `visits` dùng FK ghép `(tenant_id, customer_id, pet_id) → pets(tenant_id, customer_id, id)`. `appointments` có `UNIQUE(tenant_id, id, customer_id, branch_id)`, `appointment_items` FK ghép `(tenant_id, appointment_id, customer_id, branch_id)` tới đó. Liên kết lịch → Visit là `appointment_items.visit_id` (không có `visits.appointment_item_id`). Cột generated `customers.phone_suffix` + index. Visit cấp cứu có customer_id, pet_id NULL vẫn hợp lệ (thú hoang dùng hồ sơ hệ thống + pet is_stray) (FK bỏ qua khi có cột NULL).
- Cột generated: `invoices.open_draft_key` (chỉ khi draft + kind=service + payer_name NULL; + UNIQUE(tenant_id, open_draft_key)), `cash_sessions.open_key` (+ UNIQUE). CHECK: `customers` phone hoặc is_system; `invoices` kind='service' thì có customer_id; `payments` kind payment/refund bằng cash thì có cash_session_id (bút toán transfer_in/out không cần); `invitations` đúng một người mời; `visit_items` theo item_type.
- `tenant_settings` một dòng mỗi shop (tạo cùng shop ở task 4.3).
- Thứ tự migration đúng phụ thuộc (invoices trước khi thêm FK visits.invoice_id; vòng phụ thuộc thì thêm FK ở migration sau).
- Model: BelongsToTenant cho bảng tenant; HasUlids/public_id cho customers, appointments, visits, invoices, branches; SoftDeletes theo mục 1; `casts()` đầy đủ, enum PHP backed ở app/Enums (AppointmentStatus, AppointmentItemStatus, ServiceGroup, VisitStatus, InvoiceStatus, InvoiceKind, PaymentMethod, ReminderPurpose, MessageChannel…); quan hệ đầy đủ. Hằng `AppointmentItem::OCCUPYING_STATUSES = [booked, checked_in]`.
- Factory cho mọi model, tenant-aware (con dùng cùng tenant với cha; pet và khách của nó cùng tenant; appointment_items dùng pet của đúng khách của lịch).
- Test DB-level (`->group('mysql')`, insert thẳng bằng DB::table, kỳ vọng QueryException):
  - FK ghép chặn gắn pet shop A vào customer shop B, appointment vào branch shop khác.
  - appointment_items với pet của khách khác → lỗi FK.
  - 2 hóa đơn draft dịch vụ cùng khách, cùng cơ sở, cùng ngày → lỗi unique; hóa đơn draft thứ hai sau khi cái đầu completed → được; draft retail cùng khách cùng ngày với draft dịch vụ → được; 2 hóa đơn thú hoang có người trả cùng ngày → được.
  - hóa đơn service thiếu customer_id → lỗi CHECK; payment cash không có cash_session_id → lỗi CHECK.
  - 2 ca thu ngân mở cùng két → lỗi unique; đóng ca rồi mở ca mới → được.
  - 2 visit cùng (branch, ngày, queue_no) → lỗi unique; appointment_items trỏ appointment với customer_id/branch_id khác lịch → lỗi FK.
```

**Xong khi:** `make fresh` sạch; test DB-level xanh; `php artisan model:show Appointment` thấy quan hệ `items`, `changes`, `customer`, `branch` và cast enum.

### Task 3.2: Seeder dữ liệu demo

```prompt
Đọc docs/design/design.md mục 9 (dữ liệu mẫu) và docs/database/db-schema.md mục 2.1–2.8. Viết seeder:
- `ReferenceDataSeeder` (mọi môi trường, idempotent bằng upsert): plans (Starter 1 cơ sở/3 nhân viên/150 lịch; Pro 3/10/500; Clinic không giới hạn = null), species (dog, cat), breeds phổ biến VN có default_size.
- `DemoSeeder` (chỉ local/demo), đúng design mục 9:
  - Shop "Paw House Spa & Vet" slug paw-house, tz Asia/Ho_Chi_Minh, 2 cơ sở Q3 (chính, 08:00–20:00, nghỉ 02/09 trong branch_closures) và Q7; mỗi cơ sở một hồ sơ hệ thống "Thú hoang / vô chủ" (customers.is_system) và một két "Két 1"; online_booking_rates Q3: medical 1/3, grooming 1/2.
  - 9 nhân viên đúng tên/màu/cơ sở (role gán sau ở task 4.2: để TODO); Lê Văn Tư không tài khoản (user_id NULL). Lịch trực staff_working_hours có service_group (bác sĩ medical, spa grooming, lễ tân/kho NULL); Trần Minh Khoa chỉ đếm một nhóm mỗi khung; một người ca gãy, một người nghỉ chiều nay (staff_time_off).
  - Dịch vụ + service_price_rules S/M/L, combo CB01 (chỉ grooming) qua service_combo_items.
  - Khách & pet như bảng (dị ứng, "sợ máy sấy"); Mochi có medical_records HS-000101 (tenant_counters cập nhật tương ứng).
  - Phác đồ vaccine 7 bệnh + tẩy giun 90 ngày; pet_vaccinations để Sushi đang mũi 2/4 và Tôm quá hạn tẩy giun 12 ngày.
  - Hôm nay ở Q3 (tính theo ngày hiện tại, giờ shop → UTC): 14 lịch đủ trạng thái, có lịch 2 pet (Mochi + Bơ, 14:00), 2 lịch online chưa phân người; hàng đợi 9 Visit (6 khám, 3 spa), 1 cấp cứu, 1 "Thú hoang #3"; 2 hóa đơn draft từ hôm qua; ca thu ngân Két 1 đang mở; vài hóa đơn completed.
- Tenant thứ hai nhỏ ("Meow Clinic") để demo cô lập dữ liệu; thêm Happy Tails (dùng thử), Bé Cún Spa (tạm khóa) kèm tenant_subscriptions theo design mục 9. Ở task này tạo tenant trực tiếp; task 4.3 đổi sang gọi `ProvisionTenant`. Admin demo thêm ở task 4.1.
- Seeder chạy lại được (`migrate:fresh --seed`), không phụ thuộc thứ tự ngẫu nhiên.
```

**Xong khi:** `make fresh` ra dữ liệu đúng design mục 9; DB lưu giờ UTC (08:00 VN = 01:00).

---

## Phase 4: Admin nền tảng → tạo shop → phân quyền

> **Thứ tự:** có admin trước, admin tạo shop, chủ shop nhận lời mời rồi mới vào CMS. **Không có tự đăng ký.**
> ```
> 4.1 Admin đăng nhập + khung  →  4.2 Roles & permissions  →  4.3 Admin tạo shop + chủ shop nhận lời mời + CMS
>                                                          →  4.4 Chủ shop mời nhân viên (dùng lại luồng nhận lời mời của 4.3)
>                                                          →  4.5 Gói/hạn, tạm khóa  →  4.6 Đăng nhập thay
> ```
> - `users` là danh tính toàn cục (UNIQUE email), thành viên shop = dòng `staff`, route shop `/app/{tenant:slug}`.
> - Chủ shop và nhân viên **đều vào shop qua `invitations`**: một luồng nhận lời mời duy nhất.
> - Từ 4.3, **mọi shop (kể cả DemoSeeder) đều tạo qua `ProvisionTenant`**.

### Task 4.1: Khu quản trị nền tảng: đăng nhập + khung ⭐

```prompt
Đọc CLAUDE.md, docs/database/db-schema.md mục 2.1 (platform_admins, platform_audit_logs) và 5.7, docs/design/design.md mục 4 và 6.10 (A1, A2). Làm khu /admin cho nhà cung cấp, CÙNG app. Bảng `platform_admins` đã có từ task 3.1.
- Model `App\Platform\Models\PlatformAdmin`. Guard `platform` (session) với provider riêng. Không thêm cột quyền vào `users`.
- Route group `/admin`, KHÔNG có middleware tenant; middleware group riêng đặt session cookie riêng (`pawbook_admin_session`) TRƯỚC StartSession: đăng xuất CMS không mất phiên admin và ngược lại.
- Đăng nhập email + mật khẩu + TOTP (2FA bắt buộc, màn bật 2FA lần đầu), rate limit 5 lần/phút theo email+IP. Command `platform:admin-create {email} {--role=super_admin}`: cách DUY NHẤT tạo admin đầu tiên; không có trang đăng ký admin.
- Layout admin theo design 6.10 (sidebar tối, badge môi trường). Màn A2 danh sách shop (chỉ đọc ở task này): `App\Platform\Queries\TenantList` dùng `TenantContext::withoutTenancy()`, đếm cơ sở/nhân viên/lịch tháng bằng subquery (không N+1, assert số query cố định). Empty state "Chưa có shop nào".
- Arch test: cấm gọi `withoutTenancy` ngoài `App\Platform\*` và command đã liệt kê.
- A6 Quản lý admin (super_admin): mời admin qua `platform_admin_invitations` (token 32 byte, lưu sha256, 1 lần, hạn 72 giờ) → A7 `/admin/invite/{token}` đặt mật khẩu → bắt buộc bật 2FA; vô hiệu hóa (không xóa), không tự vô hiệu chính mình.
- DemoSeeder: thêm 2 admin theo design mục 9 (bỏ qua 2FA chỉ khi APP_ENV=local/demo; test production không bỏ qua được).
- Test: user guard web gọi /admin/* → 404; admin chưa bật 2FA không vào được A2; support không thấy nút tạo shop và không mời được admin; token mời dùng 2 lần / hết hạn → 404; đăng xuất web không đăng xuất admin.
- docs/adr/0008-platform-admin.md (cùng app vs app riêng; bảng riêng vs is_admin; đăng nhập thay bằng token 1 lần).
```

**Xong khi:** `php artisan platform:admin-create` → đăng nhập `/admin` với 2FA → thấy danh sách shop; mọi test cô lập tenant cũ vẫn xanh.

### Task 4.2: Roles & permissions (spatie teams mode)

```prompt
Đọc CLAUDE.md, docs/database/db-schema.md mục 3 và docs/design/design.md mục 2, 8 (ma trận quyền). Cài spatie/laravel-permission teams mode, team_id = tenant_id:
- Role theo shop. Sinh sẵn 7 role mẫu khi tạo shop (gọi từ `ProvisionTenant` ở task 4.3): owner, branch_manager, receptionist, cashier, vet, groomer, inventory, gán permission đúng **design mục 8**. Cột `roles.display_name` (tên hiển thị, chủ đổi được; `name` là mã ổn định, role tự tạo sinh `custom_{id}`). Màn Cài đặt › Phân quyền S12.3: đổi tên role, sửa permission của role, tạo role mới; role owner không sửa/xóa được; mọi thay đổi ghi activity_logs. Danh mục permission định nghĩa trong code (`config/permissions.php`: mã, tên VN, mô tả, module, cờ nhạy cảm), shop không tạo/đổi tên; permission mới mặc định tắt trừ owner. Quyền nhạy cảm (`inventory.view_cost`, `purchase.cost`, `invoices.amend`, `branches.all`, `payroll.view`) mặc định chỉ owner, gán cho role khác cần xác nhận + activity_logs; `roles.manage` chỉ owner, không gán được cho role khác. Một staff nhiều role → cộng quyền.
- Danh sách permission theo module (appointments.*, customers.*, pets.*, visits.*, invoices.*, cash_sessions.*, services.manage, staff.manage, roles.manage, branches.manage, settings.manage, reports.view_today (lễ tân) / reports.view_branch (QL chi nhánh) / reports.view_all (chủ shop), activity_logs.view…) cộng các quyền có ảnh hưởng dữ liệu ở db-schema 3.2: `medical.view_full`, `medical.view_alerts`, `inventory.view_cost`, `purchase.cost`, `inventory.approve`, `invoices.void_draft`, `invoices.amend`, `returns.create`, `returns.overdue`, `branches.all`, `payroll.view`. Tạo sẵn tên cho GĐ2/GĐ3 (inventory.*, inventory.override_batch, inventory.alerts, shifts.*, attendance.*) để role mẫu đủ ngay.
- Middleware ResolveTenant gọi `setPermissionsTeamId(tenant()->id)`; clear cache permission đúng cách khi đổi shop.
- Role/permission CHỈ cho guard `web`. Admin nền tảng dùng `platform_admins.role`.
- Phạm vi cơ sở: mọi role trừ người có `branches.all` chỉ thao tác ở cơ sở trong staff_branches (Policy + helper `CurrentBranch::authorize`).
- Policy cho từng model tenant. Không dùng Gate::before cho owner: chủ shop không tự ghi bệnh án (kiêm bác sĩ thì gán thêm role vet).
- Chia sẻ quyền xuống Inertia (`can` props) để UI disable nút kèm tooltip.
- Test: ma trận role × hành động theo design mục 8 (Pest dataset); gán `roles.manage` cho role khác → từ chối; gán `inventory.view_cost` cho QL → QL thấy giá vốn và có 1 dòng activity_logs; đổi display_name không đổi `name`; user là owner shop A nhưng groomer shop B thì quyền đúng theo shop đang mở; chủ shop không có role vet gọi ghi bệnh án hoặc nhận Visit khám → 403.
```

**Xong khi:** test ma trận xanh; đổi shop thì quyền đổi theo.

### Task 4.3: Admin tạo shop → chủ shop nhận lời mời → vào CMS ⭐

```prompt
Đọc docs/database/db-schema.md mục 2.1, 2.2 (branches, staff, invitations) và docs/design/design.md mục 4, S13, 6.10 (A3, A4 tab Tổng quan), F18, F19. Làm luồng bán shop từ đầu đến cuối:
- Action `ProvisionTenant` (một transaction): tenant (status `trial` hoặc `active`) + tenant_settings mặc định + cơ sở đầu tiên + két "Két 1" + hồ sơ hệ thống "Thú hoang / vô chủ" của cơ sở + 7 role mẫu (task 4.2) + `tenant_subscriptions` đầu + `invitations` role `owner` (`invited_by_admin_id`, hạn 7 ngày) + `platform_audit_logs` `tenant.created`. Sau commit queue mail lời mời. Đây là đường DUY NHẤT tạo tenant: refactor DemoSeeder và factory "tenant đầy đủ" gọi Action này (seeder tự nhận lời mời thay chủ shop demo).
- Slug: unique + danh sách cấm gồm mọi segment route trung tâm (admin, app, api, login, logout, register, dashboard, settings, forgot-password, reset-password, verify-email, confirm-password, invite, email, storage, build, www…); test tự quét `Route::getRoutes()` lấy segment đầu để danh sách cấm không thiếu khi thêm route mới (Site nằm ở `/{slug}`, task 7.1).
- Nhận lời mời (route trung tâm `/invite/{token}`, dùng chung cho chủ shop và nhân viên ở 4.4): token 32 byte, DB lưu sha256, 1 lần. Email chưa có user → form tạo tài khoản ngay trên trang; đã có user → đăng nhập đúng email đó rồi nhận. Accept trong transaction, idempotent: tạo staff + gán role + staff_branches; redirect `/app/{slug}` với checklist lần đầu S13 (Thêm dịch vụ, Cài lịch trực, Cài tỉ lệ đặt online, Mời nhân viên, Chia sẻ link Site).
- Không tự đăng ký: gỡ route `register` của starter kit và link "Đăng ký". Đăng nhập: sau khi đúng mật khẩu, nếu user không còn dòng `staff` active ở shop nào chưa hủy (bị vô hiệu ở mọi shop, hoặc shop cancelled) → không tạo phiên, báo lỗi ở form "Tài khoản không còn hoạt động ở shop nào. Liên hệ chủ shop." Đang trong phiên mà staff bị vô hiệu → `ResolveTenant` đăng xuất và về /login (nếu còn shop khác thì chuyển sang shop đó). Test: staff disabled ở shop duy nhất → không đăng nhập được; disabled ở shop A nhưng active ở shop B → đăng nhập vào B, A không có trong switcher.
- Màn A3 wizard 3 bước + A4 tab Tổng quan (chủ shop "Chưa nhận lời mời · Gửi lại / Sao chép link"; gửi lại = token mới, vô hiệu token cũ). Support chỉ xem.
- CMS layout theo design 3.4, 3.5 (sidebar, topbar có tên shop + workspace switcher + branch switcher). Token màu design 3.1 làm CSS variables + Tailwind theme, có dark mode.
- Branch switcher: `CurrentBranch` trong session, chỉ chọn cơ sở trong staff_branches (chủ shop thấy thêm "Tất cả" ở báo cáo). Màn Cài đặt › Cơ sở (CRUD, giờ mở cửa); thêm cơ sở kiểm `plans.max_branches`.
- Test: provision tạo đủ bản ghi và rollback toàn bộ nếu một bước lỗi; slug trùng/cấm; token sai/hết hạn/đã dùng; email đăng nhập khác email được mời → từ chối; nhận 2 lần không tạo 2 staff; route /register không tồn tại; vượt max_branches → từ chối; user của shop mới không thấy dữ liệu shop khác.
```

**Xong khi:** admin tạo shop ở `/admin` → Mailpit có lời mời → chủ shop tạo tài khoản ở `/invite/...` → vào `/app/{slug}` thấy checklist; `migrate:fresh --seed` vẫn ra đủ shop demo qua ProvisionTenant.

### Task 4.4: Mời nhân viên

```prompt
Đọc docs/database/db-schema.md mục 2.2 (staff, staff_branches, invitations) và docs/design/design.md S11, F18. Làm luồng chủ shop mời nhân viên, dùng lại trang `/invite/{token}` của task 4.3:
- Chủ shop mời email + một hoặc nhiều role (`invitations.role_names`, không mời được role owner) + cơ sở chính/phụ (`primary_branch_id`, `branch_ids`). Token 32 byte gửi qua mail (queued), DB chỉ lưu sha256. Hết hạn 7 ngày. Gửi lại = token mới, vô hiệu token cũ. Thu hồi được.
- Giới hạn `plans.max_staff` (đếm nhân viên có tài khoản đang hoạt động, kể cả chủ shop, + lời mời còn hạn): kiểm khi mời, khi accept VÀ khi kích hoạt lại nhân viên disabled, `lockForUpdate` hàng tenant để 2 thao tác đồng thời không vượt.
- Trang Nhân viên S11: bảng staff (màu, role, chức danh `title`, cơ sở, status), tab Lời mời; vô hiệu nhân viên (status disabled, không xóa).
- Form hồ sơ nhân viên: thông tin cá nhân, liên hệ, chức danh hiển thị, role, cơ sở; khối chuyên môn (`license_no`, `qualifications`) hiện khi có role vet. Quyền lấy từ role, không từ chức danh.
- Nhân viên **không tài khoản** (bảo vệ, lao công): user_id NULL, không gửi lời mời, không tính vào max_staff; gán cơ sở.
- Test: token sai/hết hạn/đã dùng, email không khớp, vượt quota (kể cả 2 accept song song, `->group('mysql')`), cô lập tenant; người đã là nhân viên shop A nhận lời mời shop B → 2 workspace trong switcher.
```

**Xong khi:** mời → mail ở Mailpit → accept → thấy workspace trong switcher.

### Task 4.5: Gói & hạn dùng, tạm khóa ⭐

```prompt
Đọc docs/database/db-schema.md mục 2.1 (tenants, tenant_subscriptions, platform_audit_logs) và docs/design/design.md 6.10 (A4 tab Gói & gia hạn, Nhật ký; A5), F20. Làm vòng đời sau khi tạo shop:
- Action `ExtendSubscription` (nối tiếp hạn hiện tại, thêm dòng tenant_subscriptions, cập nhật tenants.plan_id/plan_expires_at cùng transaction), `SuspendTenant`, `ResumeTenant`: đổi status bằng compare-and-set (`WHERE status = :expected`), mỗi action đúng 1 dòng `platform_audit_logs` (append-only: model ném exception khi update/delete).
- `ResolveTenant`: trial/active/past_due/suspended đi tiếp, chỉ `cancelled` → 404. Middleware `EnsureTenantActive` ngay sau: suspended → request ghi trả 423 + trang thông báo, GET vẫn được; Site hiện "tạm ngưng nhận lịch". Banner hạn dùng/quá hạn/tạm khóa trong CMS (Inertia shared props).
- Màn A4 tab Gói & gia hạn + modal Tạm khóa, tab Nhật ký; A5 nhật ký toàn hệ thống. Support chỉ xem.
- Command `tenants:check-expiry` hằng ngày: còn ≤ 7 ngày → mail nhắc (dedupe theo tenant + ngày hết hạn); quá hạn → past_due; quá 7 ngày ân hạn → suspended. Job nhắc khách bỏ qua shop suspended.
- Test: suspended chặn POST nhưng cho GET; cancelled → 404; check-expiry chuyển đúng trạng thái theo mốc (Carbon::setTestNow); job và admin đồng thời đổi status không ghi đè nhau; gia hạn khi đang suspended do hết hạn → active ngay; mỗi action đúng 1 dòng audit.
```

**Xong khi:** khóa shop thì CMS không tạo được lịch nhưng vẫn xem được; gia hạn xong mở lại ngay; A5 thấy đủ log.

### Task 4.6: Đăng nhập thay chủ shop, có ghi log ⭐

```prompt
Đọc docs/database/db-schema.md mục 2.1 (impersonation_sessions), 5.7 và docs/design/design.md 6.10 (A4), F21. Làm "Đăng nhập như chủ shop":
- Admin nhập lý do (bắt buộc) → tạo `impersonation_sessions`, token 32 byte (DB lưu sha256, hết hạn 60s) → mở tab mới tới `/app/{slug}/impersonate/{token}`. Cần token vì phiên admin dùng cookie riêng.
- Route `/app/{slug}/impersonate/{token}`: tiêu thụ token 1 lần bằng UPDATE có điều kiện (affected = 0 → 404; token phải thuộc đúng shop của slug), đăng xuất user web hiện tại nếu có, đăng nhập guard web là chủ shop, session lưu impersonation_session_id; `Referrer-Policy: no-referrer`.
- Middleware `ImpersonationGuard`: hết 30 phút → kết thúc + logout; chặn route nhạy cảm (đổi mật khẩu/email, lương, sửa/hủy hóa đơn, xóa, mời/khóa nhân viên, đổi quyền) theo danh sách route name; mọi request ghi khác ghi `platform_audit_logs` action `impersonation.write`; `activity_logs` của shop ghi kèm `impersonation_session_id` (S32 hiện "Admin … thực hiện thay"). Trong phiên, workspace switcher chỉ có shop đang hỗ trợ.
- Banner đỏ đếm ngược + nút Thoát (ghi ended_at). Inertia prop `impersonating` để UI disable nút bị chặn.
- Tab "Truy cập hỗ trợ" ở A4 và "Lịch sử truy cập hỗ trợ" trong Cài đặt của chủ shop (chỉ thấy phiên của shop mình).
- Test: token dùng 2 lần / hết hạn / dùng với slug shop khác → 404; route nhạy cảm bị chặn; request ghi tạo audit; hết 30 phút bị logout; chủ shop B không thấy phiên của shop A; không chuyển sang shop khác trong phiên.
```

**Xong khi:** từ A4 bấm "Đăng nhập như chủ shop" → tab mới vào CMS có banner đỏ → tạo 1 lịch → A4 thấy log; bấm Thoát thì phiên đóng.

---

## Phase 5: Nghiệp vụ lõi

### Task 5.1: Khách & thú cưng

```prompt
Đọc docs/database/db-schema.md mục 2.3 và docs/design/design.md S4, S10. Làm CRUD:
- `customers`: SĐT là khóa khách, unique theo shop, dùng chung mọi cơ sở; chuẩn hóa SĐT VN sang E.164 bằng value object `Phone` (dùng lại cho Site, OTP, Zalo/SMS); địa chỉ tùy chọn (không thu CCCD). Unique (tenant_id, phone) báo lỗi thân thiện.
- Hồ sơ hệ thống "Thú hoang / vô chủ" mỗi cơ sở (is_system, phone NULL, không sửa/xóa, ẩn khỏi danh sách khách); action tạo nhanh pet "Thú hoang #n" (`is_stray`).
- Danh sách khách: tìm theo tên/SĐT (4 số cuối qua cột `phone_suffix` có index, không `LIKE '%…'`), phân trang, đếm số pet và lần đến gần nhất KHÔNG N+1 (withCount, subquery).
- Pets: thêm/sửa trong ngữ cảnh khách; gợi ý size từ breed.default_size + weight (`PetSizeSuggester`, unit test); `allergies`, `chronic_conditions`, `behavior_notes` ("cắn người", "sợ máy sấy") là cảnh báo: badge ở mọi nơi có pet (design mục 10.6).
- Hồ sơ pet S4: tab Tổng quan + Lịch sử (mỗi sự kiện ghi cơ sở phát sinh); tab Bệnh án, Tiêm phòng làm ở phase 6 (placeholder).
- API combobox tìm khách (cho S2, S15): tên, SĐT, số pet, danh sách pet; giới hạn 10; index phù hợp.
- Soft delete; xóa khách có lịch tương lai thì chặn.
- Test: CRUD, phân quyền theo design mục 8, cô lập tenant, không N+1 (đếm query).
```

### Task 5.2: Dịch vụ, combo, giá & thời lượng theo pet

```prompt
Đọc docs/database/db-schema.md mục 2.4 và docs/design/design.md S9. Làm:
- CRUD services (`code` unique theo shop, `service_group` medical/grooming, is_public, duration/buffer/price mặc định), service_price_rules, branch_services (không có dòng = có ở mọi cơ sở).
- **Combo chỉ cho spa**: `is_combo` + service_combo_items; combo và mọi dịch vụ con phải là grooming (kiểm ở Action), không lồng combo, con cùng shop qua FK ghép; combo có giá riêng, thời lượng của combo (gợi ý = tổng con). Khám + spa là dịch vụ riêng, không gộp combo.
- `ServiceQuoteResolver::resolve(Service, Pet|PetProfile): Quote{durationMin, bufferMin, price, appliedRule, explanation}`. Thứ tự: giống → loài + cỡ → loài → mặc định. Một query, ORDER BY độ cụ thể. `PetProfile` là DTO (species, breed, size) cho Site khi khách thêm pet mới.
- explanation dạng "90 phút · áp dụng cho Chó cỡ L". Giá hiển thị luôn ghi "tham khảo".
- Ô "Thử tính" ở chi tiết dịch vụ gọi resolver.
- Unit test đủ các nhánh resolve; dịch vụ không có rule; combo chứa dịch vụ medical → từ chối; combo lồng combo → từ chối.
```

### Task 5.3: Lịch trực, ngày nghỉ, ngày đóng cửa, tỉ lệ đặt online

```prompt
Đọc docs/database/db-schema.md mục 2.2, 2.5 (online_booking_rates), 5.4 và docs/design/design.md S8. Làm:
- CRUD staff_working_hours: nhiều khung/ngày, effective_from/to, mỗi khung thuộc một cơ sở và có `service_group` (medical/grooming/NULL). Không cho một người 2 khung chồng giờ, kể cả khác cơ sở (kiểm ở Action, `lockForUpdate` hàng staff). Một người có cả role vet và groomer chọn đúng một nhóm cho mỗi khung: người trực đếm một lần.
- staff_time_off (nhập giờ shop, lưu UTC); branch_closures (branch_id NULL = cả shop). Thêm nghỉ trùng lịch đã gán người: trả danh sách dòng lịch bị ảnh hưởng (không tự hủy).
- CRUD online_booking_rates theo cơ sở × nhóm (Cài đặt › Cơ sở): tỉ lệ mặc định + ghi đè theo thứ/khung giờ. Không có dòng = không nhận đặt online nhóm đó.
- `ShopClock` gom việc chuyển giờ shop ↔ UTC theo tenants.timezone: `localDayRangeUtc(date)`, `toUtc(date, time)`, `today()`. Không cộng offset cố định.
- UI S8: lưới tuần theo người, màu theo nhóm trực, hàng "Số người trực" mỗi khung theo nhóm, panel tỉ lệ đặt online.
- Test: DST (America/New_York) khung 09:00–17:00 ngày đổi giờ chuyển đúng; 2 khung chồng giờ khác cơ sở → từ chối; người 2 role chỉ được đếm một lần.
```

### Task 5.4: Tính chỗ trống theo hạn mức ⭐

```prompt
Đọc docs/database/db-schema.md mục 2.2, 2.5 và 5.1, 5.4. Viết `CapacityService` (`AvailabilityService`):
- Theo cơ sở + nhóm (medical/grooming), mỗi khung 15 phút trong ngày shop: số người trực = số staff có staff_working_hours khung đó với service_group của nhóm (GĐ3: ngày có shift_assignments thì dùng ca được xếp, để interface sẵn), trừ staff_time_off, branch_closures, ngoài giờ mở cửa. Ratio từ online_booking_rates (dòng có thứ/giờ cụ thể nhất thắng). Hạn mức online = floor(người trực × ratio).
- Đang chiếm: số `appointment_items` nhóm đó có status booked/checked_in của lịch chưa hủy, chồng khung. Site: chỉ đếm dòng của lịch `source=online` để so với hạn mức online. Lễ tân: trả thêm số người trực và tổng dòng (online + staff) mỗi khung để cảnh báo.
- API `slotsForOnline(branch, items: [{petProfile, service}], dateRange)`: khung bắt đầu (bước 15') mà MỌI nhóm của các dòng còn đủ chỗ trong suốt thời lượng (đã cộng buffer); nhiều pet cùng nhóm cần đủ số chỗ tương ứng; bỏ khung đã qua (now + lead time). `slotsForStaff(...)`: mọi khung trong giờ mở cửa, kèm số người trực / số dòng / cờ vượt.
- Hiệu năng: tải dữ liệu cả khoảng ngày trong số query cố định (không query theo từng khung hay từng người). Tính bằng interval arithmetic trong PHP (class Interval/IntervalSet, unit test).
- Endpoint JSON cho S2 và Site.
- Test: 3 bác sĩ × 1/3 → 1 chỗ; 1 bác sĩ nghỉ → 0 chỗ (floor 2/3); ghi đè tỉ lệ theo khung; lịch lễ tân đặt không trừ chỗ online; lịch 2 pet khám cần 2 chỗ; lịch khám + spa kiểm cả 2 nhóm; người 2 role đếm một lần; ca gãy; closure; dòng cancelled/no_show không chiếm; DST; số query không tăng theo số nhân viên.
```

**Xong khi:** test xanh; với DemoSeeder, khung chiều của người nghỉ chiều giảm đúng số chỗ.

### Task 5.5: Đặt lịch chống vượt hạn mức ⭐⭐ (điểm nhấn CV)

```prompt
Đọc kỹ docs/database/db-schema.md mục 2.5 và 5.1. Viết Action `BookAppointment` (dùng chung lễ tân và Site), đúng 8 bước ở mục 5.1:
- Input DTO: branchId, customerId, items[{petId, serviceId, assignedStaffId|null}], startsAt (UTC), source (online|staff), preferredStaffId|null, customerNote, idempotencyKey (UUID từ client), createdByStaffId|null.
- Trước transaction (autocommit): `INSERT IGNORE` hàng booking_capacity_locks(tenant, branch, group, ngày shop) cho mọi nhóm trong lịch. KHÔNG làm trong transaction (2 transaction cùng INSERT IGNORE trúng khóa trùng giữ khóa S rồi cùng nâng lên X → deadlock).
- Trong DB::transaction:
  (1) idempotency_key đã có → trả lịch cũ.
  (2) `lockForUpdate` hàng customers của khách (tuần tự hóa lịch cùng khách cho bước 6). Mọi pet thuộc khách (FK ghép cũng chặn ở DB), dịch vụ có ở cơ sở (branch_services), cơ sở mở cửa, giờ trong khung trực; resolve quote từng dòng, các dòng cùng bắt đầu lúc startsAt; ends_at lịch = muộn nhất.
  (3) `SELECT … FOR UPDATE` các hàng booking_capacity_locks đã tạo, thứ tự cố định medical → grooming.
  (4)(5) Online: với mỗi nhóm, (dòng online đang chiếm chồng giờ + dòng mới của nhóm) > hạn mức ở bất kỳ khung nào → `SlotTakenException` cho cả lịch (không lưu dòng nào). Staff: không chặn; tổng dòng chồng giờ > người trực → trả cờ cảnh báo; dòng gán người cụ thể mà người đó đã có dòng chồng giờ → cảnh báo.
  (6) Mỗi pet không có dòng lịch khác chồng giờ → lỗi.
  (7) Quota gói (khóa cuối): `UPDATE tenant_usage … WHERE appointments_count < ?` (INSERT … ON DUPLICATE KEY cho tháng mới); affected = 0 → QuotaExceededException. Đếm mọi lịch (online + lễ tân) theo tháng tạo (giờ shop), theo lịch không theo dòng; hủy không trả suất.
  (8) Tạo appointment `confirmed` + appointment_items (`booked`, snapshot duration/reference_price, service_group, branch_id/customer_id/starts_at chép), appointment_changes; dispatch `AppointmentBooked` afterCommit.
- Online không gán người (assigned_staff_id NULL). Deadlock/lock wait timeout: retry có giới hạn (DB::transaction attempts=3).
- Exception nghiệp vụ map HTTP 409 với payload 3 khung gần nhất còn đủ chỗ (CapacityService).
- **Test concurrency thật** (`->group('mysql')`, không RefreshDatabase transaction): 20 process (Symfony Process chạy artisan command, mỗi process một kết nối MySQL) cùng đặt online vào khung còn 1 chỗ → đúng 1 lịch, 19 SlotTaken; lịch 2 pet vào khung còn 1 chỗ → từ chối, không có dòng nào; quota còn 1 suất, 10 request → 1; cùng idempotency key 10 lần → 1 lịch; lịch khám+spa và lịch spa+khám song song không deadlock; ngày chưa có hàng khóa, 20 process cùng đặt → không deadlock; cùng khách, cùng pet, 2 lịch lễ tân chồng giờ song song (khác nhóm) → 1 lịch.
- `docs/adr/0002-booking-capacity-lock.md` (khóa một hàng đếm theo cơ sở × nhóm × ngày; so với khóa hàng staff, bảng slot UNIQUE, GET_LOCK, SERIALIZABLE; vì sao không gap lock) kèm kết quả test concurrency. Cập nhật docs/architecture.md mục Đặt lịch & hạn mức, sequence diagram mermaid.
```

**Xong khi:** test concurrency chạy trong CI, lặp 10 lần liền đều xanh; ADR giải thích rõ vì sao khóa hàng đếm không gây gap lock.

### Task 5.6: Vòng đời lịch hẹn + dời lịch

```prompt
Đọc docs/database/db-schema.md mục 2.5, 5.1 và docs/design/design.md S3, F3. Làm:
- State machine theo dòng: appointment_items booked → checked_in | cancelled | no_show. Trạng thái lịch suy ra: `checked_in` khi có ít nhất một dòng đã đến; `cancelled`/`no_show` khi mọi dòng như vậy. Không có `completed`.
- Action CheckIn (gọi `CreateVisit` của task 5.8 trong cùng transaction; các dòng cùng pet + cùng nhóm gom vào một Visit, ghi `appointment_items.visit_id`; dòng đã có visit_id thì bỏ qua; chọn từng dòng/pet đến, phân người cho dòng chưa phân, gợi ý preferred_staff_id), CancelAppointment / CancelItem (lý do, tùy chọn báo khách), MarkNoShow (lễ tân đánh dấu sớm). Command `appointments:mark-no-show` cuối ngày theo giờ từng shop: dòng còn `booked` của ngày đó → `no_show` (idempotent, không phạt). Mọi thay đổi ghi appointment_changes. Policy theo design mục 8.
- `RescheduleAppointment`: dùng cùng đường khóa/kiểm hạn mức với BookAppointment (tái sử dụng, không copy code); đổi sang ngày/nhóm khác thì khóa cả hàng cũ và mới theo thứ tự (ngày, nhóm) tăng dần; nhắc cũ skipped, tạo nhắc mới.
- Thêm/bớt dòng (pet, dịch vụ) trên lịch đã đặt: qua cùng đường kiểm hạn mức (item_added / item_removed).
- Hoàn tác hủy trong 5 giây (khôi phục = kiểm lại hạn mức, ghi appointment_changes `restored`).
- Test: chuyển trạng thái không hợp lệ bị chặn; dời vào khung hết chỗ online → 409; hai reschedule chéo ngày đồng thời không deadlock (`->group('mysql')`); một pet không đến thì dòng kia vẫn check-in được; mark-no-show chạy 2 lần không đổi gì thêm; lịch sử đổi giờ đủ.
```

### Task 5.7: UI lịch + form đặt lịch (S1, S2, S3)

```prompt
Đọc docs/design/design.md 6.1 (S1, S2, S3, S20), F1, mục 3, 10. Làm UI Vue 3 + Inertia:
- S1 Lịch: chế độ Ngày / Tuần / Theo nhân viên (mặc định), cột "Chưa phân" cho dòng chưa gán người, trục 07:00–21:00 bước 15', vùng ngoài giờ sọc xám, nghỉ, closure overlay, vạch "bây giờ", block theo màu nhân viên + nền nhóm dịch vụ + buffer sọc chéo, badge an toàn của pet, lịch nhiều pet hiện nhóm. Hàng đầu: số người trực / số dòng mỗi khung theo nhóm. Tự viết grid bằng CSS grid (nếu dùng thư viện thì giải thích).
- Dữ liệu lịch theo ngày: một endpoint trả appointment_items + lịch trực + nghỉ của khoảng UTC tương ứng ngày shop; eager load, không N+1.
- S2 drawer đặt lịch: combobox khách, tick nhiều pet, mỗi pet chọn dịch vụ (giá tham khảo / thời lượng đã resolve theo pet, có explanation), người làm ("Chưa phân" mặc định), date picker + lưới khung có số chỗ/người trực, cảnh báo vàng khi vượt nhưng vẫn lưu; thanh tóm tắt dính đáy. Sinh idempotency key (crypto.randomUUID) khi mở form, giữ khi retry. Nút submit chặn double click. Xử lý 409: toast, làm mới khung, gợi ý 3 khung, không mất dữ liệu form. Quota hết: banner + disable.
- S3 drawer chi tiết: dòng theo pet, action theo trạng thái (Check-in, Hủy dòng/lịch, Không đến, Dời), lịch sử đổi, khối bệnh án theo quyền (`medical.view_alerts` chỉ cảnh báo), trạng thái nhắc (Zalo/SMS). Hủy có modal + toast Hoàn tác.
- Kéo-thả dời lịch (commit riêng, có thể làm sau cùng).
- Phím tắt `N` mở đặt lịch, `/` focus tìm kiếm. Accessibility theo design mục 10.9.
- Test: Pest feature cho endpoint; 1–2 test trình duyệt (Pest browser plugin hoặc Dusk) cho F1.
```

**Xong khi:** F1 (2 pet, 2 dịch vụ) làm được dưới 60 giây với dữ liệu demo; 2 tab cùng đặt online vào khung còn 1 chỗ → tab sau nhận thông báo thân thiện.

### Task 5.8: Tiếp nhận + hàng đợi + cấp cứu ⭐

```prompt
Đọc CLAUDE.md, docs/database/db-schema.md mục 2.6 (visits, quy tắc Visit), 2.7 (mở hóa đơn khi tiếp nhận), 2.10 và docs/design/design.md 6.3 (S15, S16), F3, F7, F8. Làm:
- Action `CreateVisit` (check-in lịch hẹn và khách vãng lai): customer + pet + service_group + lý do đến/tình trạng ban đầu (`reason`) + cân nặng. Cấp queue_no nguyên tử qua `branch_daily_counters` name=queue (`INSERT … ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)`), ngày theo giờ shop. Check-in: các appointment_items cùng pet + cùng nhóm → một Visit (`appointment_items.visit_id`); dòng đã có visit_id thì bỏ qua (check-in 2 lần idempotent). Một pet vừa khám vừa spa = 2 Visit; khám + tiêm = 1 Visit.
- `priority_at`: khách có hẹn (giờ hẹn = starts_at sớm nhất của các dòng gom vào Visit) đến trong ±`tenant_settings.walk_in_priority_minutes` quanh giờ hẹn → giờ hẹn; muộn hơn hoặc vãng lai → giờ check-in.
- Mở hóa đơn: `CreateVisit` gắn Visit vào hóa đơn `draft` của khách ở cơ sở hôm nay, chưa có thì tạo (`OpenDraftInvoice`) trong cùng transaction; tìm nháp bằng open_draft_key `FOR UPDATE`, chưa có thì INSERT, trùng UNIQUE thì đọc lại và khóa (2 lễ tân cùng lúc không mở 2 hóa đơn). Khóa hóa đơn trước rồi mới tới Visit. Thú hoang (khách is_system): không tự mở hóa đơn.
- S15: tìm theo SĐT (Phone), card "Có lịch 14:00" → check-in nhiều pet; tạo nhanh khách + pet; nút "Thú hoang / vô chủ"; nút "Cấp cứu".
- **Cấp cứu**: `CreateEmergencyVisit` chỉ cần loài + `emergency_label` (customer_id, pet_id NULL), `is_emergency`, lên đầu hàng đợi ngay; lễ tân gắn khách/pet sau (`AttachVisitProfile`, lúc đó mở/gắn hóa đơn). Không hoàn tất Visit hay hóa đơn khi chưa gắn khách và pet (Action).
- S16 hàng đợi theo cơ sở hiện tại, tab Khám/Spa, sắp `is_emergency DESC, priority_at`, thời gian chờ, queue_no trên phiếu giữ nguyên. "Nhận khách": `UPDATE visits SET status='in_progress', assigned_staff_id=?, started_at=? WHERE id=? AND status='waiting'`; affected = 0 → 409 "đã có người nhận". Cập nhật bằng polling 10s (hoặc Reverb, ghi lý do).
- Test: 20 Visit tạo song song cùng cơ sở cùng ngày → queue_no 1..20 không trùng không lỗ (`->group('mysql')`); 2 bác sĩ nhận cùng khách → 1 thành công; qua 0h giờ shop thì số reset; khách có hẹn đến sớm 10' được xếp theo giờ hẹn, đến muộn 30' xếp như vãng lai; cấp cứu lên đầu, số khác giữ nguyên; 2 lễ tân tiếp nhận 2 pet của cùng khách song song → 1 hóa đơn; check-in lịch có pet khám + tiêm → 1 Visit, khám + spa → 2 Visit; thú hoang không có hóa đơn; cô lập tenant + cơ sở.
```

**Xong khi:** F3, F7 tới bước "Nhận khách" chạy được với dữ liệu demo; test concurrency queue_no xanh 10 lần liền.

### Task 5.9: Visit (khám / spa)

```prompt
Đọc docs/database/db-schema.md mục 2.6 (visit_items, attachments, quy tắc Visit), 2.7 (đồng bộ dòng) và docs/design/design.md 6.3 (S17), F4, F5. Làm:
- S17: bác sĩ ghi bệnh án (task 6.2 làm phần medical_entries; ở đây khung + nút), thêm dịch vụ phát sinh / xét nghiệm vào visit_items khi khách đồng ý (unit_price = giá hiện tại từ ServiceQuoteResolver lúc thêm); groomer ghi dịch vụ + ghi chú spa, ảnh lúc nhận/trả (attachments kind intake_photo/return_photo, tùy chọn). MVP chỉ item_type=service; để sẵn interface cho medication/consumable (phase 11).
- Đồng bộ một chiều visit_items (`is_billable = 1`) → invoice_lines `line_type=visit_item` khi hóa đơn còn draft (khóa theo visit_item_id, `lockForUpdate` hóa đơn; hóa đơn không còn draft → từ chối sửa visit_items). Combo = một dòng. Dòng mang pet_id.
- Action `CompleteVisit`: phải có customer_id + pet_id; status done, finished_at; thông báo in-app (notifiable = staff) cho lễ tân/thu ngân cơ sở "Số 042 chờ thanh toán"; spa → tạo reminder `pickup_ready` gắn Visit (remindable_type `visit`, task 6.1). Visit bị hủy khi hóa đơn còn nháp → gỡ dòng visit_item khỏi hóa đơn. Nhận/hoàn tất Visit khám chỉ role vet (chủ shop không có vet → 403). Lịch hẹn giữ `checked_in`. GĐ2 trừ kho thuốc + vật tư ở đây (task 11.2).
- Groomer thấy dấu hiệu bệnh → nút "Báo lễ tân" tạo thông báo; lễ tân tạo thêm Visit khám cùng hóa đơn.
- File private, URL ký có hạn, đường dẫn chứa tenant_id; test không tải được file shop khác.
- Test: ma trận quyền (lễ tân xem, không sửa dòng Visit; bác sĩ chỉ Visit khám, spa chỉ Visit spa), giá dòng không đổi khi sửa giá dịch vụ, thêm/xóa dịch vụ thì dòng hóa đơn nháp đổi theo, 2 bác sĩ ghi dịch vụ cho 2 pet cùng hóa đơn song song không mất dòng (`->group('mysql')`), hoàn tất Visit cấp cứu chưa gắn hồ sơ → từ chối, cô lập tenant.
```

### Task 5.10: Hóa đơn + thanh toán ⭐

```prompt
Đọc kỹ docs/database/db-schema.md mục 2.7, 5.6 và docs/design/design.md 6.4 (S18, S19), S32, F4, F8, F9, F10, F11. Làm:
- Trạng thái **draft → completed** (+ void). Một hóa đơn gom nhiều Visit (`visits.invoice_id`), dòng mang pet_id. `OpenDraftInvoice` (đã gọi từ 5.8); `CreatePayerInvoice` cho thú hoang (kind=service, customer_id = hồ sơ hệ thống của cơ sở, payer_name, payer_phone, gắn Visit thú hoang); `CreateRetailInvoice` (kind=retail, SĐT tùy chọn: khớp khách có sẵn thì gắn customer_id, không thì chỉ payer_phone, không tạo hồ sơ; từ chối dòng dịch vụ và sản phẩm requires_prescription; sản phẩm thật có ở phase 11, MVP làm Action + test với product giả lập hoặc TODO rõ ràng).
- Sửa nháp: dòng visit_item chỉ đọc ở quầy (sửa ở Visit); thêm/bớt dòng product, **dòng gõ tay** (custom: tên + giá, không trừ kho). **Không giảm giá tay** (discount_amount = 0). Thuế theo tenant_settings.default_tax_rate. Tiền DECIMAL/brick-money, không float.
- `VoidDraftInvoice` (`invoices.void_draft`): lý do bắt buộc, gỡ visits.invoice_id, thông báo chủ shop, activity_logs; báo cáo ngày có mục "Hóa đơn đã hủy". Model guard ném exception khi sửa hóa đơn status ≠ draft.
- Cảnh báo dọn nháp: job đầu ngày (giờ shop) đếm `status='draft' AND business_date < hôm nay` theo cơ sở → notifications cho lễ tân, thu ngân, chủ shop; S18 nhóm "Từ ngày trước"; card ở S5. Không tự hủy.
- In tạm tính: HTML/PDF có watermark "TẠM TÍNH", không số hóa đơn; dùng chung mẫu in với hóa đơn hoàn thành.
- `CompleteInvoice` (một transaction): lockForUpdate hóa đơn (phải draft) → mọi Visit gắn phải done/cancelled và đã có khách + pet → tiền mặt cần ca thu ngân đang mở (task 5.11) → ghi payments (nhiều phương thức, tổng = total, idempotency_key; tiền thối cho cash; cash gắn cash_session_id) → cấp code `{branch}-{yyyymmdd}-{seq}` từ branch_daily_counters name=invoice → completed → (GĐ2 trừ kho dòng product) → activity_logs. Không cọc, không trả một phần, không công nợ.
- `AmendInvoice` (chỉ `invoices.amend`, lý do bắt buộc, một transaction): bản cũ void (giữ số) → bản thay thế số mới `replaces_invoice_id` chép dòng → chuyển visits.invoice_id sang bản mới → ghi cặp payments `transfer_out` (bản cũ) / `transfer_in` (bản mới) theo từng phương thức, KHÔNG sửa dòng payments cũ (append-only) → sửa dòng → chênh lệch thu thêm (payment) hoặc trả lại (refund, tiền mặt cần ca mở) → completed. Hóa đơn đã có phiếu trả hàng → từ chối. Báo cáo: bản cũ giữ ở ngày gốc, ngày sửa ghi −bản cũ + bản mới.
- `activity_logs` (append-only, model guard) + màn S32 Nhật ký (chủ shop, lọc theo người/loại/ngày).
- UI S18 thu ngân (nháp đang mở theo cơ sở, nhóm "Từ ngày trước", dòng nhóm theo pet, Hoàn thành disabled khi còn Visit chưa xong) + S19 bán lẻ (UI đầy đủ ở phase 11).
- Test: sửa hóa đơn completed bị chặn; hoàn thành khi còn Visit chưa done bị chặn; cùng idempotency key 2 lần → 1 lần; 2 người hoàn thành cùng hóa đơn song song → 1 (`->group('mysql')`); khách 2 pet → 1 hóa đơn, dòng đúng pet; nháp không chiếm số hóa đơn; hóa đơn service thiếu khách bị từ chối ở Action và DB; thú hoang có người trả → hóa đơn có payer_name; retail không cần khách, chặn thuốc kê đơn; chủ sửa hóa đơn → payments cũ không đổi, bản thay thế có transfer_in = đã thu + chênh lệch đúng, Thực thu và tiền mặt ca không bị tính đôi; lễ tân gọi amend → 403; hủy nháp không lý do → 422; thu tiền mặt khi chưa mở ca → từ chối; job dọn nháp đếm đúng và không gửi trùng trong ngày; mỗi thao tác đúng 1 dòng activity_logs.
- `docs/adr/0005-visit-invoice-separation.md` (lịch hẹn tách Visit, Visit tách hóa đơn; hóa đơn bất biến, sửa = hủy + bản thay thế).
```

**Xong khi:** F4, F8, F10, F11 chạy hết tới hóa đơn Hoàn thành; F9 (bán lẻ không hồ sơ) pass ở tầng Action.

### Task 5.11: Ca thu ngân theo két

```prompt
Đọc docs/database/db-schema.md mục 2.7 (cash_drawers, cash_sessions, payments) và docs/design/design.md S35, S18, F12. Làm:
- CRUD két theo cơ sở (Cài đặt › Cơ sở › Két). `OpenCashSession` (tiền đầu ca; mỗi két một ca mở, dựa UNIQUE open_key + bắt lỗi duplicate), `CloseCashSession` (expected_cash = đầu ca + Σ cash payment − Σ cash refund của ca; bút toán transfer_in/out không tính; nhập counted_cash; chênh lệch ≠ 0 bắt buộc lý do, activity_logs + thông báo chủ shop). Ca đóng không nhận thêm khoản. Nhiều người thu chung ca, mỗi khoản ghi received_by_staff_id.
- Mọi payment/refund cash bắt buộc cash_session_id của ca đang mở ở cơ sở hiện tại; chuyển khoản/QR/thẻ không cần nhưng vẫn vào báo cáo ca theo thời gian.
- Báo cáo ca: theo phương thức, theo người thu, hoàn tiền; in được. Chủ shop thấy mọi ca, quản lý chi nhánh xem ca cơ sở mình. Chip ca đang mở ở topbar.
- Test: 2 người mở ca cùng két song song → 1 (`->group('mysql')`); thu tiền mặt khi chưa mở ca → từ chối; expected_cash đúng với nhiều người thu + hoàn tiền; đóng ca 2 lần → 1 lần; cô lập tenant + cơ sở.
```

**Xong khi:** mở ca → thu 2 hóa đơn (tiền mặt + QR) → đóng ca lệch 10.000 ₫ có lý do → báo cáo ca đúng số, chủ shop nhận thông báo.

---

## Phase 6: Nhắc hẹn, bệnh án, tiêm phòng ⭐

### Task 6.1: Hạ tầng queue + nhắc khách qua Zalo/SMS

```prompt
Đọc docs/database/db-schema.md mục 2.8 (reminders, message_deliveries, notifications) và 5.2 (job TenantAware). Làm:
- Laravel Horizon (queue: high cho tin xác nhận/OTP, default, low cho nhắc hàng loạt), service horizon trong docker compose, dashboard chỉ admin nền tảng.
- Tách "cần nhắc gì" (`reminders`) khỏi "đã gửi qua kênh nào" (`message_deliveries`). Khi AppointmentBooked: tạo reminders `appointment_confirm` (ngay), `appointment_24h`, `appointment_2h` với dedupe_key `appt:{id}:{purpose}`, insertOrIgnore; `appointments.send_reminders = 0` thì bỏ 24h/2h; nhắc có send_at đã qua thì không tạo. Một tin cho cả lịch nhiều pet. Lịch hủy/dời: reminders pending → skipped, tạo mới cho giờ mới.
- Gửi: Zalo ZNS trước; lỗi hoặc SĐT không dùng Zalo → SMS; không gửi cả hai khi Zalo thành công. Mỗi lần gửi thật một dòng message_deliveries (channel, template_code, status, provider_message_id, error, cost). Không email/Telegram cho khách.
- `ZnsGateway`, `SmsGateway` (interface, driver log ở local/demo, driver thật cấu hình theo shop ở Cài đặt › Kênh nhắn tin); `SmsGateway` dùng chung cho OTP (task 7.1).
- Scheduler mỗi phút: chọn reminders pending đến hạn (IDX(status, send_at)), claim nguyên tử (`SELECT … FOR UPDATE SKIP LOCKED` hoặc UPDATE có điều kiện sang processing), dispatch job gửi.
- Job gửi: TenantAware, idempotent (đã sent thì thoát), retry backoff [60, 300, 900], `failed()` ghi failed + error. Nội dung tiếng Việt, giờ theo múi giờ shop, link S14. Rate limit nhà cung cấp → release theo retry_after. Bỏ qua shop suspended.
- Nhắc nội bộ: trước 1 ngày tạo notifications (notifiable = staff, không phải users) cho lễ tân cơ sở → trang S20 "Cần gọi hôm nay", chuông S41 topbar. Cấu hình Zalo/SMS của shop lưu `tenant_settings.zalo_config`/`sms_config` (encrypted, không trả ra frontend).
- Thử lại thủ công từ S3 ("SMS thất bại · Thử lại").
- Test: chạy scheduler 2 lần không gửi 2 lần; lịch "Không gửi nhắc" không có nhắc 24h/2h; user làm ở 2 shop chỉ thấy thông báo của shop đang mở; 2 worker claim cùng lúc không trùng; retry; hủy lịch thì không gửi; Zalo lỗi → đúng 1 SMS dự phòng; Zalo thành công → không SMS; lịch 2 pet → 1 tin; Http::fake cho ZNS/SMS.
- `docs/adr/0007-reminders-message-deliveries.md`; cập nhật docs/architecture.md mục Queue & nhắc hẹn.
```

### Task 6.2: Bệnh án

```prompt
Đọc docs/database/db-schema.md mục 2.6 (medical_records, medical_entries, medical_entry_addenda, attachments), 2.10 (tenant_counters), 3.2, 5.3 và docs/design/design.md S4 tab Bệnh án, S17, F4. Làm:
- Mỗi pet **một hồ sơ** `medical_records`, số hồ sơ `HS-000123` cấp ở lần khám đầu qua tenant_counters (nguyên tử, dùng chung mọi cơ sở). Mỗi lần khám **một dòng** `medical_entries` gắn visit (UNIQUE visit_id): triệu chứng, chỉ số sinh tồn, chẩn đoán, điều trị, hướng dẫn chăm sóc (khách thấy), ghi chú nội bộ, tái khám (`follow_up_on` → reminder follow_up), chuyển tuyến. Bác sĩ nhập bệnh sử khám nơi khác: dòng visit_id NULL.
- Chỉ role vet tạo; **chỉ người lập sửa dòng của mình trong 24 giờ** (Policy + editable_until); sau đó chỉ thêm medical_entry_addenda. Mọi lần sửa ghi activity_logs trước/sau.
- Quyền xem: `medical.view_full` (vet mọi cơ sở, chủ shop) nhận MedicalEntryResource đầy đủ; `medical.view_alerts` (lễ tân, spa, quản lý chi nhánh) chỉ cảnh báo + ngày khám (Resource riêng, không chỉ ẩn UI); thu ngân, kho không gọi được. Site (task 7.1) nhận ngày khám, chẩn đoán, hướng dẫn, thuốc đã kê, tái khám, **không bao giờ** internal_notes.
- Đính kèm kết quả xét nghiệm / chẩn đoán hình ảnh (ảnh, PDF) vào dòng bệnh án qua attachments (private disk, URL ký có hạn, giới hạn loại + dung lượng); ô `is_shared_with_customer` (mặc định tắt) quyết định Site có thấy file không.
- Timeline bệnh án ở hồ sơ pet, mọi cơ sở.
- Test: ma trận quyền (JSON của lễ tân không có khóa diagnosis/treatment/internal_notes), người khác sửa dòng → 403, quá 24h → 403, addendum được, số hồ sơ cấp song song không trùng (`->group('mysql')`), file shop khác → 404, cô lập tenant.
```

### Task 6.3: Phác đồ tiêm phòng / tẩy giun ⭐

```prompt
Đọc docs/database/db-schema.md mục 2.6 (vaccines, care_protocols, care_protocol_steps, pet_vaccinations) và docs/design/design.md S4 tab Tiêm phòng, S7, F6, F13. Làm:
- CRUD vaccines, care_protocols, care_protocol_steps (chủ shop/vet).
- `CareScheduleCalculator` (thuần, unit test kỹ): từ phác đồ + sổ tiêm của pet + ngày sinh → mũi kế tiếp và next_due_on (min_age_days, offset_days, recurring). Ngày theo DATE giờ shop.
- Action `RecordVaccination` (vet, trong Visit): ghi mũi (vaccine, số lô, hạn lô), visit_id, tính next_due_on, đánh dấu dòng cũ cùng vaccine superseded_at, trong transaction. UI hiển thị trước "Mũi kế tiếp dự kiến: dd/MM/yyyy". `PostponeVaccination`: dòng `postponed` + lý do + ngày hẹn mới ở next_due_on. "Đã tiêm ở nơi khác": dòng không visit_id.
- Scheduler hằng ngày theo giờ sáng của TỪNG shop (vd 08:00 giờ shop; Tenant::forEach, dùng IDX(tenant_id, superseded_at, next_due_on)): next_due_on trong 7 ngày tới hoặc quá hạn, superseded_at null → reminders `vaccine_due` dedupe_key `vax:{pet_vaccination_id}:{offset}`. chunkById, không load hết vào bộ nhớ.
- S7: tab Quá hạn / 7 ngày / 30 ngày, lọc, trạng thái nhắc, action Đặt lịch tiêm (mở S2 điền sẵn), Gửi nhắc lại.
- Tab Tiêm phòng ở hồ sơ pet với tiến độ phác đồ (3/4 mũi).
- Test: dữ liệu design mục 9 (Sushi mũi 2/4, Tôm quá hạn); hoãn tiêm thì nhắc theo ngày mới; chạy scheduler 2 lần không nhắc trùng; múi giờ.
```

---

## Phase 7: Site của shop

### Task 7.1: Site, đặt lịch online, tài khoản khách (S6, S14, S21, S33)

```prompt
Đọc docs/design/design.md mục 4, 6.2 (S6, S14, S21, S33), F2, mục 10 và docs/database/db-schema.md mục 2.3 (customer_otps), 2.5, 5.2. Làm Site của shop tại `/{slug}`, mobile-first, layout riêng. Route `/{slug}` đăng ký SAU mọi route trung tâm; slug cấm đã có từ task 4.3.
- Trang chủ shop (giới thiệu, cơ sở, giờ mở cửa, gọi, chỉ đường), Dịch vụ & giá (dịch vụ is_public, giá "tham khảo") công khai.
- Đăng nhập khách S21: SĐT + OTP SMS (customer_otps lưu hash, hết hạn 5 phút, sai 5 lần khóa 15 phút; gửi lại sau 60s, tối đa 5 lần/giờ mỗi SĐT, 20 lần/giờ mỗi IP, key có tiền tố shop); guard riêng `customer`, không dùng bảng users; phiên `tenant_settings.customer_session_days`. Đăng nhập xong về `/{slug}/tai-khoan` hoặc trang đang xem dở (intended chỉ nhận đường dẫn thuộc Site đúng shop). Khách KHÔNG bao giờ vào được `/app` (test: khách mở /app/{slug} → về /login, không thấy dữ liệu); đăng nhập Site shop A thì ở Site shop B vẫn là khách lạ.
- **Đặt lịch S6, 5 bước**: (1) chọn cơ sở (ẩn nếu chỉ có 1) + dịch vụ công khai; (2) xác minh: đã đăng nhập thì bỏ qua, chưa thì SĐT + OTP, SĐT mới thì nhập tên → tạo customers (khách tự đăng ký; 2 request cùng SĐT đồng thời không tạo trùng: UNIQUE + bắt lỗi duplicate rồi đọc lại); (3) chọn một hoặc nhiều pet đã có hoặc thêm mới (loài, giống, cân nặng → quote qua ServiceQuoteResolver), mỗi pet một dịch vụ; (4) dải 14 ngày + khung còn đủ chỗ cho mọi pet (CapacityService.slotsForOnline), không chọn nhân viên, ô "người mong muốn"; (5) xác nhận → BookAppointment source=online → lịch `confirmed` ngay. Không gợi ý pet trước khi xác minh OTP.
- Chống lạm dụng: rate limit theo IP + SĐT + shop, honeypot hoặc Turnstile, giới hạn số lịch tương lai mỗi SĐT.
- Trang thành công: mã lịch (public_id), file .ics, link quản lý; tin xác nhận Zalo (lỗi thì SMS) qua task 6.1.
- S14 quản lý lịch qua signed URL theo public_id (không cần đăng nhập): xem, hủy cả lịch hoặc một pet, dời (về bước 4). Chặn ở server theo `tenant_settings.booking_cancel_cutoff_hours` / `booking_reschedule_cutoff_hours` (mặc định 24 giờ).
- Tài khoản "Của tôi": pet, lịch sắp tới, lịch sử khám/spa mọi cơ sở (ngày khám, chẩn đoán, hướng dẫn chăm sóc, thuốc đã kê, tái khám, file bác sĩ chia sẻ; không ghi chú nội bộ), sổ tiêm, hóa đơn đã hoàn thành của mình.
- Điều khoản sử dụng S33 `/{slug}/dieu-khoan`: nội dung từ `tenant_settings.terms_content`, chủ shop soạn ở Cài đặt › Site (mẫu gợi ý, sanitize), link ở footer và bước xác nhận. Không có bước đồng ý tại quầy.
- Mobile-first theo design 3.4, 6.2 (bottom nav, OTP autofill, sticky CTA, PWA manifest). Giờ theo múi giờ shop, ghi chú nếu thiết bị khác.
- Test: không lộ dữ liệu (dò public_id, gợi ý pet theo SĐT), rate limit OTP, URL ký sai/hết hạn, quá hạn hủy/dời → từ chối, shop suspended thì Site không nhận lịch; JSON lịch sử khám không có khóa internal_notes; test trình duyệt viewport 390×844 cho OTP → đặt lịch 2 pet → xem lịch sử.
```

**Xong khi:** F2 chạy hết trên điện thoại: đặt 2 bé, nhận tin xác nhận (driver log), mở link quản lý dời được giờ.

---

## Phase 8: Thu phí gói tự động (sau MVP)

> Ở MVP gói và hạn do admin gán tay (task 4.5). Khi làm phase này: webhook ghi vào `tenant_subscriptions` và cập nhật `tenants.plan_expires_at`, không tạo luồng hạn dùng thứ hai. Thu phí gói online nằm trong "Sau V1" (db-schema 2.12), chỉ làm khi có nhu cầu.

### Task 8.1: Thanh toán gói online

```prompt
Đọc docs/database/db-schema.md mục 2.1 (plans, tenant_subscriptions, tenant_usage). Làm thanh toán gói online (cổng thanh toán VN hoặc Stripe; chọn và giải thích ngắn):
- Trang Gói & hạn dùng trong CMS: gói hiện tại, usage, nâng cấp/gia hạn qua cổng thanh toán.
- Webhook idempotent: lưu event (UNIQUE event_id) TRƯỚC khi xử lý, xử lý trong job; event trùng bỏ qua. Thanh toán thành công → gọi `ExtendSubscription` (task 4.5).
- Gom mọi kiểm tra giới hạn gói vào class `PlanLimits` (max_branches, max_staff, max_appointments_per_month, features) dùng ở 4.3, 4.4, 5.5.
- Banner quota ≥ 80% (vàng), 100% (đỏ).
- Test: webhook trùng không xử lý 2 lần, chữ ký sai bị từ chối, hạ gói khi đang vượt số nhân viên (không khóa người cũ, chặn mời thêm); không gọi cổng thật trong CI.
```

> **Tự đăng ký (tùy chọn, sau V1):** trang đăng ký gọi lại `ProvisionTenant` (trial + Starter), bật bằng cờ `TENANCY_SELF_SIGNUP`. Không tạo luồng tạo tenant thứ hai.

---

## Phase 9: Dashboard, báo cáo, hiệu năng, observability

### Task 9.1: Tổng quan (S5) + báo cáo doanh thu (S30)

```prompt
Đọc docs/design/design.md 6.7 (S5, S30), mục 8 (dòng Báo cáo) và docs/database/db-schema.md mục 2.7 (định nghĩa doanh thu). Làm:
- **Doanh thu** = tổng hóa đơn completed trước thuế theo ngày hoàn thành; hóa đơn bị sửa giữ ở ngày gốc, ngày sửa ghi −bản cũ (void có completed_at, theo voided_at) + bản thay thế; trả hàng trừ ngày trả (phase 11). **Thực thu** = Σ payment − Σ refund theo ngày (bỏ transfer_in/out), ghi nhãn rõ là dòng tiền.
- Phạm vi ép ở server theo quyền: chủ shop mọi cơ sở hoặc "Tất cả"; quản lý chi nhánh cơ sở mình, không lãi gộp; lễ tân (`reports.view_today`) chỉ HÔM NAY cơ sở mình, kể cả trên S5 (query tự clamp khoảng ngày, test sửa query string vẫn chỉ ra hôm nay); bác sĩ, spa không thấy doanh thu; thu ngân chỉ báo cáo ca.
- S5: KPI, lịch hôm nay theo nhóm, hàng đợi, tiêm đến hạn (5 dòng), hóa đơn chưa hoàn thành từ ngày trước, nhắc thất bại, biểu đồ 14 ngày, usage gói (chủ shop). Bản rút gọn cho nhân viên.
- S30: tuần/tháng/khoảng tùy chọn, tách dịch vụ / bán lẻ, combo tính một dịch vụ, theo cơ sở; mục "Hóa đơn đã hủy". Giá vốn và lãi gộp thêm ở task 11.2.
- Mỗi widget một Query class; số query cố định (assert trong test); cache ngắn theo key tenant nếu nặng; mọi khoảng ngày theo ngày shop.
```

### Task 9.2: Hiệu năng & observability

```prompt
Rà soát hiệu năng và thêm observability:
- EXPLAIN các query nóng (lịch theo ngày, đếm hạn mức, kiểm chồng giờ pet, hàng đợi, reminders đến hạn, tiêm đến hạn, tìm khách, hóa đơn nháp cũ) với dữ liệu lớn: command `demo:seed-large` tạo 50 shop × 20k lịch. Ghi kết quả + index thêm/đổi vào docs/performance.md.
- Telescope (local), Sentry (production, tag tenant_id, không gửi PII như SĐT).
- Log có context tenant_id, request id.
- Health check (DB, Redis, queue lag từ Horizon).
- Load test đặt lịch bằng k6 (`tools/k6`): throughput và p95 của POST đặt lịch online, ghi vào docs/performance.md.
```

---

## Phase 10: Deploy & trình bày cho CV

### Task 10.1: Deploy demo

```prompt
Chuẩn bị deploy production cho một VPS nhỏ (hoặc Laravel Cloud/Forge; chọn phương án rẻ và đơn giản nhất, giải thích ngắn):
- Dockerfile production multi-stage (composer --no-dev, npm build, opcache), docker-compose.prod (app php-fpm + nginx hoặc FrankenPHP, horizon, scheduler, mysql, redis), hoặc cấu hình tương đương.
- GitHub Actions deploy khi push tag sau khi CI xanh; `migrate --force`; zero-downtime nếu nền tảng hỗ trợ.
- Backup MySQL hằng ngày.
- Bootstrap môi trường mới: sau `migrate --force` chạy `php artisan platform:admin-create` (một lần); production không chạy DemoSeeder. Ghi vào docs/deploy.md.
- Môi trường demo: reset DemoSeeder mỗi đêm, tài khoản demo cho từng role (hiện trên trang login demo), Zalo/SMS/mail dùng driver log.
```

### Task 10.2: README & ADR hoàn chỉnh

```prompt
Viết lại README cho nhà tuyển dụng đọc trong 2 phút: demo link + tài khoản demo theo role, ảnh chụp (lịch, đặt lịch nhiều pet, hàng đợi, hồ sơ pet, Site mobile), sơ đồ kiến trúc (mermaid), mục "Điểm kỹ thuật đáng chú ý" (multi-tenant fail closed + FK ghép; chống vượt hạn mức đặt online bằng khóa hàng đếm + test concurrency; hàng đợi cấp số nguyên tử; hóa đơn bất biến + bản thay thế; timezone/DST; nhắc Zalo→SMS idempotent), mỗi mục 2–3 dòng + link code/ADR, số liệu (coverage, PHPStan level, p95 đặt lịch). Rà docs/adr/ đủ 8 ADR theo docs/database/db-schema.md mục 6 (0001 single DB, 0002 capacity lock, 0003 UTC + TIME local, 0004 branch không global scope, 0005 Visit/hóa đơn, 0006 sổ kho FIFO (GĐ2), 0007 reminders/message_deliveries, 0008 platform admin).
```

**Xong khi:** người lạ mở README hiểu dự án làm gì, vào được demo, thấy ngay 3–4 điểm kỹ thuật khó.

---

## Phase 11 (GĐ2): Kho, thuốc, bán hàng, chuyển kho, trả hàng

### Task 11.1: Sản phẩm, nhà cung cấp, phiếu nhập 2 bước, hao hụt, kiểm kê

```prompt
Đọc CLAUDE.md, docs/database/db-schema.md mục 2.9, 4.2, 5.5 và docs/design/design.md 6.8 (S22, S24), F15. Làm:
- Migration + model cho mọi bảng mục 2.9 trừ stock_transfers/reservations (11.3) và sales_returns (11.5); thêm FK còn thiếu từ bảng MVP (visit_items.product_id, invoice_lines.product_id, vaccines.product_id). FK ghép như mọi bảng tenant.
- Giá bán `products.sale_price` là một giá hiện hành, không theo lô/giá vốn; đổi giá ghi activity_logs `price.changed`. `product_units` (thùng = 24 gói): quy đổi ở Action, kho chỉ lưu đơn vị nhỏ nhất. `is_batch_tracked`; hàng không theo lô có một lô ngầm. HSD chỉ tháng/năm → ngày cuối tháng.
- Service duy nhất `StockLedger` cho mọi thay đổi tồn: lockForUpdate stock_items (product_id tăng dần), chọn lô **FIFO theo lô nhập** (`ORDER BY received_at, id`, bỏ lô quá hạn), trừ lô + on_hand, ghi movement append-only (mỗi lô một dòng, `unit_cost` = giá lô, NULL nếu lô chưa có giá). Không chỗ nào khác được UPDATE on_hand hay stock_batches.quantity (arch test).
- **Phiếu nhập 2 bước**: `PostPurchaseReceipt` (role inventory, cơ sở nhận): sản phẩm, số lượng, lô, hạn; tạo lô (unit_cost NULL) + movement purchase + cộng tồn ngay, status `posted`; request/response của kho không có trường giá (test). `CostPurchaseReceipt` (`purchase.cost`, chủ shop): điền unit_cost từng dòng → cập nhật lô + movement chưa có giá + total_cost, status `costed`, activity_logs. Màn "Phiếu chờ điền giá" + badge. Đã posted không sửa số lượng (sai thì lập điều chỉnh).
- **Phiếu hao hụt / điều chỉnh** `stock_adjustments`: kho lập có lý do (chuột cắn, vỡ, hết hạn, mất…) → `pending_approval` → `inventory.approve` (chủ shop, quản lý chi nhánh) duyệt thì StockLedger ghi write_off/adjustment; từ chối thì không đổi tồn. Đổi trạng thái bằng UPDATE có điều kiện.
- Kiểm kê theo lô `stocktakes`: chênh lệch có lý do, duyệt mới ghi movement stocktake.
- Hàng lỗi: chủ shop chọn hủy (write_off), trả nhà cung cấp, hoặc chuyển mã riêng bán giảm (relabel_out mã gốc + relabel_in mã mới, giữ giá vốn lô, `replaces_product_id`).
- Giá vốn chỉ người có `inventory.view_cost` (mặc định chủ shop, giao được): `ProductResource` không có cost; `ProductCostResource` chỉ khi `inventory.view_cost`. Test dataset role × endpoint: JSON của lễ tân/bác sĩ/kho không chứa khóa nào có "cost".
- Cảnh báo kho `stock_alerts`: sắp hết (`stock_items.reorder_level` → `products.default_reorder_level`), hết hàng (kiểm sau mỗi lần xuất, job sau commit), cận date/quá hạn (ngưỡng sản phẩm → nhóm → tenant_settings.expiry_alert_days; quét 07:00 giờ shop). Một cảnh báo mở mỗi sự việc (open_key). Báo qua notifications cho chủ shop + người có `inventory.alerts` của cơ sở; card ở S5, tab Cảnh báo ở S22.
- S22 Kho (tồn, lô & HSD, sổ kho, lọc Cận date / Quá hạn), S24 phiếu nhập, hao hụt, kiểm kê, nhà cung cấp.
- Test: tổng lô = on_hand sau mọi thao tác; giá vốn xuất đúng theo lô FIFO (lô 180.000 rồi lô 190.000, bán 1 → 180.000; bán vượt lô 1 → 2 movement 2 giá); giá bán không đổi khi nhập lô giá mới; lô chưa có giá → movement unit_cost NULL, điền giá sau thì cập nhật; kho gọi CostPurchaseReceipt → 403; phiếu hao hụt chưa duyệt không đổi tồn, duyệt 2 lần song song → trừ 1 lần (`->group('mysql')`); nhập 2 thùng → 48 gói; xuống dưới ngưỡng → đúng 1 cảnh báo mở, chạy job 2 lần không trùng; nhập bù → resolved; cảnh báo cơ sở A không hiện ở cơ sở B; cô lập tenant.
- `docs/adr/0006-stock-ledger-fifo.md` (FIFO theo lô nhập, giá vốn = giá lô, phiếu nhập 2 bước; so với FEFO và bình quân gia quyền).
```

### Task 11.2: Trừ kho khi hoàn tất Visit / hoàn thành hóa đơn + lãi gộp ⭐

```prompt
Đọc docs/database/db-schema.md mục 2.6 (visit_items), 2.7, 2.9 (quy tắc kho), 5.5 và docs/design/design.md S17, S19, S30. Làm:
- visit_items thêm medication (bác sĩ kê) và consumable (vật tư). Lúc kê/thêm chỉ **kiểm còn đủ**, không giữ hàng. **Trừ kho khi `CompleteVisit`** qua StockLedger (movement visit_usage), thiếu thì từ chối hoàn tất và báo.
- Vật tư **theo thực tế**: thêm dịch vụ (hoặc combo → từng dịch vụ con) thì điền sẵn dòng consumable (`is_billable=false`, `suggested_quantity`) theo service_consumables đúng cỡ pet → NULL; nhân viên sửa số lượng, đổi vật tư, xóa. Dòng khớp dị ứng của pet hiện cảnh báo. Trừ kho theo số thực tế.
- Vaccine có `vaccines.product_id` → trừ kho khi hoàn tất Visit, tự điền số lô vào pet_vaccinations.
- Hóa đơn: mọi dòng product (hóa đơn retail và hàng mua thêm trên hóa đơn dịch vụ) trừ kho khi **completed** (movement sale), invoice_lines.unit_cost = giá vốn xuất. Bán theo thùng trừ quantity × unit_factor. POS hiện lô sẽ xuất; chọn lô tay cần `inventory.override_batch` + lý do.
- Hoàn thiện UI S19 bán lẻ nhanh: mã vạch, không bắt buộc SĐT, chỉ sản phẩm `is_sellable`, chặn thuốc kê đơn.
- Báo cáo S30 (chỉ `inventory.view_cost`): **Lãi gộp = Doanh thu − Giá vốn hàng bán − Giá vốn vật tư tiêu hao**; doanh thu trước thuế; giá vốn từ stock_movements (sale + visit_usage − customer_return); dòng thiếu giá nhập đánh dấu "thiếu giá nhập"; tách bán lẻ / dịch vụ; theo cơ sở hoặc cả shop.
- Test: lô L1 (nhập trước, 24 gói) + L2 (nhập sau, 24 gói, HSD gần hơn), bán 30 gói → L1 = 0, L2 = 18 (FIFO, không theo hạn); lô quá hạn không bao giờ bị chọn; bán 1 thùng khi còn 20 gói → từ chối; lượt spa có vật tư không tính tiền vẫn ra giá vốn; báo cáo tổng = tổng các cơ sở; còn 1 hộp, 10 request hoàn thành hóa đơn song song → đúng 1 thành công (`->group('mysql')`); 2 Visit kê nhiều thuốc theo thứ tự ngược nhau hoàn tất song song không deadlock.
```

### Task 11.3: Tra tồn cơ sở khác + chuyển kho + giữ hàng ⭐

```prompt
Đọc docs/database/db-schema.md mục 2.9 (stock_transfers, stock_reservations) và docs/design/design.md S23, F14. Làm:
- Endpoint tra tồn một sản phẩm ở mọi cơ sở của shop (khả dụng = on_hand − reserved, HSD gần nhất, không giá vốn). Gọi từ S17 khi kê thuốc hết hàng.
- Actions: RequestTransfer (lễ tân/bác sĩ tạo yêu cầu, gắn khách nếu có; tạo reservation reason=transfer ở cơ sở nguồn), ApproveTransfer (quản lý chi nhánh nguồn), ShipTransfer (kho nguồn: lô FIFO, người giao, movement transfer_out), ReceiveTransfer (kho nhận: số thực nhận, lệch → received_short + ghi chú; movement transfer_in giữ số lô, HSD, giá vốn, received_at gốc; có for_customer_id thì tạo reservation customer_hold 48h, thông báo lễ tân), CancelTransfer (nhả reservation).
- Chuyển trạng thái bằng UPDATE có điều kiện (nhận 2 lần không cộng kho 2 lần). Job nhả reservation hết hạn. Chỉ giữ hàng khi chuyển cho khách; kê thuốc, thêm dòng hóa đơn không giữ hàng.
- Policy theo staff_branches: cơ sở nguồn duyệt/xuất, cơ sở nhận xác nhận nhận.
- Test: cơ sở nhận không bán được phần đang giữ cho khách khác; nhận phiếu 2 lần đồng thời → 1 (`->group('mysql')`); hết hạn giữ thì khả dụng tăng lại; lô nhận giữ nguyên số lô + HSD + giá; cô lập tenant.
```

### Task 11.4: Site trưng bày sản phẩm

```prompt
Đọc docs/design/design.md S25. Trang sản phẩm công khai (products.is_public) trên Site `/{slug}`: ảnh, giá bán, tình trạng theo cơ sở chỉ "Còn hàng"/"Hết hàng" (không lộ số lượng). Không giỏ hàng, không đặt mua. Cache theo key tenant, xóa cache khi đổi giá/tồn.
```

### Task 11.5: Trả hàng

```prompt
Đọc docs/database/db-schema.md mục 2.9 (sales_returns), 2.7 (payments refund) và docs/design/design.md S31, F16. Làm:
- `CreateSalesReturn` (`returns.create`: lễ tân, thu ngân, quản lý chi nhánh): từ hóa đơn completed, chỉ dòng **sản phẩm**, chọn số lượng (lock hóa đơn gốc, không vượt số đã bán trừ số đã trả); hạn `tenant_settings.return_window_days` (mặc định 7), quá hạn cần `returns.overdue` (chủ shop), ghi is_overdue.
- Mỗi dòng nhân viên kiểm tay `condition`: intact → movement customer_return hoàn đúng lô theo movement gốc, giá vốn theo unit_cost gốc; defective → disposition pending_owner, danh sách "Hàng lỗi chờ xử lý" (không cộng tồn), chủ shop chọn hủy / trả nhà cung cấp / chuyển mã riêng (task 11.1); expired → write_off.
- Hoàn tiền: tiền mặt (cần ca thu ngân mở) hoặc chuyển khoản = payments kind refund gắn sales_return_id, trừ doanh thu ngày trả; activity_logs. Chứng từ trả nhà cung cấp ngoài V1.
- Test: trả 2 lần song song không vượt số bán (`->group('mysql')`); hàng nguyên về đúng lô; hàng lỗi không tăng on_hand; quá hạn lễ tân → 403, chủ shop được; báo cáo doanh thu và lãi gộp trừ phần trả đúng ngày; hoàn tiền mặt khi chưa mở ca → từ chối; cô lập tenant.
```

---

## Phase 12 (GĐ3): Ca làm, chấm công, lương

### Task 12.1: Mẫu ca, đăng ký ca, xếp ca

```prompt
Đọc docs/database/db-schema.md mục 2.11, 4.3 và docs/design/design.md 6.9 (S26), F17. Làm:
- Migration + model mục 2.11; seed public_holidays VN.
- shift_templates (theo cơ sở, ngày hoạt động, ca qua nửa đêm), coverage_requirements (min_count theo role), shift_assignments (có service_group để đếm người trực, rate chép từ staff_shift_rates).
- Đăng ký ca: màn "Đăng ký ca của tôi" (nhân viên chỉ thấy của mình) → shift_availabilities submitted; S26 hiện ô đã đăng ký, chủ shop/quản lý chi nhánh nhận (tạo shift_assignment trong cùng transaction) hoặc từ chối.
- S26 lưới tuần kéo-thả, thanh phủ ca cảnh báo thiếu người theo role. Không cho một người 2 ca chồng giờ dù khác cơ sở (lock hàng staff). Đổi ca: chủ sửa tay, ghi activity_logs.
- CapacityService: ngày có shift_assignments thì dùng ca được xếp thay cho staff_working_hours khi đếm người trực (test cả 2 nguồn).
- Test: ca qua nửa đêm và DST; nhận 2 đăng ký chồng giờ song song → 1 (`->group('mysql')`); thiếu người hiện cảnh báo; cô lập tenant.
```

### Task 12.2: Chấm công trên CMS + kiosk

```prompt
Đọc docs/database/db-schema.md mục 2.11 (attendance_*) và docs/design/design.md S27, S28, F17. Làm:
- Cách chính: nhân viên có tài khoản bấm "Chấm công vào/ra" trong CMS (`method = cms`), gắn shift_assignment gần nhất trong ngày, **cơ sở lấy từ ca**; không có ca → nhân viên chọn cơ sở, `is_off_schedule`, status `pending_approval`, chủ shop duyệt mới tính công. Ghi IP + user agent; kiểm `branches.allowed_ips` chỉ khi cơ sở có cài. Chấm 2 lần liền trong 1 phút = bỏ qua (idempotent).
- Kiosk cho nhân viên không tài khoản: chủ shop đăng ký thiết bị (attendance_devices, token lưu sha256, cookie dài hạn trên tablet), route `/{slug}/kiosk` (S27.1, ngoài /app), mã nhân viên + PIN (pin_hash, rate limit, khóa 15 phút sau 5 lần sai).
- Quên chấm ra → job auto_closed tại giờ kết thúc ca, vào danh sách cần duyệt. S28 bảng công, chủ shop sửa giờ có lý do + activity_logs.
- Test: chấm hộ người khác qua CMS bị chặn; cơ sở có cài IP thì IP lạ bị từ chối; kiosk thiết bị lạ / PIN sai bị khóa; auto close đúng giờ shop; cô lập tenant.
```

### Task 12.3: Lương theo ca + trang khóa cấp 2 ⭐

```prompt
Đọc docs/database/db-schema.md mục 2.11 (staff_shift_rates, payroll_*, sensitive_access_logs), 5.3 và docs/design/design.md S29, F17. Làm:
- Lương V1 **theo đơn giá ca**: staff_shift_rates (nhân viên × mẫu ca, hiệu lực từ ngày), payroll_periods theo tuần hoặc tháng (`tenant_settings.payroll_period_kind`, kỳ không chồng ngày), payroll_entries.shift_count + shift_pay = Σ rate các ca có chấm công hợp lệ, breakdown JSON từng ca. Đây là **số tiền dự kiến** để chủ shop kiểm tra. Lương tháng, phụ cấp, thưởng/phạt: sau V1, không làm.
- `LockPayrollPeriod`: khóa kỳ; sau đó sửa attendance thuộc kỳ → exception.
- Trang lương: `payroll.view` (chủ shop mặc định, gán thêm được cho người được ủy quyền), kiểm ở backend; middleware `RequireSensitiveUnlock:payroll`: nhập lại mật khẩu, mở 10 phút (session unlocked_until, không tự gia hạn), Cache-Control no-store, ghi sensitive_access_logs mọi lần mở khóa/xem/xuất. Đồng hồ đếm ngược + "Khóa ngay". Màn cài đơn giá ca theo nhân viên.
- Test: nhân viên thường gọi thẳng URL/API lương → 403/404, JSON không có khóa lương người khác; người được ủy quyền vào được; chưa mở khóa → chuyển sang nhập mật khẩu; quá 10 phút → khóa lại; sai mật khẩu nhiều lần bị throttle; ca tối đơn giá khác ca sáng tính đúng; kỳ đã khóa không tính lại; log được ghi; đăng nhập thay (task 4.6) không vào được trang lương.
```

---

## Phụ lục: Prompt review dùng sau mỗi task

```prompt
Review diff của branch hiện tại so với dev như một senior reviewer, tập trung: rò rỉ dữ liệu giữa shop (query thiếu scope, exists không kèm tenant_id, route binding, job thiếu context, cache key), lộ dữ liệu nhạy cảm trong shop (giá vốn, bệnh án, ghi chú nội bộ, lương: Resource theo quyền), race condition (hạn mức đặt lịch, số thứ tự, hóa đơn, tồn kho), N+1 / thiếu index, múi giờ (so sánh/format theo UTC hay giờ shop), idempotency, đối chiếu docs/database/db-schema.md và docs/analysis/nghiep-vu-da-chot.md, thiếu test. Liệt kê vấn đề theo mức độ, sửa những cái chắc chắn, hỏi lại những cái cần quyết định.
```
