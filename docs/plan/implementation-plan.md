# Kế hoạch triển khai: Pet Spa & Vet Booking SaaS (PawBook)

Repo: https://github.com/trancn-dev/pet-spa-booking-saas · Laravel 12 · MySQL 8 · một DB + `tenant_id`
Nguồn: `project-brief.md`, `design/db-schema.md`, `design/design.md` (thư mục project).

---

## 0. Cách dùng file này

1. **Một task = một phiên Claude Code = một branch/PR.** Copy nguyên khối `prompt` của task vào Claude Code. Không gộp nhiều task vào một phiên: context loãng, PR khó review.
2. Với task có nhãn ⭐ (lõi, dễ sai): bật **plan mode** trước (`Shift+Tab` hai lần), đọc kế hoạch Claude đưa ra, sửa rồi mới cho chạy.
3. Mọi prompt đều giả định repo đã có `CLAUDE.md` và `docs/` (task 1.3). Nhờ vậy prompt ngắn mà vẫn đủ ngữ cảnh. **Làm task 1.3 sớm**, kể cả khi phase 1 đã được làm ở chỗ khác.
4. Sau mỗi task, tự kiểm theo mục **"Xong khi"**. Chưa đạt thì yêu cầu Claude sửa tiếp trong cùng phiên, đừng merge.
5. Thứ tự phase là thứ tự phụ thuộc. Trong cùng phase, task đánh số theo thứ tự nên làm.
6. Ưu tiên **làm sâu** phase 2, 5, 6 (multi-tenant, đặt lịch, queue). Đó là phần nhà tuyển dụng senior đọc. Phase 9 (UI dashboard, báo cáo) có thể cắt bớt.

> ⚠️ **Phase 1 có thể đã được làm.** Một thread khác trong project đang init repo (Laravel + Docker + nền tenant + migration) trên nhánh `claude/init-laravel`. Trước khi chạy phase 1-3, kiểm tra nhánh đó / PR của nó. Nếu đã merge: bỏ qua task tương ứng, chỉ chạy prompt "kiểm tra & bổ sung" ở cuối mỗi phase để Claude đối chiếu với tiêu chí "Xong khi".

### Lộ trình gợi ý (làm buổi tối/cuối tuần)

| Tuần | Phase |
|---|---|
| 1 | 1. Init + tooling |
| 2 | 2. Nền multi-tenant · 3. Migration + seed |
| 3 | 4. Auth, workspace, phân quyền |
| 4 | 5a. Khách hàng, pet, dịch vụ, lịch làm việc |
| 5-6 | 5b. ⭐ Availability + đặt lịch + test concurrency + UI lịch |
| 7 | 6. Queue, nhắc hẹn, tiêm phòng, hồ sơ y tế |
| 8 | 7. Portal công khai |
| 9 | 8. Billing + quota |
| 10 | 9. Dashboard, hiệu năng, observability · 10. Deploy, README, ADR |

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

> Trước khi chạy: copy `design/db-schema.md`, `design/design.md`, `design/erd.png`, `project-brief.md` từ project vào `docs/` của repo (Claude Code trên máy bạn không đọc được thư mục project).

```prompt
Thư mục docs/ có db-schema.md, design.md, erd.png, project-brief.md. Tạo các file sau:

1. `CLAUDE.md` ở gốc repo (tiếng Việt, ngắn, dạng gạch đầu dòng) gồm:
   - Mô tả dự án 3 dòng, link tới docs/.
   - Lệnh: chạy test, lint, phpstan, migrate fresh (theo Makefile).
   - Quy ước bắt buộc (lấy từ docs/db-schema.md): mọi bảng tenant có tenant_id + UNIQUE(tenant_id,id); FK giữa 2 bảng tenant là FK ghép (tenant_id, x_id) → (tenant_id, id); index ghép bắt đầu bằng tenant_id; DATETIME lưu UTC, ca làm TIME giờ local tiệm; public_id ULID cho thứ lộ ra URL công khai; soft delete cho owners/pets/services/staff.
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
Đọc CLAUDE.md, docs/db-schema.md mục 1.1 (tenants) và 5.2. Làm nền multi-tenant (một DB + tenant_id), KHÔNG dùng stancl/tenancy:

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
Đọc CLAUDE.md và docs/db-schema.md mục 5.2. Repo đã có nền multi-tenant. Đối chiếu code hiện tại với các yêu cầu sau và liệt kê cái nào đã đạt / thiếu, rồi bổ sung phần thiếu kèm test: global scope fail closed khi thiếu context; auto gán tenant_id; runAs/forEach thay cho withoutGlobalScope; middleware resolve tenant theo slug + check membership; Rule exists theo tenant; route binding 404 chéo tenant; chặn withoutGlobalScopes bằng arch/PHPStan; job TenantAware; cache key prefix tenant; Model::shouldBeStrict; test chạy trên MySQL; CI xanh.
```

