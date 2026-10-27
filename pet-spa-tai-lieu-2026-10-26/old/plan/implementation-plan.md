# Kế hoạch triển khai: Pet Spa & Vet Booking SaaS (PawBook)

Repo: https://github.com/trancn-dev/pet-spa-booking-saas · Laravel 12 · MySQL 8 · một DB + `tenant_id`
Nguồn: `project-brief.md`, `design/db-schema.md`, `design/design.md` (thư mục project).

> **v2 (17/10/2026):** nghiệp vụ mở rộng theo mô tả của chủ tiệm. Chia 3 giai đoạn:
> - **MVP** (phase 1-10): đặt lịch + **tiếp nhận / hàng đợi vãng lai + lượt khám + hóa đơn** (task mới 5.8-5.10), đa chi nhánh ở mức schema + branch switcher.
> - **Giai đoạn 2** (phase 11): kho, thuốc theo lô, bán hàng, điều chuyển giữa chi nhánh.
> - **Giai đoạn 3** (phase 12): xếp ca, chấm công, lương.
> Task cũ giữ số, chỗ sửa ghi `(v2)`.
>
> **v4 (19/10/2026):** lô + HSD + FEFO (v6: đổi sang FIFO theo lô nhập) áp cho **mọi hàng có HSD** (thức ăn, vật tư), quy đổi thùng/gói (`product_units`), cảnh báo cận date theo sản phẩm, hủy hàng hết hạn. Vẫn ở **Giai đoạn 2**, sửa trong task 11.1-11.3, chỗ sửa ghi `(v4)`. Docs cần copy lại db-schema.md (1.4, 4.7, 5.4) và design.md (S19, S22, S24). Docs trong repo (`docs/`) cần copy lại bản mới của db-schema.md và design.md.

> **v3 (17/10/2026): quản trị nền tảng (super-admin).** **Phase 4 xếp lại: có admin trước, admin tạo shop, Owner nhận lời mời rồi mới vào CMS** (task 4.1-4.6, xem đầu Phase 4). Không có tự đăng ký ở MVP. Bảng mục 1.6 tạo cùng task 3.1; DemoSeeder tạo shop qua `ProvisionTenant` từ task 4.3. Cashier (8.1) để sau MVP. Docs cần copy lại bản mới của db-schema.md (1.6, 4.9, 5.6) và design.md (mục 11).

> **v5 (19/10/2026): theo bản tổng hợp nghiệp vụ user đã trao đổi lại** (`old/analysis/doi-chieu-nghiep-vu-chatgpt.md`). Hóa đơn `draft` → `completed`, mở nháp khi tiếp nhận, 1 hóa đơn nhiều pet, in nháp (5.8, 5.10); combo + mã dịch vụ (5.2); địa chỉ khách (5.1); chứng chỉ bác sĩ (4.4); nhật ký thao tác (5.10); trang Điều khoản (7.1); báo cáo doanh thu (9.1). GĐ2: phương pháp giá vốn **chưa chốt**, vật tư theo thực tế, lãi gộp từ sổ kho, bán thuốc không kê đơn, trả hàng, thành viên/tích điểm (11.1, 11.2, task mới 11.5). GĐ3: đăng ký ca, chấm công trên CMS, lương theo đơn giá ca, ủy quyền xem lương (12.1-12.3). Lưu chuồng ngoài V1. Chỗ sửa ghi `(v5)`; **[Cần nghiên cứu]** = chưa chốt, prompt không được tự chốt. Docs trong repo (`docs/`) cần copy lại db-schema.md và design.md bản v5.

> **v6 (20/10/2026): nghiệp vụ user chốt nhóm 1–14** (`analysis/so-do-nghiep-vu/nghiep-vu-da-chot.md`). Sửa: 3.1 (bảng mới, enum mới, bỏ `active_visit_id`), 4.2 (7 role mẫu, quản lý role), 5.4-5.7 (**hạn mức đặt online thay khóa hàng staff**, bỏ pending/completed, job no-show), 5.8-5.10 (cấp cứu, lượt độc lập hóa đơn, dòng gõ tay, không giảm giá tay, hủy nháp, Owner sửa = bản thay thế), **task mới 5.11 ca thu ngân**, 6.2 (số hồ sơ, dòng chi tiết, 24h, đính kèm), 7.1 (đặt online 5 bước có OTP), 11.1-11.2, 11.5 (trả hàng kiểm tay; tích điểm sau V1), 12.2. (v6) Nhắc khách qua **Zalo ZNS + SMS** (task 6.1). Chỗ sửa ghi `(v6)`. Kho (15.1, 15.2, 16.1, đã chốt): **xuất FIFO theo lô nhập** (thay FEFO v4), giá vốn = giá lô xuất, chỉ owner xem; phiếu nhập 2 bước (kho lập không thấy giá, cộng tồn ngay → owner điền giá); phiếu hao hụt chờ duyệt. Không còn câu hỏi mở chặn task 11.1. Docs trong repo cần copy lại bản v6.

---

## 0. Cách dùng file này

1. **Một task = một phiên Claude Code = một branch/PR.** Copy nguyên khối `prompt` của task vào Claude Code. Không gộp nhiều task vào một phiên: context loãng, PR khó review.
2. Với task có nhãn ⭐ (lõi, dễ sai): bật **plan mode** trước (`Shift+Tab` hai lần), đọc kế hoạch Claude đưa ra, sửa rồi mới cho chạy.
3. Mọi prompt đều giả định repo đã có `CLAUDE.md` và `docs/` (task 1.3). Nhờ vậy prompt ngắn mà vẫn đủ ngữ cảnh. **Làm task 1.3 sớm**, kể cả khi phase 1 đã được làm ở chỗ khác.
4. Sau mỗi task, tự kiểm theo mục **"Xong khi"**. Chưa đạt thì yêu cầu Claude sửa tiếp trong cùng phiên, đừng merge.
5. Thứ tự phase là thứ tự phụ thuộc. Trong cùng phase, task đánh số theo thứ tự nên làm.
6. Ưu tiên **làm sâu** phase 2, 5, 6 (multi-tenant, đặt lịch, queue). Đó là phần nhà tuyển dụng senior đọc. Phase 9 (UI dashboard, báo cáo) có thể cắt bớt.

> ⚠️ **Tiến độ thực tế (17/10/2026, nhánh `dev` của repo):** đã xong phase 1 (Laravel 12 + Docker + Pint/Larastan/Pest + CI), phase 2 (TenantContext, BelongsToTenant fail closed, ResolveTenant theo `/app/{tenant:slug}`), starter kit Vue/Inertia + auth với `users` toàn cục, bảng `staff` tối thiểu, và docs v2. Nhánh `main` còn bản cũ (resolve theo host, `users` theo tenant): **đừng dựa vào `main`**. Việc tiếp theo: task 1.3 (copy docs v3), 3.1, rồi Phase 4 theo thứ tự mới. Với task đã làm, chỉ chạy prompt "kiểm tra & bổ sung" để đối chiếu "Xong khi".

### Lộ trình gợi ý (làm buổi tối/cuối tuần)

| Tuần | Phase |
|---|---|
| 1 | 1. Init + tooling |
| 2 | 2. Nền multi-tenant · 3. Migration + seed |
| 3-4 | 4. (v3) Admin → tạo shop → phân quyền: 4.1 Admin · 4.2 Roles · 4.3 Tạo shop + Owner nhận lời mời + CMS · 4.4 Mời nhân viên · 4.5 Gói/hạn, khóa · 4.6 Impersonate |
| 4 | 5a. Khách hàng, pet, dịch vụ, lịch làm việc |
| 5-6 | 5b. ⭐ Availability + đặt lịch + test concurrency + UI lịch |
| 7 | 6. Queue, nhắc hẹn, tiêm phòng, hồ sơ y tế |
| 8 | 7. Site của tiệm (đặt lịch công khai) |
| 9 | 8. Billing + quota |
| 10 | 9. Dashboard, hiệu năng, observability · 10. Deploy, README, ADR |
| 6-7 (chèn sau 5.7) | (v2) 5.8 Tiếp nhận + hàng đợi · 5.9 Lượt khám · 5.10 ⭐ Hóa đơn + thanh toán |
| 11-13 | (v2) **GĐ2** Phase 11: kho, bán hàng, điều chuyển |
| 14-15 | (v2) **GĐ3** Phase 12: ca làm, chấm công, lương |

Lộ trình MVP vì vậy dài thêm khoảng 2 tuần; các tuần ở trên dịch tương ứng.

---

## Phase 1: Khởi tạo & tooling

### Task 1.1: Khởi tạo Laravel + Docker

```prompt
Repo hiện chỉ có README và LICENSE. Khởi tạo dự án:

- Laravel 12 với starter kit Vue (Inertia 2 + Vue 3 `<script setup>` + TypeScript + Tailwind), cài vào thư mục gốc repo, giữ LICENSE.
- Docker cho môi trường dev bằng docker compose (Laravel Sail cũng được, nhưng service phải có): app (PHP 8.3+), mysql:8.0, redis:7, mailpit. Thêm queue worker chạy riêng sau khi có Horizon (để TODO trong compose).
- Cấu hình thời gian: `config/app.php` timezone = 'UTC'; connection mysql thêm `'timezone' => '+00:00'`; charset utf8mb4.
- `.env.example` đủ biến cho docker (DB_HOST=mysql, REDIS_HOST=redis, MAIL_HOST=mailpit), QUEUE_CONNECTION=redis, CACHE_STORE=redis, SESSION_DRIVER=redis.
- Tạo `Makefile` (hoặc composer scripts) với: up, down, sh, test, lint, analyse, fresh (migrate:fresh --seed).
- README: mục "Chạy local" ngắn.

Chạy `php artisan migrate` và `npm run build` trong container để chứng minh chạy được. Commit theo từng bước hợp lý.
```

**Xong khi:** `docker compose up -d && make fresh` chạy sạch; mở trình duyệt đăng ký/đăng nhập được; `SELECT @@session.time_zone` từ app trả `+00:00`.

### Task 1.2: Chất lượng code & CI

```prompt
Thêm tooling chất lượng cho repo Laravel 12 hiện tại:

- Pest 3 (+ pest-plugin-laravel, pest-plugin-arch). Chuyển test mặc định sang Pest. Test chạy trên **MySQL thật** (DB `testing` riêng), không SQLite: dự án dựa vào FK ghép, lock và hành vi DATETIME của MySQL.
- Larastan level 8 (mục tiêu max sau này), `phpstan.neon` có baseline rỗng. Fix lỗi hiện có thay vì baseline.
- Laravel Pint (preset laravel) + ESLint/Prettier cho resources/js (giữ cấu hình starter kit nếu có).
- Arch test cơ bản: không dùng `dd`, `dump`, `ray`; `App\Models` extend Model; `declare(strict_types=1)` cho app/.
- GitHub Actions `.github/workflows/ci.yml`: job lint (pint --test, eslint), job analyse (phpstan), job test (service mysql:8.0 + redis, `pest --parallel --coverage --min=70`), build assets. Cache composer + npm.
- Bật trong AppServiceProvider (môi trường không phải production): `Model::shouldBeStrict()` (chặn lazy loading = phát hiện N+1, chặn gán thuộc tính không tồn tại).
- Thêm badge CI vào README.
```

**Xong khi:** PR có CI xanh cả 3 job; cố tình viết 1 lazy load trong test thì test fail.

### Task 1.3: CLAUDE.md + tài liệu thiết kế trong repo

> Trước khi chạy: copy từ project vào repo theo cấu trúc nhánh `dev` (Claude Code trên máy bạn không đọc được thư mục project): `design/db-schema.md` + 3 ảnh ERD → `docs/database/`, `design/design.md` → `docs/design/`, `plan/implementation-plan.md` → `docs/plan/`, `project-brief.md` → `docs/`. Mỗi lần tài liệu đổi phiên bản (v2, v3…) copy lại cả bộ.