---

## Phase 3: Migration, model, seed

### Task 3.1: Bảng dùng chung + migration toàn bộ schema

```prompt
Đọc CLAUDE.md và docs/db-schema.md (toàn bộ). Tạo migration + model + factory cho TẤT CẢ bảng trong mục 1.1 và 1.2 (trừ bảng của Cashier và spatie: để phase sau).

Yêu cầu:
- Đúng kiểu cột, enum, index, unique như tài liệu. DATETIME (không TIMESTAMP) cho mọi thời điểm nghiệp vụ; `created_at/updated_at` cũng dùng `datetime` (viết macro Blueprint `datetimesUtc()` nếu cần).
- Mọi bảng tenant: `UNIQUE(tenant_id, id)` và FK ghép tới bảng tenant khác: `$table->foreign(['tenant_id','owner_id'])->references(['tenant_id','id'])->on('owners')`. FK tới bảng dùng chung (species, breeds, users) là FK đơn.
- Thứ tự migration đúng phụ thuộc. Chú ý FK ghép với cột nullable (vd medical_records.appointment_id) vẫn hợp lệ.
- Model: dùng BelongsToTenant cho bảng tenant, HasUlids/public_id cho owners & appointments (route key = public_id ở portal), SoftDeletes theo tài liệu, `casts()` đầy đủ (enum PHP backed cho status/size/category/channel… đặt ở app/Enums), quan hệ đầy đủ.
- Hằng `Appointment::BLOCKING_STATUSES` = pending, confirmed, checked_in.
- Factory cho mọi model, factory tenant-aware (tự dùng tenant hiện tại hoặc tạo mới cùng tenant cho quan hệ con: pet và owner của nó phải cùng tenant).
- Test: migrate:fresh chạy được; test DB-level chứng minh FK ghép chặn gắn pet tenant A vào owner tenant B (insert thẳng bằng DB::table, kỳ vọng QueryException).
```

**Xong khi:** `make fresh` sạch; test FK ghép xanh; `php artisan model:show Appointment` thấy đủ quan hệ và cast.

### Task 3.2: Seeder dữ liệu demo

```prompt
Đọc docs/design.md mục 8. Viết seeder:
- `ReferenceDataSeeder` (chạy ở mọi môi trường, idempotent bằng upsert): plans (Starter 2 nhân viên/150 lịch, Pro 5/500, Clinic không giới hạn = null), species (dog, cat), breeds phổ biến VN có default_size.
- `DemoSeeder` (chỉ local/demo): tiệm "Paw House Spa & Vet" slug paw-house, tz Asia/Ho_Chi_Minh; 5 nhân viên đúng tên/vai trò/màu (role gán sau khi có phase 4: để TODO); ca làm 08:00-20:00, một người ca gãy, một người nghỉ chiều nay; dịch vụ + quy tắc thời lượng S/M/L; chủ nuôi & pet như bảng (dị ứng, "sợ máy sấy"); phác đồ vaccine 7 bệnh + tẩy giun 90 ngày; pet_care_records để Sushi đang mũi 2/4 và Tôm quá hạn tẩy giun 12 ngày; khoảng 14 lịch hôm nay đủ các status (tính theo ngày hiện tại, giờ local tiệm → UTC).
- Thêm tenant thứ hai nhỏ ("Meow Clinic") để demo cô lập dữ liệu.
- Seeder phải chạy lại được (`migrate:fresh --seed`) và không phụ thuộc thứ tự ngẫu nhiên.
```

**Xong khi:** `make fresh` ra dữ liệu đúng như design.md; mở DB thấy giờ lưu UTC (08:00 VN = 01:00).

---

## Phase 4: Auth, workspace, phân quyền

### Task 4.1: Roles & permissions (spatie teams mode)

```prompt
Đọc CLAUDE.md, docs/db-schema.md (roles/permissions) và docs/design.md mục 2. Cài spatie/laravel-permission với teams mode, team_id = tenant_id:
- Roles dùng chung (team_id NULL): owner, vet, groomer, receptionist. Permissions chi tiết theo module (appointments.*, owners.*, pets.*, medical_records.view/edit, vaccinations.record, services.manage, staff.manage, billing.manage, reports.view). Seeder phân quyền theo bảng persona (lễ tân chỉ xem medical_records; chỉ vet sửa; groomer chỉ xem lịch của mình).
- Middleware ResolveTenant gọi `setPermissionsTeamId(tenant()->id)`; clear cache permission đúng cách khi đổi team.
- Gán role theo staff (một user có thể owner ở tiệm A, groomer ở tiệm B).
- Policy cho từng model tenant; Gate::before cho owner (trừ medical_records.edit nếu owner không phải vet: owner Trần Minh Khoa là owner + vet, nên hỗ trợ nhiều role).
- Chia sẻ quyền xuống Inertia (`can` props) để UI disable nút kèm tooltip, không ẩn dữ liệu được phép xem.
- Cập nhật DemoSeeder gán role. Test: ma trận vai trò × hành động (Pest dataset), và user là owner tiệm A nhưng groomer tiệm B thì quyền đúng theo tiệm đang mở.
```

**Xong khi:** test ma trận xanh; đổi workspace thì quyền đổi theo.

### Task 4.2: Đăng ký, tạo workspace, switcher

```prompt
Đọc docs/design.md S13, F7 và mục 7.1. Làm:
- Sau đăng ký (starter kit), nếu user chưa thuộc tenant nào → onboarding tạo workspace: tên tiệm, slug (gợi ý từ tên, check trùng realtime, danh sách slug cấm: app, api, admin, www…), loại hình, timezone (mặc định Asia/Ho_Chi_Minh), giờ mở cửa. Action `CreateTenant` trong transaction: tạo tenant, staff cho user, gán role owner, gán plan Starter + trial 14 ngày, tạo dịch vụ mẫu nếu tick.
- Workspace switcher ở topbar (list tenant của user), route `/app/{slug}`; đăng nhập xong redirect về last_tenant_id.
- Layout staff app theo design.md 3.4 (sidebar, topbar có tên workspace luôn hiển thị). Dùng token màu mục 3.1 làm CSS variables + Tailwind theme, có dark mode.
- Test: tạo tenant, slug trùng/cấm bị từ chối, user không thuộc tenant truy cập /app/{slug} khác → 404 (không 403, tránh lộ slug tồn tại).
```

**Xong khi:** đăng ký mới → onboarding → vào app; user có 2 tiệm chuyển qua lại được.

### Task 4.3: Mời nhân viên

```prompt
Đọc docs/db-schema.md (invitations, staff) và docs/design.md S11, F7. Làm luồng mời:
- Owner mời email + role. Token ngẫu nhiên 32 byte gửi qua mail (queued), DB chỉ lưu sha256 (token_hash). Hết hạn 7 ngày. Gửi lại = token mới, vô hiệu token cũ. Thu hồi được.
- Link nhận lời mời: nếu chưa đăng nhập → đăng nhập/đăng ký với email đó rồi quay lại; email phải khớp. Accept trong transaction, idempotent (bấm 2 lần không tạo 2 staff).
- Giới hạn gói max_staff: kiểm tra khi mời VÀ khi accept (đếm staff active + lời mời còn hạn), có lock để 2 accept đồng thời không vượt (lockForUpdate trên hàng tenant).
- Trang Nhân viên: bảng staff (màu, role, is_bookable, status), tab Lời mời; vô hiệu nhân viên (status disabled, không xóa).
- Test: token sai/hết hạn/đã dùng, email không khớp, vượt quota, cô lập tenant.
```