```prompt
Thư mục docs/ có database/db-schema.md (+ ảnh ERD), design/design.md, plan/implementation-plan.md, project-brief.md. Tạo các file sau:

1. `CLAUDE.md` ở gốc repo (tiếng Việt, ngắn, dạng gạch đầu dòng) gồm:
   - Mô tả dự án 3 dòng, link tới docs/.
   - Lệnh: chạy test, lint, phpstan, migrate fresh (theo Makefile).
   - Quy ước bắt buộc (lấy từ docs/database/db-schema.md): mọi bảng tenant có tenant_id + UNIQUE(tenant_id,id); FK giữa 2 bảng tenant là FK ghép (tenant_id, x_id) → (tenant_id, id); index ghép bắt đầu bằng tenant_id; DATETIME lưu UTC, ca làm TIME giờ local tiệm; public_id ULID cho thứ lộ ra URL công khai; soft delete cho owners/pets/services/staff.
   - Quy ước code: PHP 8.3 typed, `declare(strict_types=1)`, logic nghiệp vụ trong `app/Actions/*` (một class một use case, method `handle`), DTO readonly class, query phức tạp trong Query/Service class, Controller mỏng, FormRequest để validate, Policy để phân quyền. Vue 3 `<script setup lang="ts">`, Inertia.
   - Quy tắc tenant: không bao giờ gọi `withoutGlobalScope(s)` ngoài whitelist; validation `exists` phải kèm where tenant_id; cache key prefix `t:{tenant_id}:`; job phải TenantAware.
   - Mỗi tính năng phải có Pest test, gồm test cô lập tenant (user tenant A truy cập ID tenant B → 404).
   - Không sửa migration đã merge; tạo migration mới.
2. `docs/adr/0000-template.md` (Context, Decision, Alternatives, Consequences) và `docs/adr/README.md` liệt kê ADR.
3. `docs/architecture.md` để trống các mục: Tổng quan, Multi-tenancy, Đặt lịch, Queue, Billing (sẽ điền dần).
```

**Xong khi:** mở phiên Claude Code mới, hỏi "quy ước FK giữa 2 bảng tenant là gì" → trả lời đúng mà không cần đọc lại docs.

---

## Phase 2: Nền multi-tenant ⭐

### Task 2.1: Tenant context + BelongsToTenant

```prompt
Đọc CLAUDE.md, docs/database/db-schema.md mục 1.1 (tenants) và 5.2. Làm nền multi-tenant (một DB + tenant_id), KHÔNG dùng stancl/tenancy:

- Migration `tenants` đúng schema (public_id ULID, slug UNIQUE, timezone IANA, locale, currency, plan_id nullable tạm thời chưa FK nếu bảng plans chưa có, status). Model Tenant.
- `App\Tenancy\TenantContext` (scoped singleton): set/get/clear/has, `require()` ném `TenantNotResolvedException`. Helper `tenant()`.
- Trait `BelongsToTenant`: global scope `TenantScope` lọc theo tenant hiện tại; tự gán tenant_id khi `creating`; **fail closed**: query hoặc create khi chưa có tenant context thì ném exception (không trả về toàn bộ dữ liệu). Quan hệ `tenant()`.
- Cách chạy có chủ đích không có tenant (scheduler, admin): `TenantContext::runAs(Tenant $t, callable)` và `Tenant::forEach(callable)`; KHÔNG dùng withoutGlobalScope rải rác.
- Middleware `ResolveTenant`: route nhóm `/app/{tenant:slug}`; check user hiện tại là thành viên (bảng staff sẽ có ở phase 3: để interface `TenantMembership` + TODO, test bằng fake); set TenantContext; lưu users.last_tenant_id.
- Ở Octane/queue: đảm bảo context được clear giữa request/job.
- Test Pest: scope lọc đúng, fail closed khi không có context, auto gán tenant_id, không ghi đè tenant_id khi đã có context khác (ném exception), runAs khôi phục context cũ kể cả khi callable ném lỗi.
- Viết `docs/adr/0001-single-db-tenant-id.md` (so sánh với DB riêng mỗi tenant / stancl/tenancy) và cập nhật mục Multi-tenancy trong docs/architecture.md.
```

**Xong khi:** test xanh; đọc trait thấy không có đường nào trả dữ liệu khi thiếu context.

### Task 2.2: Lưới an toàn chống rò rỉ tenant

```prompt
Đọc CLAUDE.md. Thêm các lớp bảo vệ chống rò rỉ dữ liệu giữa tenant:

- Rule tiện dụng `TenantExists::in('pets')` (hoặc macro `Rule::tenantExists`) = Rule::exists(...)->where('tenant_id', tenant()->id).
- Route model binding: model BelongsToTenant tự resolve qua scope → ID tenant khác trả 404 (viết test chứng minh).
- Arch test: cấm gọi `withoutGlobalScope`/`withoutGlobalScopes` ngoài danh sách class whitelist (định nghĩa trong test). Nếu Pest arch không bắt được lời gọi method, viết test quét source bằng nikic/php-parser hoặc PHPStan custom rule (ưu tiên PHPStan rule, đặt ở `tools/phpstan`).
- Helper test: `actingAsStaffOf($tenant, role)` và một Pest dataset/macro `assertTenantIsolated(routeName, model factory)` để các feature sau dùng: tạo bản ghi ở tenant B, user tenant A gọi show/update/delete → 404.
- Cache: helper `tenant_cache_key(string $key)` → `t:{id}:{key}`; Rate limiter key cũng prefix tenant.
- Job: interface/trait `TenantAware` + job middleware set TenantContext từ tenant_id đã serialize; dispatch khi có context thì tự gắn tenant_id. Test: job dispatch trong tenant A chạy đúng context A dù worker đang không có context.
```

**Xong khi:** dataset helper dùng được trong 1 dòng; PHPStan báo lỗi khi thử thêm `withoutGlobalScopes()` vào controller.

**Kiểm tra & bổ sung (nếu phase 1-2 đã được thread khác làm):**

```prompt
Đọc CLAUDE.md và docs/database/db-schema.md mục 5.2. Repo đã có nền multi-tenant. Đối chiếu code hiện tại với các yêu cầu sau và liệt kê cái nào đã đạt / thiếu, rồi bổ sung phần thiếu kèm test: global scope fail closed khi thiếu context; auto gán tenant_id; runAs/forEach thay cho withoutGlobalScope; middleware resolve tenant theo slug + check membership; Rule exists theo tenant; route binding 404 chéo tenant; chặn withoutGlobalScopes bằng arch/PHPStan; job TenantAware; cache key prefix tenant; Model::shouldBeStrict; test chạy trên MySQL; CI xanh.
```

---

## Phase 3: Migration, model, seed

### Task 3.1: Bảng dùng chung + migration toàn bộ schema

```prompt
Đọc CLAUDE.md và docs/database/db-schema.md (toàn bộ). Tạo migration + model + factory cho TẤT CẢ bảng trong mục 1.1, 1.2, 1.3 và (v3) 1.6 (gồm cột mới của `tenants`; trừ bảng của Cashier và spatie: để phase sau; mục 1.4, 1.5 làm ở phase 11, 12).

Yêu cầu:
- Đúng kiểu cột, enum, index, unique như tài liệu. DATETIME (không TIMESTAMP) cho mọi thời điểm nghiệp vụ; `created_at/updated_at` cũng dùng `datetime` (viết macro Blueprint `datetimesUtc()` nếu cần).
- Mọi bảng tenant: `UNIQUE(tenant_id, id)` và FK ghép tới bảng tenant khác: `$table->foreign(['tenant_id','owner_id'])->references(['tenant_id','id'])->on('owners')`. FK tới bảng dùng chung (species, breeds, users) là FK đơn.
- Thứ tự migration đúng phụ thuộc. Chú ý FK ghép với cột nullable (vd medical_records.appointment_id) vẫn hợp lệ.
- Model: dùng BelongsToTenant cho bảng tenant, HasUlids/public_id cho owners & appointments (route key = public_id ở portal), SoftDeletes theo tài liệu, `casts()` đầy đủ (enum PHP backed cho status/size/category/channel… đặt ở app/Enums), quan hệ đầy đủ.
- Hằng `Appointment::BLOCKING_STATUSES` = confirmed, checked_in ((v6) enum chỉ còn confirmed, checked_in, cancelled, no_show; `staff_id` nullable, `preferred_staff_id`, `service_group`).
- (v2) `branches` có `UNIQUE(tenant_id, id)`; mọi `branch_id` là FK ghép `(tenant_id, branch_id)`. Chi nhánh KHÔNG có global scope. `staff.user_id` nullable. `owners.phone` nullable chỉ khi `is_system` (CHECK constraint). (v5, v6) Hóa đơn **không** có `visit_id`/`active_visit_id`: `visits.invoice_id` FK ghép; CHECK `kind='visit'` thì có owner_id; `visits.status` không có `invoiced`; `invoice_lines.line_type` có `custom`.
- (v6) Thêm: `pets.record_no` (unique theo tenant), `medical_records` theo v6 (bỏ `appointment_id`, có `internal_notes`, `care_instructions`, `follow_up_on`, `editable_until`), `medical_record_addenda`, `attachments`, `online_booking_rates`, `booking_capacity_locks`, `cash_drawers`, `cash_sessions` (+ `payments.cash_session_id`), `tenants.return_window_days`.
- Factory cho mọi model, factory tenant-aware (tự dùng tenant hiện tại hoặc tạo mới cùng tenant cho quan hệ con: pet và owner của nó phải cùng tenant).
- Test: migrate:fresh chạy được; test DB-level chứng minh FK ghép chặn gắn pet tenant A vào owner tenant B (insert thẳng bằng DB::table, kỳ vọng QueryException). (v2) Thêm: FK ghép chặn gắn appointment vào branch của tenant khác; 2 hóa đơn không void cho cùng visit → lỗi unique; hóa đơn visit thiếu owner_id → lỗi CHECK; (v6) 2 ca thu ngân mở cùng một két → lỗi unique (`->group('mysql')`).
```

**Xong khi:** `make fresh` sạch; test FK ghép xanh; `php artisan model:show Appointment` thấy đủ quan hệ và cast.

### Task 3.2: Seeder dữ liệu demo

```prompt
Đọc docs/design/design.md mục 8. Viết seeder:
- `ReferenceDataSeeder` (chạy ở mọi môi trường, idempotent bằng upsert): plans (Starter 2 nhân viên/150 lịch, Pro 5/500, Clinic không giới hạn = null), species (dog, cat), breeds phổ biến VN có default_size.
- `DemoSeeder` (chỉ local/demo): tiệm "Paw House Spa & Vet" slug paw-house, tz Asia/Ho_Chi_Minh; (v2) 2 chi nhánh Q3, Q7 (docs/design/design.md mục 10.4), mỗi chi nhánh một hồ sơ hệ thống "Thú hoang/vô chủ", nhân viên không tài khoản (bảo vệ, lao công), hàng đợi hôm nay 9 lượt + vài hóa đơn đã thanh toán; 5 nhân viên đúng tên/vai trò/màu (role gán sau khi có phase 4: để TODO); ca làm 08:00-20:00, một người ca gãy, một người nghỉ chiều nay; dịch vụ + quy tắc thời lượng S/M/L; chủ nuôi & pet như bảng (dị ứng, "sợ máy sấy"); phác đồ vaccine 7 bệnh + tẩy giun 90 ngày; pet_care_records để Sushi đang mũi 2/4 và Tôm quá hạn tẩy giun 12 ngày; khoảng 14 lịch hôm nay đủ các status (tính theo ngày hiện tại, giờ local tiệm → UTC).
- Thêm tenant thứ hai nhỏ ("Meow Clinic") để demo cô lập dữ liệu.
- (v3) Shop demo theo docs/design/design.md 11.3 (Paw House đang hoạt động, Meow Clinic quá hạn, Happy Tails dùng thử, Bé Cún Spa tạm khóa) kèm `tenant_subscriptions`. Ở task này tạo tenant trực tiếp; task 4.3 sẽ đổi sang gọi `ProvisionTenant`. Admin demo thêm ở task 4.1.
- Seeder phải chạy lại được (`migrate:fresh --seed`) và không phụ thuộc thứ tự ngẫu nhiên.
```

**Xong khi:** `make fresh` ra dữ liệu đúng như design.md; mở DB thấy giờ lưu UTC (08:00 VN = 01:00).

---

## Phase 4: Admin nền tảng → tạo shop → phân quyền (v3: xếp lại)

> **Thứ tự v3 (đã chốt với user):** có admin trước, admin tạo shop, Owner nhận lời mời rồi mới vào CMS. **Không có tự đăng ký** ở MVP. Phụ thuộc:
> ```
> 4.1 Admin đăng nhập + khung  →  4.2 Roles & permissions  →  4.3 Admin tạo shop + Owner nhận lời mời + CMS
>                                                          →  4.4 Owner mời nhân viên (dùng lại luồng nhận lời mời của 4.3)
>                                                          →  4.5 Gói/hạn, tạm khóa  →  4.6 Impersonate
> ```
> - 4.1 không cần spatie nên làm đầu tiên được. 4.3 cần role `owner` (4.2).
> - Theo code nhánh `dev`: `users` là danh tính toàn cục (UNIQUE email), thành viên tiệm = dòng `staff`, route tiệm `/app/{tenant:slug}`, `TenantContext`. Phase 4 giữ nguyên kiến trúc đó.
> - Owner và nhân viên **đều vào tiệm qua `invitations`** (Owner do admin mời, nhân viên do Owner mời): một luồng nhận lời mời duy nhất, không có user không mật khẩu.
> - Từ 4.3, **mọi shop (kể cả DemoSeeder) đều tạo qua `ProvisionTenant`**. Các bảng mục 1.6 đã tạo ở task 3.1.
> - Số task cũ: 4.1 Roles → nay 4.2; 4.2 Đăng ký/workspace → bỏ tự đăng ký (phần CMS/switcher/chi nhánh gộp vào 4.3; tự đăng ký xem cuối Phase 8); 4.3 Mời nhân viên → nay 4.4.

### Task 4.1 (v3): Khu quản trị nền tảng: đăng nhập + khung ⭐

```prompt
Đọc CLAUDE.md, docs/database/db-schema.md mục 1.6, 4.9, 5.6 và docs/design/design.md mục 11 (A1, A2). Làm khu /admin cho nhà cung cấp, CÙNG app. Bảng `platform_admins` đã có từ task 3.1 (nếu chưa thì tạo migration theo db-schema 1.6).
- Model `App\Platform\Models\PlatformAdmin`. Guard `platform` (session) với provider riêng. Không thêm cột quyền nào vào `users`.
- Route group `/admin`, KHÔNG có middleware `tenant`, dùng middleware group riêng đặt session cookie riêng (`pawbook_admin_session`) TRƯỚC StartSession: đăng xuất ở CMS không làm mất phiên admin và ngược lại.
- Thêm `admin` vào danh sách slug cấm (xem 4.3) để không có tiệm nào chiếm `/admin`.
- Đăng nhập email + mật khẩu + TOTP (2FA bắt buộc, có màn bật 2FA lần đầu), rate limit 5 lần/phút theo email+IP. Command `platform:admin-create {email} {--role=super_admin}`: cách DUY NHẤT tạo admin đầu tiên (chạy một lần khi dựng môi trường); không có trang đăng ký admin.
- Layout admin theo design.md 11 (sidebar tối, badge môi trường). Màn A2 danh sách shop (chỉ đọc ở task này): đọc qua `App\Platform\Queries\TenantList` dùng `TenantContext::withoutTenancy()`, đếm chi nhánh/nhân viên/lịch tháng bằng subquery `withCount` (không N+1, assert số query cố định). Empty state "Chưa có shop nào" (nút Tạo shop làm ở 4.3).
- Arch test: whitelist có tên cho `App\Platform\Models\*`; cấm gọi `withoutTenancy` ngoài `App\Platform\*`, scheduler/command đã liệt kê.
- DemoSeeder: thêm 2 admin theo design.md 11.3 (mật khẩu demo; bỏ qua 2FA chỉ khi APP_ENV=local/demo, có test production không bỏ qua được).
- Test: user (guard web) gọi /admin/* → 404; admin chưa bật 2FA không vào được A2; admin role support không thấy nút tạo shop; đăng xuất web không đăng xuất admin.
- docs/adr/0007-platform-admin.md (cùng app vs app riêng; bảng riêng vs is_admin).
```

**Xong khi:** `php artisan platform:admin-create` → đăng nhập `localhost:8080/admin` với 2FA → thấy danh sách shop từ DemoSeeder; mọi test cô lập tenant cũ vẫn xanh.

### Task 4.2: Roles & permissions (spatie teams mode)

```prompt
Đọc CLAUDE.md, docs/database/db-schema.md (roles/permissions) và docs/design/design.md mục 2. Cài spatie/laravel-permission với teams mode, team_id = tenant_id:
- (v6) **Roles theo tenant** (team_id = tenant_id), sinh sẵn 7 role mẫu khi `ProvisionTenant`: owner, branch_manager, receptionist, cashier, vet, groomer, inventory theo **ma trận quyền docs/design/design.md mục 13**. Màn Cài đặt › Phân quyền: Owner sửa permission của role, tạo role mới (role owner khóa). Một staff nhiều role → cộng quyền. Bệnh án tách `medical_records.view_full` (vet) và `medical_records.view_alerts` (receptionist, groomer, branch_manager); cashier/inventory không có. Permissions chi tiết theo module (appointments.*, owners.*, pets.*, medical_records.view/edit, vaccinations.record, services.manage, staff.manage, billing.manage, reports.view). (v2) Thêm: visits.*, invoices.create/pay/void, reports.view_today (lễ tân) vs reports.view_all (owner), branches.manage; chừa sẵn tên cho GĐ2/3: inventory.view, inventory.view_cost (chỉ owner), inventory.transfer, payroll.view (owner mặc định; (v5) Owner gán được cho người được ủy quyền, kiểm ở backend). (v5) Thêm: invoices.void/refund (chỉ owner tới khi chốt quy trình), activity_logs.view, shifts.register (nhân viên tự đăng ký ca). Staff chỉ thao tác ở chi nhánh có trong staff_branches (Policy). Seeder phân quyền theo ma trận mục 13 (v6: lễ tân/groomer chỉ thấy cảnh báo bệnh án; chỉ vet ghi; groomer chỉ xem lịch của mình). (v6) Bỏ `invoices.void` cho lễ tân với hóa đơn completed; thêm `invoices.void_draft` (receptionist, cashier), `invoices.amend` (chỉ owner), `cash_sessions.manage`, `returns.create`, `returns.overdue` (owner).
- Middleware ResolveTenant gọi `setPermissionsTeamId(tenant()->id)`; clear cache permission đúng cách khi đổi team.
- Gán role theo staff (một user có thể owner ở tiệm A, groomer ở tiệm B).
- (v3) Role/permission CHỈ cho guard `web` (nhân viên tiệm). Admin nền tảng (guard `platform`) dùng `platform_admins.role`, không dùng spatie.
- Policy cho từng model tenant; Gate::before cho owner (trừ medical_records.edit nếu owner không phải vet: owner Trần Minh Khoa là owner + vet, nên hỗ trợ nhiều role).
- Chia sẻ quyền xuống Inertia (`can` props) để UI disable nút kèm tooltip, không ẩn dữ liệu được phép xem.
- Cập nhật DemoSeeder gán role. Test: ma trận vai trò × hành động (Pest dataset), và user là owner tiệm A nhưng groomer tiệm B thì quyền đúng theo tiệm đang mở.
```

**Xong khi:** test ma trận xanh; đổi workspace thì quyền đổi theo.

### Task 4.3 (v3): Admin tạo shop → Owner nhận lời mời → vào CMS ⭐

```prompt
Đọc docs/database/db-schema.md 1.6, 4.9 và docs/design/design.md A3, A4 (tab Tổng quan), 10.1, 11.1, 11.4, F7, F12. Làm luồng bán shop từ đầu đến cuối:
- Action `ProvisionTenant` (một transaction): tenant (status `trial` hoặc `active` theo gói) + chi nhánh đầu tiên + `tenant_subscriptions` đầu + `invitations` role `owner` (`invited_by_admin_id`, hạn 7 ngày) + audit `tenant.created`. Sau commit queue mail lời mời. Đây là đường DUY NHẤT tạo tenant: refactor DemoSeeder và factory "tenant đầy đủ" gọi Action này (seeder tự nhận lời mời thay Owner demo).
- Slug: unique + danh sách cấm gồm mọi segment route trung tâm (admin, app, api, login, logout, register, dashboard, settings, forgot-password, reset-password, verify-email, confirm-password, invite, email, storage, build, www…); test tự quét `Route::getRoutes()` lấy segment đầu để danh sách cấm không bị thiếu khi thêm route mới (Site của tiệm nằm ở `/{slug}`, task 7.1).
- Nhận lời mời (route trung tâm `/invite/{token}`, dùng chung cho Owner ở đây và nhân viên ở 4.4): token 32 byte, DB lưu sha256, 1 lần. Email chưa có user → form tạo tài khoản (tên, mật khẩu) ngay trên trang; đã có user → đăng nhập đúng email đó rồi nhận. Accept trong transaction, idempotent: tạo staff + gán role + staff_branches; xong redirect `/app/{slug}` với checklist lần đầu (Thêm dịch vụ, Mời nhân viên, Chia sẻ link Site).
- Bỏ tự đăng ký: gỡ route `register` của starter kit (và link "Đăng ký" trên màn login). User đăng nhập mà chưa thuộc tiệm nào → trang "Bạn chưa thuộc tiệm nào, hãy dùng link mời trong email".
- Màn A3 wizard 3 bước + A4 tab Tổng quan (Owner: "Chưa nhận lời mời · Gửi lại / Sao chép link"; gửi lại = token mới, vô hiệu token cũ). Role support: chỉ xem.
- CMS layout theo design.md 3.4 (sidebar, topbar có tên tiệm + workspace switcher). Token màu mục 3.1 làm CSS variables + Tailwind theme, có dark mode.
- (v2) Branch switcher (design.md 10.1): `CurrentBranch` trong session, chỉ chọn chi nhánh trong staff_branches. Màn Cài đặt › Chi nhánh (CRUD).
- Test: provision tạo đủ bản ghi và rollback toàn bộ nếu bước nào lỗi; slug trùng/cấm (kể cả segment route mới); token sai/hết hạn/đã dùng; email đăng nhập khác email được mời → từ chối; nhận 2 lần không tạo 2 staff; route /register không tồn tại; user của shop mới không thấy dữ liệu shop khác (extend tests/Feature/Tenancy).
```

**Xong khi:** admin tạo shop ở `localhost:8080/admin` → Mailpit có lời mời → Owner tạo tài khoản ở `/invite/...` → vào `/app/{slug}` thấy checklist; `migrate:fresh --seed` vẫn ra đủ shop demo qua ProvisionTenant.

### Task 4.4: Mời nhân viên

```prompt
Đọc docs/database/db-schema.md (invitations, staff) và docs/design/design.md S11, F7. Làm luồng Owner mời nhân viên (trong CMS), dùng lại trang nhận lời mời `/invite/{token}` của task 4.3:
- Owner mời email + role. Token ngẫu nhiên 32 byte gửi qua mail (queued), DB chỉ lưu sha256 (token_hash). Hết hạn 7 ngày. Gửi lại = token mới, vô hiệu token cũ. Thu hồi được.
- Giới hạn gói max_staff: kiểm tra khi mời VÀ khi accept (đếm staff active + lời mời còn hạn), có lock để 2 accept đồng thời không vượt (lockForUpdate trên hàng tenant).
- Trang Nhân viên: bảng staff (màu, role, is_bookable, status), tab Lời mời; vô hiệu nhân viên (status disabled, không xóa).
- (v5) Form hồ sơ nhân viên: thông tin cá nhân, liên hệ, vai trò, chi nhánh; khối chuyên môn (`license_no`, `qualifications`) bắt buộc khi position = vet, ẩn với vai trò khác.
- (v2) Thêm nhân viên **không tài khoản** (position security/cleaner/assistant, user_id NULL, không gửi lời mời, không tính vào max_staff **[mặc định, cần xác nhận]**); gán chi nhánh (staff_branches).
- Test: token sai/hết hạn/đã dùng, email không khớp, vượt quota, cô lập tenant; người đã là nhân viên tiệm A nhận lời mời tiệm B → có 2 workspace trong switcher.
```

**Xong khi:** mời → nhận mail ở Mailpit → accept → thấy workspace trong switcher.

### Task 4.5 (v3): Gói & hạn dùng, tạm khóa ⭐

```prompt
Đọc docs/database/db-schema.md 1.6, 4.9 và docs/design/design.md A4 (tab Gói & gia hạn, Nhật ký), A5, 11.1, F13. Làm vòng đời sau khi tạo shop:
- Action `ExtendSubscription` (gia hạn nối tiếp hạn hiện tại, thêm dòng tenant_subscriptions, cập nhật tenants.plan_id/plan_expires_at trong cùng transaction), `SuspendTenant`, `ResumeTenant`: đổi status bằng compare-and-set (`WHERE status = :expected`), mỗi action đúng 1 dòng `platform_audit_logs` (append-only: model ném exception khi update/delete).
- Sửa `ResolveTenant`: hiện đang 404 mọi status ≠ active. Đổi thành: trial/active/past_due/suspended đi tiếp, chỉ `cancelled` → 404. Thêm middleware `EnsureTenantActive` ngay sau: suspended → request ghi trả 423 + trang thông báo, GET vẫn được; Site hiện "tạm ngưng nhận lịch". Banner hạn dùng/quá hạn/tạm khóa trong CMS (Inertia shared props).
- Màn A4 tab Gói & gia hạn + modal Tạm khóa, tab Nhật ký; A5 nhật ký toàn hệ thống. Role support: chỉ xem.
- Command `tenants:check-expiry` chạy hằng ngày: ≤7 ngày → mail nhắc (dedupe theo tenant + ngày hết hạn); quá hạn → past_due; quá 7 ngày ân hạn → suspended. Job nhắc hẹn bỏ qua tenant suspended.
- Test: suspended chặn POST nhưng cho GET; cancelled → 404; check-expiry chuyển đúng trạng thái theo mốc thời gian (Carbon::setTestNow); job và admin đồng thời đổi status không ghi đè nhau; gia hạn khi đang suspended do hết hạn → active ngay; mỗi action có đúng 1 dòng audit.
```

**Xong khi:** khóa shop thì CMS không tạo được lịch nhưng vẫn xem được; gia hạn xong mở lại ngay; A5 thấy đủ log.

### Task 4.6 (v3): Impersonate có ghi log ⭐

```prompt
Đọc docs/database/db-schema.md 4.9 (Impersonate), 5.6 và docs/design/design.md A4, 11.1. Làm "Đăng nhập như Owner":
- Bảng `impersonation_sessions` (đã có từ 3.1). Admin nhập lý do (bắt buộc) → tạo phiên, token 32 byte (DB lưu sha256, hết hạn 60s) → mở tab mới tới `/app/{slug}/impersonate/{token}`. Cần token vì phiên admin dùng cookie riêng, không đọc được từ phiên CMS.
- Route `/app/{slug}/impersonate/{token}`: tiêu thụ token 1 lần bằng UPDATE có điều kiện (affected = 0 → 404; token phải thuộc đúng tenant của slug), đăng xuất user web hiện tại nếu có, đăng nhập guard web là Owner, session lưu impersonation_session_id; `Referrer-Policy: no-referrer`.
- Middleware `ImpersonationGuard`: hết 30 phút → kết thúc + logout; chặn các route nhạy cảm (đổi mật khẩu/email, trang lương, hủy hóa đơn, xóa, mời/khóa nhân viên) bằng danh sách route name; mọi request ghi khác ghi `platform_audit_logs` action `impersonation.write`. Trong phiên, workspace switcher chỉ có tiệm đang hỗ trợ.
- Banner đỏ đếm ngược + nút Thoát (kết thúc phiên, ghi ended_at). Inertia prop `impersonating` để UI disable nút bị chặn.
- Tab "Truy cập hỗ trợ" ở A4 và "Lịch sử truy cập hỗ trợ" trong Cài đặt của Owner (Owner chỉ thấy phiên của tenant mình).
- Test: token dùng 2 lần / hết hạn / dùng với slug tiệm khác → 404; route nhạy cảm bị chặn trong phiên; request ghi tạo audit; hết 30 phút bị logout; Owner tenant B không thấy phiên của tenant A; không chuyển sang tiệm khác của Owner được trong phiên.
```

**Xong khi:** từ A4 bấm "Đăng nhập như Owner" → tab mới vào CMS có banner đỏ → tạo 1 lịch → A4 thấy log; bấm Thoát thì phiên đóng.

---

## Phase 5: Nghiệp vụ lõi

### Task 5.1: Chủ nuôi & thú cưng

```prompt
Đọc docs/database/db-schema.md (owners, pets) và docs/design/design.md S4, S10. Làm CRUD:
- (v2) SĐT là khóa khách, unique theo tenant, dùng chung mọi chi nhánh. Hồ sơ hệ thống "Thú hoang/vô chủ" mỗi chi nhánh (is_system, phone NULL, không sửa/xóa được, ẩn khỏi danh sách khách); action tạo nhanh pet "Thú hoang #n".
- (v5) Owners có `address` (tùy chọn, không thu CCCD). Hồ sơ pet tab Lịch sử ghi chi nhánh phát sinh ở mỗi sự kiện; bấm mở bệnh án theo quyền.
- Owners: danh sách có tìm theo tên/SĐT (chuẩn hóa SĐT VN sang E.164 bằng một value object Phone, dùng lại cho portal), phân trang, đếm số pet và lần đến gần nhất KHÔNG N+1 (withCount, subquery select). Unique (tenant_id, phone) báo lỗi thân thiện.
- Pets: thêm/sửa trong ngữ cảnh owner; gợi ý size từ breed.default_size + weight (class `PetSizeSuggester`, có unit test); cờ an toàn (dị ứng, ghi chú cảnh báo) hiển thị badge.
- Hồ sơ pet (S4) tab Tổng quan + Lịch sử dịch vụ (tab Y tế, Tiêm phòng để phase 6, hiển thị placeholder).
- API combobox tìm chủ nuôi (dùng cho form đặt lịch): trả tên, SĐT, số pet; giới hạn 10; index phù hợp.
- Soft delete; xóa owner có lịch hẹn tương lai thì chặn.
- Test: CRUD, phân quyền, cô lập tenant (dùng helper phase 2), không N+1 (đếm query).
```

### Task 5.2: Dịch vụ + thời lượng động

```prompt
Đọc docs/database/db-schema.md mục 4.1 và docs/design/design.md S9. Làm:
- CRUD services và service_duration_rules; gán nhân viên làm được dịch vụ (staff_services).
- (v5) `services.code` (unique theo tenant). **Combo**: `is_combo` + `service_combo_items` (combo có giá riêng, không lồng combo, dịch vụ con cùng tenant qua FK ghép). Đặt lịch/ghi lượt chọn combo như một dịch vụ; thời lượng combo = của combo (gợi ý tổng con), vẫn qua resolver. Không seed dịch vụ lưu chuồng (ngoài V1).
- `ServiceQuoteResolver::resolve(Service, Pet|PetProfile): Quote{durationMin, bufferMin, price, appliedRule, explanation}`. Thứ tự: breed → species+size → species → default. Một query, ORDER BY độ cụ thể, priority. `PetProfile` là DTO (species, breed, size) để portal dùng khi khách chưa có pet trong DB.
- explanation dạng "90 phút · áp dụng cho Chó cỡ L".
- Ô "Thử tính" ở màn chi tiết dịch vụ gọi resolver.
- Unit test đủ các nhánh resolve, quy tắc trùng độ cụ thể thì priority thắng, dịch vụ không có rule.
```

### Task 5.3: Lịch làm việc, ngày nghỉ, ngày đóng cửa

```prompt
Đọc docs/database/db-schema.md mục 4.2, 5.3 và docs/design/design.md S8. Làm:
- CRUD staff_working_hours (nhiều ca/ngày, effective_from/to; validate không chồng ca trong cùng ngày; (v2) mỗi ca thuộc một chi nhánh, không chồng giờ giữa 2 chi nhánh), staff_time_off (nhập giờ local tiệm, lưu UTC), tenant_closures ((v2) branch_id NULL = cả chuỗi).
- Khi thêm time off trùng lịch hẹn đang active: trả về danh sách lịch bị ảnh hưởng (không tự hủy).
- Class `TenantClock` (hoặc tương tự) gom việc convert local ↔ UTC theo tenant timezone, có `localDayRangeUtc(date)`. Không cộng offset cố định.
- UI lưới tuần như S8.
- Test có timezone DST (America/New_York): ca 09:00-17:00 ngày đổi giờ convert đúng.
```

### Task 5.4: Tính slot trống ⭐

> (v6) Đổi mô hình: slot online tính theo **hạn mức** = `floor(số người trực của nhóm × ratio)` (db-schema 4.11, `online_booking_rates`), không theo từng nhân viên rảnh. Lễ tân vẫn thấy số người trực và số lịch mỗi khung (cảnh báo khi vượt). Prompt dưới đã sửa.

```prompt
Đọc docs/database/db-schema.md mục 4.2, 4.11 (v6) và 5.3. Viết `AvailabilityService`:
- (v6) Theo cơ sở + nhóm (medical/grooming): đếm người trực mỗi khung 15 phút từ staff_working_hours (role vet → medical, groomer → grooming; trừ time_off, closures), lấy ratio từ online_booking_rates (ghi đè theo khung cụ thể nhất thắng), hạn mức = floor(trực × ratio); trừ số lịch online (source=portal) BLOCKING chồng giờ → còn chỗ online. Với lễ tân trả thêm: số người trực, tổng lịch chồng giờ (để cảnh báo), danh sách người trực rảnh nếu muốn gán người.
- Input: tenant, service quote (duration+buffer), ngày local D (hoặc khoảng ngày), staffId hoặc null = bất kỳ nhân viên bookable làm được dịch vụ.
- Output: danh sách slot (startsAt UTC, endsAt UTC, staffIds khả dụng), bước 15 phút, slot phải nằm trọn trong một ca (ends_at gồm buffer), loại trừ time_off, closures, appointments trạng thái BLOCKING, slot đã qua (so với now + lead time cấu hình).
- Hiệu năng: tải dữ liệu cho cả khoảng ngày trong số query cố định (không query theo từng slot hay từng nhân viên). Tính toán bằng interval arithmetic trong PHP (viết class Interval/IntervalSet nhỏ, có unit test).
- Endpoint JSON cho UI và (sau này) portal.
- Test: ca gãy, buffer cuối ca, time off nửa ngày, closure, lịch cancelled không chặn, DST, số query không tăng theo số nhân viên (assert query count). (v6) 3 bác sĩ × 1/3 → 1 chỗ online; 1 bác sĩ nghỉ → 0 chỗ (floor 2/3); ghi đè tỉ lệ theo ca; lịch lễ tân đặt không trừ chỗ online.
```

**Xong khi:** test xanh; với DemoSeeder, slot của người nghỉ chiều không có buổi chiều.

### Task 5.5: Đặt lịch chống đặt vượt hạn mức ⭐⭐ (điểm nhấn CV)

> (v6) Khóa đổi từ hàng `staff` sang hàng đếm `booking_capacity_locks(tenant, branch, service_group, date)`; lịch online không gán người; lịch lễ tân không tính hạn mức online, vượt số người trực chỉ cảnh báo. Các bước (4), "Bất kỳ nhân viên" và ADR dưới đây đọc theo ghi chú (v6) trong prompt.

```prompt
Đọc docs/database/db-schema.md mục 4.11 và 5.1 (bản v6) kỹ. Viết Action `BookAppointment` (dùng chung cho staff và portal):
- (v6) Bước khóa: INSERT IGNORE rồi `lockForUpdate` hàng booking_capacity_locks của (cơ sở, nhóm, ngày local); portal: đếm lịch online BLOCKING chồng giờ, ≥ floor(trực × ratio) → SlotTakenException; staff: không chặn theo hạn mức, trả cờ cảnh báo khi tổng lịch chồng giờ > số người trực; staff gán người cụ thể thì kiểm trùng lịch người đó (cảnh báo). Lịch tạo ra status confirmed. Bỏ vòng "thử lần lượt từng nhân viên". Phần dưới là bản v5, giữ các bước idempotency, pet, quota, event, test concurrency.
- Input DTO: ownerId, petId, serviceId, staffId|null, startsAt (UTC), source, idempotencyKey (UUID từ client), notes, reminder channels.
- Trong DB::transaction: (1) nếu idempotency key đã tồn tại → trả lại lịch cũ, không lỗi; (2) resolve quote, tính endsAt; (3) re-validate slot nằm trong ca/không time off/không closure; (4) `Staff::whereKey($id)->lockForUpdate()` rồi check overlap (`starts_at < :end AND ends_at > :start AND status IN BLOCKING`); (5) check pet không có lịch chồng; (6) quota: UPDATE tenant_usage atomic có điều kiện (`appointments_count < max`), INSERT ... ON DUPLICATE KEY cho tháng mới; affected = 0 → QuotaExceededException; (7) tạo appointment với snapshot duration/price; (8) dispatch event AppointmentBooked afterCommit.
- "Bất kỳ nhân viên": thử lần lượt từng nhân viên khả dụng, MỖI người một transaction riêng; SlotTakenException thì sang người kế. Không giữ nhiều lock cùng lúc. Thứ tự thử: ít lịch nhất trong ngày trước (cân tải).
- Deadlock/lock wait timeout: retry có giới hạn (DB::transaction attempts=3).
- Exception nghiệp vụ map sang HTTP 409 với payload gồm 3 slot gần nhất còn trống (dùng AvailabilityService).
- **Test concurrency thật**: script/Pest test bắn N=20 request song song vào cùng slot (dùng pcntl_fork hoặc Symfony Process chạy artisan command, mỗi process một connection MySQL riêng, không dùng RefreshDatabase transaction cho test này) → assert đúng 1 lịch được tạo và 19 nhận SlotTaken. Thêm test tương tự cho quota (còn 1 suất, 10 request) và idempotency (cùng key 10 lần → 1 lịch).
- Viết `docs/adr/0002-booking-capacity-lock.md` ((v6) lock một hàng đếm theo cơ sở × nhóm × ngày; so sánh với lock hàng staff, bảng slot UNIQUE, advisory lock GET_LOCK, SERIALIZABLE), ghi kết quả test concurrency (N request portal vào khung còn 1 chỗ online → đúng 1 thành công). Cập nhật docs/architecture.md mục Đặt lịch, có sequence diagram mermaid.
```

**Xong khi:** test concurrency chạy được trong CI và lặp 10 lần liền đều xanh; đọc ADR thấy rõ vì sao khóa một hàng đếm không gây gap lock.

### Task 5.6: Vòng đời lịch hẹn + dời lịch

```prompt
Đọc docs/design/design.md S3, F3. Làm:
- (v2) `CheckIn` gọi `CreateVisit` (task 5.8): check-in = tạo lượt khám có số thứ tự, trong cùng transaction. (v6) Lịch chưa gán người thì check-in chọn người làm (gợi ý preferred_staff_id). Lịch dừng ở `checked_in`, không có `completed`.
- (v6) State machine: confirmed → checked_in | cancelled | no_show. Action CheckIn, Cancel (lý do, có tùy chọn thông báo khách), MarkNoShow (lễ tân đánh dấu sớm). Command `appointments:mark-no-show` chạy cuối ngày theo giờ local từng tenant: lịch còn `confirmed` của ngày đó → `no_show` (idempotent, không phạt). Mỗi action kiểm quyền qua Policy (groomer chỉ thao tác lịch của mình).
- `RescheduleAppointment`: dùng cùng cơ chế lock như BookAppointment (tái sử dụng, không copy code); (v6) đổi ngày/nhóm thì lock 2 hàng booking_capacity_locks theo thứ tự khóa tăng dần để tránh deadlock; giá trên lịch chỉ là giá tham khảo.
- Hoàn tác hủy trong 5 giây (hủy rồi khôi phục = re-check slot).
- Bảng audit `appointment_events` (ai, khi nào, từ→đến) nếu thấy cần; nếu thêm thì cập nhật docs/database/db-schema.md trong repo.
- Test: chuyển trạng thái không hợp lệ bị chặn, reschedule đụng lịch → 409, hai reschedule chéo nhau đồng thời không deadlock.
```

### Task 5.7: UI lịch + form đặt lịch (S1, S2, S3)

```prompt
Đọc docs/design/design.md S1, S2, S3, F1 và mục 7. Làm UI bằng Vue 3 + Inertia:
- S1 Lịch: chế độ Ngày / Tuần / Theo nhân viên (mặc định), trục 07:00-21:00 bước 15', vùng ngoài ca sọc xám, time off, closure overlay, vạch "bây giờ", block theo màu staff + nền loại dịch vụ + buffer sọc chéo, badge an toàn của pet. Tự viết grid bằng CSS grid (không dùng FullCalendar) để kiểm soát; nếu thấy quá tốn thì dùng thư viện nhưng giải thích lựa chọn.
- Dữ liệu lịch theo ngày: một endpoint trả appointments + working hours + time off của khoảng UTC tương ứng ngày local; eager load đủ, không N+1.
- S2 drawer đặt lịch: combobox chủ nuôi, chọn pet, list dịch vụ với giá tham khảo/thời lượng đã resolve theo pet, nhân viên ((v6) "Chưa phân" mặc định), date picker + lưới slot có số lịch/số người trực, cảnh báo vượt nhưng vẫn lưu; cột "Chưa phân" ở S1; nhiều dòng pet × dịch vụ một lần lưu, thanh tóm tắt dính đáy. Sinh idempotency key (crypto.randomUUID) khi mở form, giữ nguyên khi retry. Nút submit chặn double click. Xử lý 409: toast, refresh slot, gợi ý 3 slot, không mất dữ liệu form. Quota hết: banner + disable.
- S3 drawer chi tiết: action theo status ((v6) không có nút Hoàn thành; khối y tế theo quyền), hủy có modal + toast Hoàn tác.
- Kéo-thả dời lịch (có thể làm sau cùng trong task, tách commit).
- Phím tắt N mở đặt lịch, `/` focus tìm kiếm. Accessibility theo mục 7.8.
- Test: Pest feature cho endpoint; thêm 1-2 test trình duyệt (Pest browser plugin hoặc Dusk) cho luồng F1.
```

**Xong khi:** luồng F1 làm được dưới 60 giây với dữ liệu demo; mở 2 tab cùng đặt 1 slot → tab sau nhận thông báo thân thiện.

### Task 5.8 (v2): Tiếp nhận khách + hàng đợi

```prompt
Đọc CLAUDE.md, docs/database/db-schema.md mục 1.3, 4.5 và docs/design/design.md S15, S16, F8. Làm:
- Action `CreateVisit` (dùng cho check-in lịch hẹn và khách vãng lai): bắt buộc owner + pet (khám/spa luôn cần hồ sơ). Cấp queue_no nguyên tử qua `branch_daily_counters` (`INSERT ... ON DUPLICATE KEY UPDATE last_queue_no = LAST_INSERT_ID(last_queue_no + 1)`), ngày theo giờ local tiệm. Một lịch hẹn chỉ tạo được một visit (UNIQUE appointment_id, idempotent khi bấm check-in 2 lần).
- S15: tìm theo SĐT (Phone value object), tạo nhanh owner + pet, nút "Thú hoang / vô chủ" dùng hồ sơ hệ thống của chi nhánh. (v5) Ghi lý do đến + tình trạng ban đầu (`visits.reason`); tick nhiều pet → nhiều visit.
- (v5) `CreateVisit` gắn visit vào hóa đơn `draft` đang mở của khách ở chi nhánh hôm nay, chưa có thì tạo (`OpenDraftInvoice`, trong cùng transaction, lock hàng owner để 2 lễ tân không mở 2 nháp cho cùng khách).
- S16 bảng hàng đợi theo chi nhánh hiện tại, tab Khám/Spa, thời gian chờ. "Nhận khách": `UPDATE visits SET status='in_progress', assigned_staff_id=? WHERE id=? AND status='waiting'`; affected = 0 → 409 "đã có người nhận". Cập nhật realtime bằng polling 10s (hoặc Reverb nếu muốn, ghi lý do).
- (v6) **Cấp cứu**: action `CreateEmergencyVisit` (chỉ cần loài + tình trạng, hồ sơ owner/pet hoàn thiện sau trong cùng lượt), `is_emergency` + `emergency_at`; hàng đợi sắp `is_emergency DESC, emergency_at`, queue_no không đổi. Lượt khám không có trạng thái `invoiced`.
- Test: 20 visit tạo song song cùng chi nhánh cùng ngày → queue_no 1..20 không trùng không lỗ (`->group('mysql')`); 2 bác sĩ nhận cùng khách → đúng 1 thành công; qua 0h giờ local thì số reset; cô lập tenant + chi nhánh; (v6) lượt cấp cứu lên đầu, các số khác giữ số.
```

**Xong khi:** luồng F8 tới bước "Nhận khách" chạy được với dữ liệu demo; test concurrency queue_no xanh 10 lần liền.

### Task 5.9 (v2): Lượt khám / lượt spa

```prompt
Đọc docs/database/db-schema.md mục 1.3, 4.5 và docs/design/design.md S17. Làm:
- S17: bác sĩ ghi chẩn đoán (medical_records gắn visit_id, chỉ vet sửa), thêm dịch vụ vào visit_items với unit_price snapshot từ ServiceQuoteResolver theo pet; groomer ghi dịch vụ + ghi chú spa. MVP chỉ có item_type=service; để sẵn interface cho thuốc/vật tư (phase 11).
- Action `CompleteVisit`: status done, thông báo in-app cho lễ tân chi nhánh "Số 042 chờ thanh toán" ((v6) lịch hẹn giữ `checked_in`; GĐ2 trừ kho thuốc + vật tư tại đây).
- (v6) Không sửa visit_items khi hóa đơn gắn lượt không còn draft. visit_items.unit_price = giá hiện tại lúc thêm. Đính kèm: ảnh lúc nhận/trả của spa (attachments trên visit, tùy chọn), kết quả xét nghiệm trên dòng bệnh án (task 6.2). File private, URL ký có hạn, test không tải được file của tenant khác.
- (v5) visit_items `is_billable` đồng bộ sang invoice_lines của hóa đơn nháp (khóa theo visit_item_id, `lockForUpdate` hóa đơn; hóa đơn không còn draft → từ chối). Combo = một dòng. Dòng mang pet_id.
- Test: ma trận quyền (lễ tân read-only), snapshot giá không đổi khi sửa giá dịch vụ, cô lập tenant; (v5) thêm/xóa dịch vụ thì dòng nháp đổi theo; 2 bác sĩ ghi dịch vụ cho 2 pet cùng hóa đơn song song không mất dòng (`->group('mysql')`).
```

### Task 5.10 (v2, v5): Hóa đơn + thanh toán ⭐

```prompt
Đọc docs/database/db-schema.md mục 1.3, 4.6 kỹ (bản v5) và docs/design/design.md S18, S19, S32, F8, F8b, F10. Làm:
- Trạng thái hóa đơn **draft → completed** (+ void). Một hóa đơn gom nhiều lượt khám (`visits.invoice_id`), dòng mang pet_id/visit_id. `OpenDraftInvoice` (đã gọi từ 5.8) và `CreateRetailInvoice` (kind=retail, owner_id tùy chọn, từ chối dòng service và sản phẩm requires_prescription; thuốc không kê đơn được bán. Sản phẩm thật có ở phase 11; ở MVP làm Action + test với product giả lập hoặc để TODO rõ ràng).
- Sửa nháp: thêm/bớt dòng sản phẩm, (v6) **dòng gõ tay** (line_type custom: tên + giá, không trừ kho), **không có giảm giá tay** (discount_amount = 0; khuyến mãi sau V1), thuế theo tenants.default_tax_rate.
- (v6) `VoidDraftInvoice` (receptionist, cashier): lý do bắt buộc, gỡ visits.invoice_id, thông báo in-app cho Owner, activity_logs; báo cáo ngày có mục "Hóa đơn đã hủy". Tiền tính bằng DECIMAL/brick-money, không float. Model guard ném exception khi sửa hóa đơn status ≠ draft.
- In nháp: PDF/HTML in có watermark "NHÁP / TẠM TÍNH", không có số hóa đơn; dùng chung mẫu in với hóa đơn hoàn tất.
- `CompleteInvoice` (một transaction): lockForUpdate hóa đơn (phải draft) → mọi visit của hóa đơn phải done → (v6) tiền mặt cần ca thu ngân đang mở (task 5.11) → ghi các dòng payments của lần thu (nhiều phương thức, tổng = total, mỗi dòng idempotency_key; tiền thối cho cash; cash gắn cash_session_id) → cấp code `{branch}-{yyyymmdd}-{seq}` từ branch_daily_counters → completed → activity_logs `invoice.completed`. (v6) Không đổi trạng thái visit. Không đặt cọc, không trả một phần, không công nợ.
- (v6) Sau completed: chỉ owner `AmendInvoice` (lý do bắt buộc; một transaction: void bản cũ + bản thay thế số mới `replaces_invoice_id` + chuyển visits.invoice_id và payments sang bản mới + chênh lệch thu thêm (payment) hoặc trả lại (refund)). Không có quy trình phê duyệt thêm. Lễ tân trả hàng sản phẩm qua task 11.5 (MVP chưa có sản phẩm).
- `activity_logs` (append-only, model guard) + màn S32 Nhật ký (owner, lọc theo người/loại/ngày).
- UI S18 thu ngân (danh sách nháp đang mở, dòng nhóm theo pet, nút Hoàn tất disabled khi còn lượt chưa xong) + S19 bán lẻ nhanh (phần UI S19 có thể để phase 11).
- Test: sửa hóa đơn completed bị chặn; hoàn tất khi còn visit chưa done bị chặn; bấm hoàn tất 2 lần / cùng idempotency key → 1 lần; 2 lễ tân hoàn tất cùng hóa đơn song song → 1 thành công (`->group('mysql')`); khách 2 pet → 1 hóa đơn, dòng đúng pet; nháp không chiếm số hóa đơn; hóa đơn visit thiếu owner bị từ chối ở Action và ở DB; retail không cần owner, chặn thuốc kê đơn; (v6) owner sửa hóa đơn hoàn tất → bản thay thế có đủ payments cũ + chênh lệch đúng; lễ tân gọi amend → 403; lễ tân hủy nháp không lý do → 422; thu tiền mặt khi chưa mở ca → từ chối; mỗi thao tác ghi đúng 1 dòng activity_logs.
- `docs/adr/0007-invoice-draft-completed-multi-visit.md`.
```

**Xong khi:** luồng F8 và F8b chạy hết tới hóa đơn Hoàn tất; F10 (bán lẻ không hồ sơ) pass ở tầng Action.

### Task 5.11 (v6): Ca thu ngân theo két

```prompt
Đọc docs/database/db-schema.md 1.3 (cash_drawers, cash_sessions, payments), 4.6 (ca thu ngân) và docs/design/design.md S35, S18. Làm:
- CRUD két theo cơ sở (Cài đặt › Cơ sở › Két). `OpenCashSession` (tiền đầu ca; mỗi két một ca mở, dựa UNIQUE open_key + bắt lỗi duplicate), `CloseCashSession` (expected_cash = đầu ca + Σ cash payment − Σ cash refund của ca; nhập counted_cash; chênh lệch ≠ 0 bắt buộc lý do; activity_logs khi lệch). Ca đóng rồi không nhận thêm khoản.
- Mọi payment/refund method cash bắt buộc cash_session_id của ca đang mở ở cơ sở hiện tại; chuyển khoản/QR/thẻ không cần nhưng vẫn vào báo cáo ca theo thời gian.
- Báo cáo ca: theo phương thức, theo người thu, hoàn tiền; in được. Owner thấy mọi ca, branch_manager xem ca cơ sở mình. Chip ca đang mở ở topbar.
- Test: 2 người mở ca cùng két song song → 1 thành công (`->group('mysql')`); thu tiền mặt khi chưa mở ca → từ chối; expected_cash đúng với nhiều người thu + hoàn tiền; đóng ca 2 lần → 1 lần; cô lập tenant + cơ sở.
```

**Xong khi:** mở ca → thu 2 hóa đơn (tiền mặt + QR) → đóng ca lệch 10.000 ₫ có lý do → báo cáo ca đúng số.

---

## Phase 6: Queue, nhắc hẹn, tiêm phòng, y tế ⭐

### Task 6.1: Hạ tầng queue + nhắc hẹn

```prompt
Đọc docs/database/db-schema.md (reminders) và mục 5.2 về job TenantAware. Làm:
- Cài Laravel Horizon (queue: high cho booking notifications, default, low cho reminders hàng loạt), service horizon trong docker compose, dashboard chỉ admin.
- (v6) Kênh khách đã chốt: **Zalo ZNS + SMS** (không email, không Telegram). Khi AppointmentBooked: tạo bản ghi reminders (24h và 2h trước) với dedupe_key `appt:{id}:{offset}:{channel}`, insertOrIgnore; mặc định gửi ZNS, ZNS lỗi/không có Zalo thì job tạo reminder SMS dự phòng (dedupe riêng, không gửi cả hai khi ZNS thành công).
- (v2) Thêm reminder `audience=branch_staff, channel=in_app` trước 1 ngày: thông báo lễ tân chi nhánh gọi xác nhận khách (S20, chuông topbar, trang "Cần gọi hôm nay"). (v6) `ZnsGateway` và `SmsGateway` (interface, driver log ở local/demo, driver thật cấu hình theo shop); cùng `SmsGateway` dùng cho OTP (task 7.1). Lịch bị hủy/dời: đánh dấu reminders pending là skipped, tạo mới cho giờ mới.
- Scheduler mỗi phút: chọn reminders pending đến hạn (`status, send_at` index), claim nguyên tử (UPDATE ... SET status='processing' WHERE id IN (...) AND status='pending' hoặc SELECT ... FOR UPDATE SKIP LOCKED), dispatch job gửi.
- Job gửi: TenantAware, idempotent (nếu đã sent thì thoát), retry với backoff [60, 300, 900], `failed()` ghi status failed + last_error. (v6) Kênh: Zalo ZNS (HTTP API theo SĐT + template id, nội dung tiếng Việt, giờ theo timezone tiệm, link quản lý lịch; lỗi rate limit thì release theo retry_after) và SMS (fallback). Kênh dùng interface `ReminderChannel`.
- Thử lại thủ công từ S3 ("SMS thất bại · Thử lại").
- Test: dedupe (chạy scheduler 2 lần không gửi 2 lần), hai worker claim cùng lúc không trùng, retry, hủy lịch thì không gửi, (v6) ZNS lỗi → đúng 1 SMS dự phòng, ZNS thành công → không SMS, Http::fake cho ZNS/SMS.
- Cập nhật docs/architecture.md mục Queue.
```

### Task 6.2: Hồ sơ y tế

```prompt
Đọc docs/database/db-schema.md (medical_records) và docs/design/design.md S4 tab Y tế, F4. Làm:
- (v6) Mỗi pet một **số hồ sơ** (`pets.record_no`, cấp nguyên tử khi tạo pet). Mỗi lần khám một **dòng chi tiết** `medical_records` gắn visit (không gắn appointment): chẩn đoán, điều trị, hướng dẫn chăm sóc, lịch tái khám, ghi chú nội bộ. Chỉ vet tạo; **chỉ người lập sửa dòng của mình trong 24h** (đã chốt, Policy + editable_until); sau 24h chỉ thêm `medical_record_addenda`. Mọi lần sửa ghi activity_logs trước/sau.
- (v6) Quyền xem: `medical_records.view_full` (vet, mọi cơ sở) thấy đầy đủ; `view_alerts` (lễ tân, groomer, quản lý chi nhánh) chỉ nhận cảnh báo + ngày khám (Resource riêng, không chỉ ẩn UI); thu ngân, kho không gọi được. Site (task 7.1) trả chẩn đoán, hướng dẫn, thuốc đã kê, sổ tiêm, tái khám, **không bao giờ** internal_notes.
- (v6) Đính kèm kết quả xét nghiệm / chẩn đoán hình ảnh (ảnh, PDF) vào dòng bệnh án qua `attachments` (private disk, URL ký có hạn, giới hạn loại + dung lượng).
- Timeline y tế ở hồ sơ pet.
- Test ma trận quyền (JSON của lễ tân không có khóa diagnosis/treatment/internal_notes), người khác sửa dòng → 403, quá 24h → 403, addendum được, file tenant khác → 404, cô lập tenant.
```

### Task 6.3: Phác đồ tiêm phòng / tẩy giun ⭐

```prompt
Đọc docs/database/db-schema.md mục 4.3 và docs/design/design.md S4 tab Tiêm phòng, S7, F4, F5. Làm:
- CRUD vaccines, care_protocols, care_protocol_steps (owner/vet).
- `CareScheduleCalculator` (thuần, unit test kỹ): từ protocol + lịch sử mũi của pet + ngày sinh → mũi kế tiếp và next_due_on (min_age_days, offset_days, recurring). Ngày theo DATE local tiệm.
- Action `RecordCareDose` (vet): ghi mũi, tính next_due_on, đánh dấu record cũ cùng vaccine superseded_at, trong transaction. UI hiển thị trước "Mũi kế tiếp dự kiến: dd/MM/yyyy". "Đánh dấu đã tiêm ở nơi khác".
- Scheduler hàng ngày (chạy theo giờ sáng của TỪNG tenant timezone, ví dụ 08:00 local; dùng Tenant::forEach hoặc query toàn cục có chủ đích): next_due_on trong 7 ngày tới hoặc quá hạn, superseded_at null → tạo reminders dedupe_key `care:{record_id}:{offset}:{channel}`. Dùng chunkById, không load hết vào bộ nhớ.
- S7 danh sách đến hạn: tab Quá hạn / 7 ngày / 30 ngày, filter, trạng thái nhắc, action Đặt lịch tiêm (mở S2 điền sẵn), Gửi nhắc lại.
- Tab Tiêm phòng ở hồ sơ pet với tiến độ phác đồ (3/4 mũi).
- Test: các phác đồ trong design.md mục 8 (Sushi mũi 2/4, Tôm quá hạn), chạy scheduler 2 lần không nhắc trùng, timezone.
```

---

## Phase 7: Site của tiệm (đặt lịch công khai)

### Task 7.1: Site của tiệm (S6, S14, S21; trước gọi là Portal)

```prompt
Đọc docs/design/design.md S6, S14, F2, mục 7.7 và docs/database/db-schema.md 5.2. Làm Site của shop tại `/{slug}` (v3, docs/design/design.md 11.4; trước gọi là portal), mobile-first, layout riêng. Route `/{slug}` đăng ký SAU mọi route trung tâm; slug cấm đã có từ task 4.3. Trang chủ shop (giới thiệu, chi nhánh, giờ mở cửa, dịch vụ & giá) công khai; khách đăng nhập OTP qua guard `customer` xong về `/{slug}/tai-khoan`, KHÔNG bao giờ redirect vào `/app` (test: khách đã đăng nhập mở /app/{slug} → về /login, không thấy dữ liệu; đăng nhập Site tiệm A thì ở Site tiệm B vẫn là khách; `intended` chỉ nhận đường dẫn thuộc Site của đúng tiệm):
- (v6) **5 bước**: (1) chọn cơ sở (ẩn nếu 1) + dịch vụ công khai; (2) xác minh: đã đăng nhập thì bỏ qua, chưa thì SĐT + OTP, SĐT mới thì nhập tên → tạo owner (khách tự đăng ký); (3) chọn pet đã có hoặc thêm mới (loài, giống, cân nặng → quote qua ServiceQuoteResolver), giá ghi "tham khảo"; (4) dải 14 ngày + slot theo hạn mức online (AvailabilityService), không chọn nhân viên, ô "người mong muốn"; (5) xác nhận → lịch `confirmed` ngay. Không gợi ý pet trước khi xác minh OTP.
- Submit dùng BookAppointment với source=portal. Không tạo trùng owner khi 2 request cùng SĐT đồng thời (dựa vào UNIQUE(tenant_id, phone) + bắt lỗi duplicate rồi đọc lại).
- Chống lạm dụng: rate limit theo IP + SĐT + tenant, honeypot hoặc Turnstile, giới hạn số lịch pending/SĐT.
- Trang thành công: mã lịch (public_id), file .ics, link quản lý; (v6) gửi tin xác nhận qua Zalo/SMS.
- S14 quản lý lịch qua signed URL theo public_id (không login): xem, hủy (tôn trọng chính sách hủy của tiệm, (v6) mặc định trước 24h, Owner cài), dời (về bước 4).
- (v2) S21: đăng nhập khách bằng SĐT + OTP SMS (bảng customer_otps: lưu hash, hết hạn 5 phút, sai 5 lần khóa, rate limit gửi theo SĐT + IP + tenant; guard riêng `customer`, không dùng bảng users). Trang "Của tôi": pet, lịch sắp tới, lịch sử khám/spa các chi nhánh ((v6) chẩn đoán, hướng dẫn chăm sóc, thuốc đã kê, tái khám; không có ghi chú nội bộ), lịch tiêm, hóa đơn đã hoàn thành của mình. Dời/hủy chặn ở server theo `tenants.reschedule_cutoff_hours` / `cancel_cutoff_hours`. Portal chọn chi nhánh trước khi đặt lịch. Toàn bộ màn khách làm **mobile-first** theo docs/design/design.md 3.4 (khung bottom nav) và 10.5 (OTP autofill, sticky CTA, PWA manifest); test trình duyệt ở viewport 390×844 cho luồng đăng nhập OTP → đặt lịch → xem lịch sử.
- Hiển thị giờ theo timezone tiệm, ghi chú nếu khác thiết bị.
- (v5) Trang **Điều khoản sử dụng** `/{slug}/dieu-khoan` (design S33): nội dung từ `tenants.terms_content`, Owner soạn ở Cài đặt › Site (có mẫu gợi ý, sanitize HTML/markdown), link ở footer và bước xác nhận S6. Không thêm bước đồng ý tại quầy.
- Test: không lộ dữ liệu (dò public_id, gợi ý pet theo SĐT), rate limit, URL ký sai/hết hạn, tenant suspended thì portal đóng.
```

---

## Phase 8: Billing & quota

### Task 8.1: Cashier + gói cước

> (v3) **Sau MVP.** Ở MVP gói và hạn do admin gán tay (task 4.5). Khi làm task này: webhook Stripe ghi vào `tenant_subscriptions` và cập nhật `tenants.plan_expires_at`, không tạo luồng hạn dùng thứ hai.

```prompt
Đọc docs/database/db-schema.md (plans, subscriptions, stripe_webhook_events, tenant_usage) và docs/design/design.md S12, F6. Làm:
- Laravel Cashier (Stripe), billable = Tenant. Trang Gói cước: so sánh gói, gói hiện tại, usage, nâng cấp qua Stripe Checkout, quản lý qua Billing Portal.
- Webhook idempotent: lưu event vào stripe_webhook_events (UNIQUE event_id) TRƯỚC khi xử lý, xử lý trong job queue; event trùng thì bỏ qua. Xử lý: subscription created/updated/deleted → đồng bộ tenants.plan_id; invoice.payment_failed → banner, quá hạn N ngày → status suspended (chặn đặt lịch mới, vẫn cho xem).
- Thống nhất mọi điểm kiểm tra giới hạn gói vào một class `PlanLimits` (max_staff, max_appointments_per_month, feature flags) dùng ở task 4.4 và 5.5.
- Banner quota ≥80% (vàng), 100% (đỏ) trên toàn app.
- Test: webhook trùng không xử lý 2 lần (gửi cùng payload 2 lần), chữ ký sai bị từ chối, downgrade khi đang vượt số nhân viên (quy tắc: không khóa người cũ, chặn mời thêm), dùng Stripe test mode + fixture payload, không gọi Stripe thật trong CI.
- `docs/adr/0003-billing-quota-atomic-counter.md`.
```

> (v3) **Tự đăng ký (sau MVP, tùy chọn):** nếu muốn mở cho chủ tiệm tự tạo shop, làm trang đăng ký gọi lại `ProvisionTenant` (trial + gói Starter), bật bằng cờ `TENANCY_SELF_SIGNUP`. Không tạo luồng tạo tenant thứ hai.

---

## Phase 9: Dashboard, hiệu năng, observability

### Task 9.1: Dashboard (S5)

```prompt
Đọc docs/design/design.md S5 và 10.2. (v2) Owner: doanh thu thực thu (từ payments) theo chi nhánh hoặc "Tất cả"; (v5) phần doanh thu của báo cáo S30 (tuần/tháng/khoảng tùy chọn, tách bán lẻ / dịch vụ, combo tính như một dịch vụ; giá vốn và lãi gộp thêm ở task 11.2); lễ tân: chỉ số liệu HÔM NAY của chi nhánh mình, ép ở server (query tự clamp khoảng ngày theo permission reports.view_today, kèm test sửa query string vẫn chỉ ra hôm nay). Làm dashboard: 4 KPI, lịch hôm nay nhóm theo nhân viên, tiêm phòng đến hạn (5 dòng), biểu đồ 14 ngày cột chồng theo loại dịch vụ, usage gói (owner), nhắc thất bại. Bản rút gọn cho non-owner (không doanh thu/usage). Mỗi widget là một query class riêng; tổng số query cố định (assert trong test); cache ngắn theo key tenant nếu query nặng; mọi khoảng ngày tính theo ngày local tiệm.
```

### Task 9.2: Hiệu năng & observability

```prompt
Rà soát hiệu năng và thêm observability:
- Chạy EXPLAIN cho các query nóng (lịch theo ngày, availability, overlap check, reminders đến hạn, tiêm đến hạn, tìm owner) với dữ liệu lớn: viết command `demo:seed-large` tạo 50 tenant × 20k lịch. Ghi kết quả + index đã thêm/đổi vào docs/performance.md.
- Bật Telescope (local) và Sentry (production, gắn tag tenant_id, không gửi PII như SĐT).
- Log có context tenant_id, request id.
- Health check endpoint (DB, Redis, queue lag từ Horizon).
- Load test booking bằng k6 (script trong `tools/k6`): đo throughput và p95 của POST đặt lịch, ghi số liệu vào docs/performance.md để đưa lên README.
```

---

## Phase 10: Deploy & trình bày cho CV

### Task 10.1: Deploy demo

```prompt
Chuẩn bị deploy production cho một VPS nhỏ (hoặc Laravel Cloud/Forge, chọn phương án rẻ và đơn giản nhất, giải thích ngắn):
- Dockerfile production multi-stage (composer --no-dev, npm build, opcache), docker-compose.prod (app php-fpm + nginx hoặc FrankenPHP, horizon, scheduler, mysql, redis), hoặc cấu hình tương đương cho nền tảng đã chọn.
- GitHub Actions deploy khi push tag/main sau khi CI xanh; chạy migrate --force; zero-downtime nếu nền tảng hỗ trợ.
- Backup MySQL hàng ngày.
- (v3) Bootstrap môi trường mới: sau `migrate --force` chạy `php artisan platform:admin-create` (một lần) để có admin đầu tiên; production không chạy DemoSeeder. Ghi các bước này vào docs/deploy.md.
- Môi trường demo: reset DemoSeeder mỗi đêm, tài khoản demo cho từng vai trò (hiển thị trên trang login demo), tắt gửi mail/Zalo/SMS thật (driver log) (log/mailpit).
```

### Task 10.2: README & ADR hoàn chỉnh

```prompt
Viết lại README cho nhà tuyển dụng đọc trong 2 phút: demo link + tài khoản demo theo vai trò, ảnh chụp màn hình (lịch, đặt lịch, hồ sơ pet, portal mobile), sơ đồ kiến trúc (mermaid), mục "Điểm kỹ thuật đáng chú ý" (multi-tenant fail closed + FK ghép; chống double-booking + test concurrency; timezone/DST; reminders idempotent; webhook idempotent; quota atomic) mỗi mục 2-3 dòng + link code/ADR, số liệu (coverage, PHPStan level, p95 đặt lịch từ docs/performance.md), cách chạy local. Rà docs/adr/ đủ: 0001 single DB, 0002 double-booking, 0003 billing/quota, 0004 UTC + TIME local, 0005 reminders dedupe/claim, 0006 chọn Inertia thay vì SPA + API.
```

**Xong khi:** người lạ mở README hiểu dự án làm gì, vào được demo, và thấy ngay 3-4 điểm kỹ thuật khó.

---

## Phase 11 (v2, Giai đoạn 2): Kho, thuốc, bán hàng, điều chuyển

### Task 11.1: Sản phẩm, nhà cung cấp, phiếu nhập, giá vốn

```prompt
Đọc CLAUDE.md, docs/database/db-schema.md mục 1.4, 4.7, 5.4, 5.5 và docs/design/design.md S22, S24. Làm:
- Migration + model cho suppliers, product_categories, products, service_consumables, stock_items, stock_batches, stock_movements, purchase_receipts(+lines), stocktakes(+lines). FK ghép như mọi bảng tenant.
- (v6, đã chốt) Giá bán (`products.sale_price`) là một giá hiện hành, không theo lô/giá vốn. **Xuất FIFO theo lô nhập** (`ORDER BY received_at, id`, bỏ lô quá hạn); giá vốn = `stock_batches.unit_cost` của lô xuất, ghi vào `stock_movements.unit_cost` (NULL nếu lô chưa điền giá; báo cáo lấy giá lô khi đã điền). Không dùng `avg_cost`. Chỉ owner xem giá vốn (`inventory.view_cost`).
- (v6) **Phiếu nhập 2 bước**: `PostPurchaseReceipt` (nhân viên kho, `inventory.receive`): sản phẩm, số lượng, lô, hạn, chọn cơ sở nhận; tạo lô (unit_cost NULL) + movement purchase + cộng tồn ngay, status `posted`; request/response của kho không có trường giá (test). `CostPurchaseReceipt` (chỉ owner): điền unit_cost từng dòng → cập nhật lô + total_cost, status `costed`, activity_logs. Màn "Phiếu chờ điền giá".
- (v6) **Phiếu hao hụt / điều chỉnh** `stock_adjustments`: kho lập (lý do: chuột cắn, vỡ, hết hạn, mất…) → `pending_approval` → owner/branch_manager duyệt thì StockLedger ghi `write_off`/`adjustment`, từ chối thì không đổi tồn. Kiểm kê chênh lệch cũng qua duyệt.
- Service duy nhất `StockLedger` cho mọi thay đổi tồn: lockForUpdate stock_items (product_id tăng dần), ghi movement append-only (giá vốn xuất = giá lô, ghi vào `stock_movements.unit_cost`), cập nhật on_hand. Không chỗ nào khác được UPDATE on_hand (arch test).
- Phiếu đã ghi sổ không sửa số lượng (sai thì lập phiếu điều chỉnh). Đổi giá bán ghi activity_logs `price.changed`.
- (v5) `service_consumables` có cột `size` (NULL = mọi cỡ), là định mức gợi ý.
- Kiểm kê: post chênh lệch thành movement stocktake.
- Giá vốn chỉ owner: `ProductResource` không có cost; `ProductCostResource` chỉ khi permission inventory.view_cost. Test dataset role × endpoint: JSON của lễ tân/bác sĩ không chứa khóa nào có "cost".
- S22 Kho (tồn, lô & HSD, sổ kho), S24 phiếu nhập + kiểm kê. Job hằng ngày cảnh báo tồn thấp + lô cận date (ngưỡng theo (v4) bên dưới).
- (v4) `product_units` (thùng = 24 gói): nhập theo thùng quy ra gói ở Action, kho chỉ lưu đơn vị nhỏ nhất. `is_batch_tracked` mặc định true cho mọi hàng có HSD; hàng không theo lô có 1 lô ngầm `DEFAULT`. HSD tháng/năm → ngày cuối tháng. `products.expiry_alert_days` cho ngưỡng cận date riêng; S22 thêm lọc Cận date / Quá hạn; `write_off` hủy hàng hết hạn (có lý do; (v6) qua phiếu `stock_adjustments`, kho tạo, quản lý chi nhánh/owner duyệt). (v6) Hàng lỗi: owner hủy / trả NCC / chuyển sang mã sản phẩm riêng (movement adjustment ra mã cũ, vào mã mới, có lý do). Kiểm kê theo lô.
- (v4) Cảnh báo kho cho mọi loại hàng: ngưỡng sắp hết `stock_items.reorder_level` → `products.default_reorder_level`; ngưỡng cận date sản phẩm → nhóm → 30 ngày. Bảng `stock_alerts` (một cảnh báo mở mỗi sự việc, `open_key` unique). Sắp hết/hết hàng kiểm ngay sau xuất kho (job sau commit), cận date/quá hạn quét hằng ngày 07:00 giờ tiệm. Kênh: chuông in-app (Owner, quản lý, lễ tân chi nhánh), card "Kho cần xử lý" ở S5, tab Cảnh báo S22, email tóm tắt hằng ngày cho Owner. Form cài ngưỡng ở S22.
- Test: tổng lô = on_hand sau mọi thao tác; giá vốn xuất đúng theo lô FIFO qua nhiều lần nhập giá khác nhau (vd lô 180.000 rồi lô 190.000, bán 1 → giá vốn 180.000; bán tiếp vượt lô 1 → 2 movement 2 giá); giá bán không đổi khi nhập lô giá mới; (v6) JSON trả cho nhân viên kho không có khóa cost/unit_cost; kho gọi CostPurchaseReceipt → 403; phiếu hao hụt chưa duyệt không đổi tồn, duyệt 2 lần song song → trừ 1 lần (`->group('mysql')`); cô lập tenant. (v4) Nhập 2 thùng → 48 gói, giá vốn/gói đúng; job cận date dùng ngưỡng sản phẩm, không có thì theo nhóm; bán xuống dưới ngưỡng → đúng 1 cảnh báo mở, chạy job 2 lần không tạo trùng; nhập bù vượt ngưỡng → resolved; cảnh báo chi nhánh A không hiện ở chi nhánh B.
- `docs/adr/0008-stock-ledger-cost-method.md` ((v6) FIFO theo lô nhập, giá vốn = giá lô, phiếu nhập 2 bước; so sánh FEFO và bình quân gia quyền).
```

### Task 11.2: Trừ kho khi kê thuốc / dùng vật tư / bán lẻ ⭐

```prompt
Đọc docs/database/db-schema.md mục 4.7, 5.4. Làm:
- visit_items thêm medication/consumable: (v6, đã chốt) lúc kê chỉ **kiểm còn đủ**, không giữ hàng; **trừ kho khi `CompleteVisit`** qua StockLedger (FIFO lô nhập, bỏ lô hết hạn), thiếu thì từ chối hoàn tất và báo.
- (v5) Vật tư **theo thực tế**: thêm dịch vụ (hoặc combo → từng dịch vụ con) thì điền sẵn dòng consumable (`is_billable=false`, `suggested_quantity`) theo định mức đúng cỡ pet → NULL; nhân viên sửa số lượng, đổi vật tư (`replaces_product_id`), xóa. Dòng khớp dị ứng của pet hiện cảnh báo. `CompleteVisit` trừ kho theo **số thực tế** (movement visit_usage), không theo định mức.
- (v6) **FIFO theo lô nhập** dùng chung cho **mọi** loại hàng (thay FEFO v4), thứ tự `received_at, id`, bỏ lô quá hạn. Một dòng lấy từ nhiều lô → mỗi lô một movement, source = dòng hóa đơn; trả hàng hoàn đúng lô. Bán theo thùng trừ `quantity × unit_factor`. POS hiện lô sẽ xuất; đổi lô tay cần `inventory.override_batch` + lý do.
- (v4, v6) Test: lô L1 (nhập trước, 24 gói) + L2 (nhập sau, 24 gói, HSD gần hơn L1), bán 30 gói → L1 = 0, L2 = 18 (FIFO, không theo hạn); lô quá hạn không bao giờ bị chọn; bán 1 thùng khi chỉ còn 20 gói → từ chối.
- Hóa đơn: invoice_lines.unit_cost = giá vốn xuất (từ movement); (v6) mọi dòng product (hóa đơn retail **và** hàng mua thêm trên hóa đơn lượt khám) trừ kho khi **completed**. Hoàn tất UI S19 bán lẻ nhanh (mã vạch, không bắt buộc SĐT, bán được thuốc không kê đơn, chặn thuốc kê đơn).
- (v5) Báo cáo S30 (owner): **Lãi gộp = Doanh thu − Giá vốn hàng bán − Giá vốn vật tư tiêu hao**; doanh thu trước thuế, thuế riêng; giá vốn lấy từ stock_movements (sale + visit_usage − return); tách bán lẻ / dịch vụ; tuần, tháng, khoảng tùy chọn; từng chi nhánh hoặc cả shop. Test: lượt spa có vật tư không tính tiền vẫn ra giá vốn; báo cáo tổng = tổng các chi nhánh.
- Test concurrency (`->group('mysql')`): còn 1 hộp, 10 request bán song song → đúng 1 thành công; kê nhiều thuốc trong 2 visit song song theo thứ tự ngược nhau không deadlock.
```

### Task 11.3: Tra tồn liên chi nhánh + điều chuyển + giữ hàng ⭐

```prompt
Đọc docs/database/db-schema.md mục 4.7 (luồng 5 bước) và docs/design/design.md S23, F9. Làm:
- Endpoint tra tồn một sản phẩm ở mọi chi nhánh của tenant (available = on_hand − reserved, HSD gần nhất, không giá vốn). Gọi từ S17 khi kê thuốc hết hàng.
- stock_transfers(+lines), stock_reservations. Actions: RequestTransfer (tạo reservation transfer ở chi nhánh nguồn, gắn khách), ApproveTransfer, ShipTransfer (chọn lô FIFO, shipper, movement transfer_out), ReceiveTransfer (số lượng thực nhận, lệch phải ghi chú, movement transfer_in giữ số lô/HSD, tạo reservation customer_hold 48h ở chi nhánh nhận, thông báo lễ tân), CancelTransfer (nhả reservation).
- Chuyển trạng thái bằng UPDATE có điều kiện status (nhận 2 lần không cộng kho 2 lần). Job nhả reservation hết hạn.
- Chỉ chi nhánh nguồn duyệt/xuất, chỉ chi nhánh nhận xác nhận nhận (Policy theo staff_branches).
- Test: CN2 không bán được phần đang giữ; nhận phiếu 2 lần đồng thời → 1 lần; hết hạn giữ thì available tăng lại; cô lập tenant. (v6) Chuyển hàng đi lô nhập trước (FIFO), lô nhận giữ nguyên số lô + HSD + giá nhập.
```

### Task 11.4: Portal trưng bày sản phẩm

```prompt
Đọc docs/design/design.md S25. Trang sản phẩm công khai (products.is_public) trong portal: ảnh, giá bán, còn hàng theo chi nhánh (chỉ "Còn hàng"/"Hết hàng", không lộ số lượng). Không có giỏ hàng. Cache theo key tenant, xóa cache khi đổi giá/tồn.
```

### Task 11.5 (v5, v6): Trả hàng (tích điểm: sau V1)

```prompt
Đọc docs/database/db-schema.md 1.2 (owners), 1.4 (sales_returns, loyalty_transactions) và docs/design/design.md S31, S34. Làm:
- `CreateSalesReturn` / `PostSalesReturn` (v6: lễ tân/thu ngân làm): từ hóa đơn completed, chỉ dòng **sản phẩm**, chọn số lượng (lock hóa đơn, không vượt số đã bán trừ số đã trả); hạn `tenants.return_window_days` (mặc định 7), quá hạn chỉ owner. Mỗi dòng `condition`: intact → movement `return` hoàn đúng lô theo movement gốc, giá vốn theo unit_cost gốc; defective → danh sách "Hàng lỗi chờ xử lý" (không cộng on_hand), owner xử lý; expired → write_off. Hoàn tiền mặt (cần ca thu ngân mở) hoặc chuyển khoản = payments refund, trừ doanh thu ngày trả; activity_logs. Trả nhà cung cấp chỉ là lựa chọn xử lý hàng lỗi, chứng từ NCC ngoài V1.
- (v6) Thành viên / tích điểm / khuyến mãi theo chiến dịch: **sau V1**, không làm.
- Test: trả 2 lần song song không vượt số bán (`->group('mysql')`); hàng nguyên về đúng lô; hàng lỗi không tăng on_hand; quá hạn lễ tân → 403, owner được; báo cáo lãi gộp trừ phần trả; cô lập tenant.
```

---

## Phase 12 (v2, Giai đoạn 3): Ca làm, chấm công, lương

### Task 12.1: Mẫu ca, xếp ca, phủ ca

```prompt
Đọc docs/database/db-schema.md mục 1.5, 4.8 và docs/design/design.md S26. Làm shift_templates, coverage_requirements, shift_assignments, public_holidays (seed ngày lễ VN). S26 lưới tuần kéo-thả, thanh phủ ca cảnh báo thiếu người theo position. Không cho 1 nhân viên 2 ca chồng giờ (lock hàng staff). AvailabilityService: ngày có shift_assignments thì dùng ca được xếp thay cho staff_working_hours (test cả 2 nguồn). (v5) Thêm `shift_availabilities`: màn "Đăng ký ca của tôi" cho nhân viên (chỉ thấy của mình), S26 hiện ô đã đăng ký, Owner nhận (tạo shift_assignment trong cùng transaction, lock hàng staff) hoặc từ chối; `staff_shift_rates` snapshot vào shift_assignments.rate. Mẫu ca có ngày hoạt động; nhu cầu nhân sự cơ bản = tổng min_count theo ngày/ca (thuật toán nâng cao **[Cần nghiên cứu]**, không làm). Đổi ca **[Cần nghiên cứu]**. Ca qua nửa đêm và DST có test.
```

### Task 12.2 (v5): Chấm công trên CMS (+ kiosk cho người không tài khoản)

```prompt
Đọc docs/database/db-schema.md mục 1.5 (attendance_*), 4.8 và docs/design/design.md S27, S28. Làm:
- (v5) Cách chính: nhân viên có tài khoản bấm "Chấm công vào/ra" trong CMS (`method = cms`), gắn shift_assignment gần nhất trong ngày, (v6) **branch_id lấy từ ca**; không có ca → nhân viên chọn cơ sở, ghi `is_off_schedule`, status `pending_approval`, Owner duyệt mới tính công. Ghi IP + user agent; kiểm `branches.allowed_ips` chỉ khi Owner bật. Chấm 2 lần liền trong 1 phút = bỏ qua (idempotent).
- Kiosk cho nhân viên không tài khoản (bảo vệ, lao công): Owner đăng ký thiết bị (token, lưu sha256), route kiosk riêng, mã NV + PIN (pin_hash, rate limit, khóa 15 phút sau 5 lần sai).
- Quên chấm công, xác minh làm đúng ca, quy tắc giờ làm/làm thêm: **[Cần nghiên cứu]**. Tạm thời: quên chấm ra → job auto_closed tại giờ kết thúc ca, vào danh sách cần duyệt. S28 bảng công, Owner sửa giờ có lý do + activity_logs.
- Test: chấm hộ người khác qua CMS bị chặn (chỉ chấm cho chính mình); bật kiểm IP thì IP lạ bị từ chối; kiosk thiết bị lạ/PIN sai bị khóa; auto close đúng giờ local; cô lập tenant.
```

### Task 12.3 (v5): Lương theo ca + trang khóa cấp 2 ⭐

```prompt
Đọc docs/database/db-schema.md mục 1.5, 4.8, 5.5 và docs/design/design.md S29. Làm:
- (v5) Lương V1 **theo đơn giá ca**: `staff_shift_rates` (nhân viên × mẫu ca, có hiệu lực từ ngày), payroll_periods theo **tuần hoặc tháng** (starts_on/ends_on), payroll_entries.shift_count + shift_pay = Σ rate các ca có chấm công hợp lệ, breakdown JSON từng ca. Đây là **số tiền dự kiến** cho Owner kiểm tra.
- Lương cố định tháng, phụ cấp, hoa hồng, thưởng/phạt/khấu trừ, tăng ca/đêm/lễ, số ca tối thiểu: **[Cần nghiên cứu]**, KHÔNG làm (salary_profiles, payroll_adjustments chỉ để migration sẵn nếu cần).
- `LockPayrollPeriod`: khóa kỳ; sau đó sửa attendance thuộc kỳ → exception.
- Trang lương: permission `payroll.view` (Owner mặc định, Owner gán được cho người được ủy quyền), kiểm ở backend; middleware `RequireSensitiveUnlock:payroll`: nhập lại mật khẩu, mở 10 phút (session unlocked_until, không tự gia hạn), header Cache-Control no-store, log mọi lần mở khóa/xem/xuất. Đồng hồ đếm ngược + "Khóa ngay" ở UI. Màn cài đơn giá ca theo nhân viên.
- Test: nhân viên thường gọi thẳng URL/API lương → 403/404, JSON không có khóa lương của người khác; người được ủy quyền vào được; chưa mở khóa → redirect nhập mật khẩu; quá 10 phút → khóa lại; sai mật khẩu nhiều lần bị throttle; ca tối đơn giá khác ca sáng tính đúng; kỳ đã khóa không tính lại; log được ghi.
- `docs/adr/0009-sensitive-area-reauth.md`.
```

---

## Phụ lục: Prompt review dùng sau mỗi task

```prompt
Review diff của branch hiện tại so với main như một senior reviewer, tập trung: rò rỉ dữ liệu giữa tenant (query thiếu scope, exists không kèm tenant_id, route binding, job thiếu context, cache key), race condition, N+1 / thiếu index, timezone (so sánh/format theo UTC hay local), idempotency, thiếu test. Liệt kê vấn đề theo mức độ, sửa những cái chắc chắn, hỏi lại những cái cần quyết định.
```