**Xong khi:** mời → nhận mail ở Mailpit → accept → thấy workspace trong switcher.

---

## Phase 5: Nghiệp vụ lõi

### Task 5.1: Chủ nuôi & thú cưng

```prompt
Đọc docs/db-schema.md (owners, pets) và docs/design.md S4, S10. Làm CRUD:
- Owners: danh sách có tìm theo tên/SĐT (chuẩn hóa SĐT VN sang E.164 bằng một value object Phone, dùng lại cho portal), phân trang, đếm số pet và lần đến gần nhất KHÔNG N+1 (withCount, subquery select). Unique (tenant_id, phone) báo lỗi thân thiện.
- Pets: thêm/sửa trong ngữ cảnh owner; gợi ý size từ breed.default_size + weight (class `PetSizeSuggester`, có unit test); cờ an toàn (dị ứng, ghi chú cảnh báo) hiển thị badge.
- Hồ sơ pet (S4) tab Tổng quan + Lịch sử dịch vụ (tab Y tế, Tiêm phòng để phase 6, hiển thị placeholder).
- API combobox tìm chủ nuôi (dùng cho form đặt lịch): trả tên, SĐT, số pet; giới hạn 10; index phù hợp.
- Soft delete; xóa owner có lịch hẹn tương lai thì chặn.
- Test: CRUD, phân quyền, cô lập tenant (dùng helper phase 2), không N+1 (đếm query).
```

### Task 5.2: Dịch vụ + thời lượng động

```prompt
Đọc docs/db-schema.md mục 4.1 và docs/design.md S9. Làm:
- CRUD services và service_duration_rules; gán nhân viên làm được dịch vụ (staff_services).
- `ServiceQuoteResolver::resolve(Service, Pet|PetProfile): Quote{durationMin, bufferMin, price, appliedRule, explanation}`. Thứ tự: breed → species+size → species → default. Một query, ORDER BY độ cụ thể, priority. `PetProfile` là DTO (species, breed, size) để portal dùng khi khách chưa có pet trong DB.
- explanation dạng "90 phút · áp dụng cho Chó cỡ L".
- Ô "Thử tính" ở màn chi tiết dịch vụ gọi resolver.
- Unit test đủ các nhánh resolve, quy tắc trùng độ cụ thể thì priority thắng, dịch vụ không có rule.
```

### Task 5.3: Lịch làm việc, ngày nghỉ, ngày đóng cửa

```prompt
Đọc docs/db-schema.md mục 4.2, 5.3 và docs/design.md S8. Làm:
- CRUD staff_working_hours (nhiều ca/ngày, effective_from/to; validate không chồng ca trong cùng ngày), staff_time_off (nhập giờ local tiệm, lưu UTC), tenant_closures.
- Khi thêm time off trùng lịch hẹn đang active: trả về danh sách lịch bị ảnh hưởng (không tự hủy).
- Class `TenantClock` (hoặc tương tự) gom việc convert local ↔ UTC theo tenant timezone, có `localDayRangeUtc(date)`. Không cộng offset cố định.
- UI lưới tuần như S8.
- Test có timezone DST (America/New_York): ca 09:00-17:00 ngày đổi giờ convert đúng.
```

### Task 5.4: Tính slot trống ⭐

```prompt
Đọc docs/db-schema.md mục 4.2 và 5.3. Viết `AvailabilityService`:
- Input: tenant, service quote (duration+buffer), ngày local D (hoặc khoảng ngày), staffId hoặc null = bất kỳ nhân viên bookable làm được dịch vụ.
- Output: danh sách slot (startsAt UTC, endsAt UTC, staffIds khả dụng), bước 15 phút, slot phải nằm trọn trong một ca (ends_at gồm buffer), loại trừ time_off, closures, appointments trạng thái BLOCKING, slot đã qua (so với now + lead time cấu hình).
- Hiệu năng: tải dữ liệu cho cả khoảng ngày trong số query cố định (không query theo từng slot hay từng nhân viên). Tính toán bằng interval arithmetic trong PHP (viết class Interval/IntervalSet nhỏ, có unit test).
- Endpoint JSON cho UI và (sau này) portal.
- Test: ca gãy, buffer cuối ca, time off nửa ngày, closure, lịch cancelled không chặn, DST, "bất kỳ" gộp slot của nhiều nhân viên, số query không tăng theo số nhân viên (assert query count).
```

**Xong khi:** test xanh; với DemoSeeder, slot của người nghỉ chiều không có buổi chiều.

### Task 5.5: Đặt lịch chống double-booking ⭐⭐ (điểm nhấn CV)

```prompt
Đọc docs/db-schema.md mục 5.1 kỹ. Viết Action `BookAppointment` (dùng chung cho staff và portal):
- Input DTO: ownerId, petId, serviceId, staffId|null, startsAt (UTC), source, idempotencyKey (UUID từ client), notes, reminder channels.
- Trong DB::transaction: (1) nếu idempotency key đã tồn tại → trả lại lịch cũ, không lỗi; (2) resolve quote, tính endsAt; (3) re-validate slot nằm trong ca/không time off/không closure; (4) `Staff::whereKey($id)->lockForUpdate()` rồi check overlap (`starts_at < :end AND ends_at > :start AND status IN BLOCKING`); (5) check pet không có lịch chồng; (6) quota: UPDATE tenant_usage atomic có điều kiện (`appointments_count < max`), INSERT ... ON DUPLICATE KEY cho tháng mới; affected = 0 → QuotaExceededException; (7) tạo appointment với snapshot duration/price; (8) dispatch event AppointmentBooked afterCommit.
- "Bất kỳ nhân viên": thử lần lượt từng nhân viên khả dụng, MỖI người một transaction riêng; SlotTakenException thì sang người kế. Không giữ nhiều lock cùng lúc. Thứ tự thử: ít lịch nhất trong ngày trước (cân tải).
- Deadlock/lock wait timeout: retry có giới hạn (DB::transaction attempts=3).
- Exception nghiệp vụ map sang HTTP 409 với payload gồm 3 slot gần nhất còn trống (dùng AvailabilityService).
- **Test concurrency thật**: script/Pest test bắn N=20 request song song vào cùng slot (dùng pcntl_fork hoặc Symfony Process chạy artisan command, mỗi process một connection MySQL riêng, không dùng RefreshDatabase transaction cho test này) → assert đúng 1 lịch được tạo và 19 nhận SlotTaken. Thêm test tương tự cho quota (còn 1 suất, 10 request) và idempotency (cùng key 10 lần → 1 lịch).
- Viết `docs/adr/0002-double-booking-lock-staff-row.md` (so sánh với bảng slot UNIQUE, advisory lock GET_LOCK, SERIALIZABLE), ghi kết quả test concurrency. Cập nhật docs/architecture.md mục Đặt lịch, có sequence diagram mermaid.
```

**Xong khi:** test concurrency chạy được trong CI và lặp 10 lần liền đều xanh; đọc ADR thấy rõ vì sao khóa hàng staff không gây gap lock.

### Task 5.6: Vòng đời lịch hẹn + dời lịch

```prompt
Đọc docs/design.md S3, F3. Làm:
- State machine cho appointment status (enum có method `canTransitionTo`): pending→confirmed→checked_in→completed; cancelled/no_show từ các trạng thái hợp lệ. Action ConfirmAppointment, CheckIn, Complete, Cancel (lý do, có tùy chọn thông báo khách), MarkNoShow. Mỗi action kiểm quyền qua Policy (groomer chỉ thao tác lịch của mình).
- `RescheduleAppointment`: dùng cùng cơ chế lock như BookAppointment (tái sử dụng, không copy code); nếu đổi staff thì lock cả 2 hàng staff theo thứ tự id tăng dần để tránh deadlock; giữ snapshot giá trừ khi đổi dịch vụ.
- Hoàn tác hủy trong 5 giây (hủy rồi khôi phục = re-check slot).
- Bảng audit `appointment_events` (ai, khi nào, từ→đến) nếu thấy cần; nếu thêm thì cập nhật docs/db-schema.md trong repo.
- Test: chuyển trạng thái không hợp lệ bị chặn, reschedule đụng lịch → 409, hai reschedule chéo nhau đồng thời không deadlock.
```

### Task 5.7: UI lịch + form đặt lịch (S1, S2, S3)

```prompt
Đọc docs/design.md S1, S2, S3, F1 và mục 7. Làm UI bằng Vue 3 + Inertia:
- S1 Lịch: chế độ Ngày / Tuần / Theo nhân viên (mặc định), trục 07:00-21:00 bước 15', vùng ngoài ca sọc xám, time off, closure overlay, vạch "bây giờ", block theo màu staff + nền loại dịch vụ + buffer sọc chéo, badge an toàn của pet. Tự viết grid bằng CSS grid (không dùng FullCalendar) để kiểm soát; nếu thấy quá tốn thì dùng thư viện nhưng giải thích lựa chọn.
- Dữ liệu lịch theo ngày: một endpoint trả appointments + working hours + time off của khoảng UTC tương ứng ngày local; eager load đủ, không N+1.
- S2 drawer đặt lịch: combobox chủ nuôi, chọn pet, list dịch vụ với giá/thời lượng đã resolve theo pet, nhân viên ("Bất kỳ ai phù hợp"), date picker + lưới slot, thanh tóm tắt dính đáy. Sinh idempotency key (crypto.randomUUID) khi mở form, giữ nguyên khi retry. Nút submit chặn double click. Xử lý 409: toast, refresh slot, gợi ý 3 slot, không mất dữ liệu form. Quota hết: banner + disable.
- S3 drawer chi tiết: action theo status, hủy có modal + toast Hoàn tác.
- Kéo-thả dời lịch (có thể làm sau cùng trong task, tách commit).
- Phím tắt N mở đặt lịch, `/` focus tìm kiếm. Accessibility theo mục 7.8.
- Test: Pest feature cho endpoint; thêm 1-2 test trình duyệt (Pest browser plugin hoặc Dusk) cho luồng F1.
```

**Xong khi:** luồng F1 làm được dưới 60 giây với dữ liệu demo; mở 2 tab cùng đặt 1 slot → tab sau nhận thông báo thân thiện.

---

## Phase 6: Queue, nhắc hẹn, tiêm phòng, y tế ⭐

### Task 6.1: Hạ tầng queue + nhắc hẹn

```prompt
Đọc docs/db-schema.md (reminders) và mục 5.2 về job TenantAware. Làm:
- Cài Laravel Horizon (queue: high cho booking notifications, default, low cho reminders hàng loạt), service horizon trong docker compose, dashboard chỉ admin.
- Khi AppointmentBooked: tạo bản ghi reminders (24h và 2h trước, theo kênh chủ nuôi chọn) với dedupe_key `appt:{id}:{offset}:{channel}`, insertOrIgnore. Lịch bị hủy/dời: đánh dấu reminders pending là skipped, tạo mới cho giờ mới.
- Scheduler mỗi phút: chọn reminders pending đến hạn (`status, send_at` index), claim nguyên tử (UPDATE ... SET status='processing' WHERE id IN (...) AND status='pending' hoặc SELECT ... FOR UPDATE SKIP LOCKED), dispatch job gửi.
- Job gửi: TenantAware, idempotent (nếu đã sent thì thoát), retry với backoff [60, 300, 900], `failed()` ghi status failed + last_error. Kênh: Mail (queued mailable, nội dung tiếng Việt, giờ theo timezone tiệm, link quản lý lịch) và Telegram (Bot API qua Http client, chat_id từ owners.telegram_chat_id; HTTP 429 thì release theo retry_after). Kênh dùng interface `ReminderChannel` để thêm Zalo sau.
- Thử lại thủ công từ S3 ("Telegram thất bại · Thử lại").
- Test: dedupe (chạy scheduler 2 lần không gửi 2 lần), hai worker claim cùng lúc không trùng, retry, hủy lịch thì không gửi, Http::fake cho Telegram.
- Cập nhật docs/architecture.md mục Queue.
```

### Task 6.2: Hồ sơ y tế

```prompt
Đọc docs/db-schema.md (medical_records) và docs/design.md S4 tab Y tế, F4. Làm:
- Ghi khám gắn với pet (và appointment nếu mở từ lịch). Chỉ role vet sửa/tạo; lễ tân, groomer chỉ xem (Policy + `can` props, UI khóa kèm "Chỉ bác sĩ được chỉnh sửa"). Chỉ được sửa bản ghi do chính mình tạo trong 24h (quy tắc tùy chọn, ghi rõ trong code nếu áp dụng).
- Timeline y tế ở hồ sơ pet.
- Test ma trận quyền và cô lập tenant.
```

### Task 6.3: Phác đồ tiêm phòng / tẩy giun ⭐

```prompt
Đọc docs/db-schema.md mục 4.3 và docs/design.md S4 tab Tiêm phòng, S7, F4, F5. Làm:
- CRUD vaccines, care_protocols, care_protocol_steps (owner/vet).
- `CareScheduleCalculator` (thuần, unit test kỹ): từ protocol + lịch sử mũi của pet + ngày sinh → mũi kế tiếp và next_due_on (min_age_days, offset_days, recurring). Ngày theo DATE local tiệm.
- Action `RecordCareDose` (vet): ghi mũi, tính next_due_on, đánh dấu record cũ cùng vaccine superseded_at, trong transaction. UI hiển thị trước "Mũi kế tiếp dự kiến: dd/MM/yyyy". "Đánh dấu đã tiêm ở nơi khác".
- Scheduler hàng ngày (chạy theo giờ sáng của TỪNG tenant timezone, ví dụ 08:00 local; dùng Tenant::forEach hoặc query toàn cục có chủ đích): next_due_on trong 7 ngày tới hoặc quá hạn, superseded_at null → tạo reminders dedupe_key `care:{record_id}:{offset}:{channel}`. Dùng chunkById, không load hết vào bộ nhớ.
- S7 danh sách đến hạn: tab Quá hạn / 7 ngày / 30 ngày, filter, trạng thái nhắc, action Đặt lịch tiêm (mở S2 điền sẵn), Gửi nhắc lại.
- Tab Tiêm phòng ở hồ sơ pet với tiến độ phác đồ (3/4 mũi).
- Test: các phác đồ trong design.md mục 8 (Sushi mũi 2/4, Tôm quá hạn), chạy scheduler 2 lần không nhắc trùng, timezone.
```

---

## Phase 7: Portal đặt lịch công khai

### Task 7.1: Portal (S6, S14)

```prompt
Đọc docs/design.md S6, S14, F2, mục 7.7 và docs/db-schema.md 5.2. Làm portal tại `/{slug}` (mobile-first, layout riêng):
- Bước 1 dịch vụ công khai; bước 2 thông tin pet (loài, giống, cân nặng → quote qua ServiceQuoteResolver với PetProfile); nhập SĐT: nếu có hồ sơ thì chỉ gợi ý TÊN pet (không lộ thông tin khác); bước 3 chọn nhân viên + dải 14 ngày + slot (AvailabilityService); bước 4 xác nhận.
- Submit dùng BookAppointment với source=portal (tạo owner/pet nếu mới, khớp theo SĐT). Không tạo trùng owner khi 2 request cùng SĐT đồng thời (dựa vào UNIQUE(tenant_id, phone) + bắt lỗi duplicate rồi đọc lại).
- Chống lạm dụng: rate limit theo IP + SĐT + tenant, honeypot hoặc Turnstile, giới hạn số lịch pending/SĐT.
- Trang thành công: mã lịch (public_id), file .ics, deep link Telegram (`t.me/<bot>?start=<token>`; webhook bot lưu chat_id vào owner), link quản lý.
- S14 quản lý lịch qua signed URL theo public_id (không login): xem, hủy (tôn trọng chính sách hủy của tiệm, vd không hủy trong 2h trước), dời (về bước 3).
- Hiển thị giờ theo timezone tiệm, ghi chú nếu khác thiết bị.
- Test: không lộ dữ liệu (dò public_id, gợi ý pet theo SĐT), rate limit, URL ký sai/hết hạn, tenant suspended thì portal đóng.
```

---

## Phase 8: Billing & quota

### Task 8.1: Cashier + gói cước

```prompt
Đọc docs/db-schema.md (plans, subscriptions, stripe_webhook_events, tenant_usage) và docs/design.md S12, F6. Làm:
- Laravel Cashier (Stripe), billable = Tenant. Trang Gói cước: so sánh gói, gói hiện tại, usage, nâng cấp qua Stripe Checkout, quản lý qua Billing Portal.
- Webhook idempotent: lưu event vào stripe_webhook_events (UNIQUE event_id) TRƯỚC khi xử lý, xử lý trong job queue; event trùng thì bỏ qua. Xử lý: subscription created/updated/deleted → đồng bộ tenants.plan_id; invoice.payment_failed → banner, quá hạn N ngày → status suspended (chặn đặt lịch mới, vẫn cho xem).
- Thống nhất mọi điểm kiểm tra giới hạn gói vào một class `PlanLimits` (max_staff, max_appointments_per_month, feature flags) dùng ở phase 4.3 và 5.5.
- Banner quota ≥80% (vàng), 100% (đỏ) trên toàn app.
- Test: webhook trùng không xử lý 2 lần (gửi cùng payload 2 lần), chữ ký sai bị từ chối, downgrade khi đang vượt số nhân viên (quy tắc: không khóa người cũ, chặn mời thêm), dùng Stripe test mode + fixture payload, không gọi Stripe thật trong CI.
- `docs/adr/0003-billing-quota-atomic-counter.md`.
```

---

## Phase 9: Dashboard, hiệu năng, observability

### Task 9.1: Dashboard (S5)

```prompt
Đọc docs/design.md S5. Làm dashboard: 4 KPI, lịch hôm nay nhóm theo nhân viên, tiêm phòng đến hạn (5 dòng), biểu đồ 14 ngày cột chồng theo loại dịch vụ, usage gói (owner), nhắc thất bại. Bản rút gọn cho non-owner (không doanh thu/usage). Mỗi widget là một query class riêng; tổng số query cố định (assert trong test); cache ngắn theo key tenant nếu query nặng; mọi khoảng ngày tính theo ngày local tiệm.
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
- Môi trường demo: reset DemoSeeder mỗi đêm, tài khoản demo cho từng vai trò (hiển thị trên trang login demo), tắt gửi mail/Telegram thật (log/mailpit).
```

### Task 10.2: README & ADR hoàn chỉnh

```prompt
Viết lại README cho nhà tuyển dụng đọc trong 2 phút: demo link + tài khoản demo theo vai trò, ảnh chụp màn hình (lịch, đặt lịch, hồ sơ pet, portal mobile), sơ đồ kiến trúc (mermaid), mục "Điểm kỹ thuật đáng chú ý" (multi-tenant fail closed + FK ghép; chống double-booking + test concurrency; timezone/DST; reminders idempotent; webhook idempotent; quota atomic) mỗi mục 2-3 dòng + link code/ADR, số liệu (coverage, PHPStan level, p95 đặt lịch từ docs/performance.md), cách chạy local. Rà docs/adr/ đủ: 0001 single DB, 0002 double-booking, 0003 billing/quota, 0004 UTC + TIME local, 0005 reminders dedupe/claim, 0006 chọn Inertia thay vì SPA + API.
```

**Xong khi:** người lạ mở README hiểu dự án làm gì, vào được demo, và thấy ngay 3-4 điểm kỹ thuật khó.

---

## Phụ lục: Prompt review dùng sau mỗi task

```prompt
Review diff của branch hiện tại so với main như một senior reviewer, tập trung: rò rỉ dữ liệu giữa tenant (query thiếu scope, exists không kèm tenant_id, route binding, job thiếu context, cache key), race condition, N+1 / thiếu index, timezone (so sánh/format theo UTC hay local), idempotency, thiếu test. Liệt kê vấn đề theo mức độ, sửa những cái chắc chắn, hỏi lại những cái cần quyết định.
```
