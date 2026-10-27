# Danh mục màn hình: PawBook

Nguồn tên màn hình **duy nhất** cho Claude Design, Claude Code và Claude AI. Khi nói về một màn, luôn ghi **mã + tên** (vd `S17 Visit`). Nội dung chi tiết từng màn vẫn ở `docs/design/design.md` mục 6; file này chỉ chốt **mã, tên, đường dẫn, thứ tự**.

Nguồn đối chiếu: `docs/design/design.md` (mục 4, 5, 6), `docs/database/db-schema.md`, `docs/analysis/nghiep-vu-da-chot.md`, `docs/plan/implementation-plan.md`.

---

## 1. Quy ước

### 1.1 Mã màn

| Tiền tố | Ý nghĩa | Ghi chú |
|---|---|---|
| `K0`–`K4` | Khung và design system (không phải màn nghiệp vụ) | Vẽ trước mọi màn |
| `S1`–`S35` | Màn của shop (CMS, Site, route trung tâm) | **Giữ nguyên mã của design.md.** `S34` bỏ trống, không dùng lại |
| `S36`+ | Màn shop bổ sung (design.md chưa đặt mã) | Thêm mới thì lấy số kế tiếp |
| `A1`–`A6` | Admin nền tảng, giữ nguyên mã design.md | |
| `A7`+ | Admin bổ sung | |
| `Sx.n` | Màn con / tab lớn của màn `Sx` (vd `S12.2`) | Đưa Claude Design từng màn con |

Mã đã cấp **không bao giờ đổi hoặc dùng lại**, kể cả khi màn bị bỏ (ghi "Đã bỏ").

### 1.2 Đặt tên trong từng công cụ

| Nơi dùng | Quy tắc | Ví dụ |
|---|---|---|
| Claude Design (frame) | `{Mã} {Tên} / {biến thể} / {trạng thái}` | `S17 Visit / Khám / default`, `S6 Site đặt lịch / Bước 4 / slot vừa hết` |
| Trạng thái chuẩn | `default`, `loading`, `empty`, `error`, `no-permission`, `quota`, `suspended`, `dark`, `tablet`, `mobile` | `S18 Thu ngân / quota` |
| Laravel route name | `app.*` (CMS), `site.*` (Site), `admin.*` (Admin), `account.*` (tài khoản cá nhân), không tiền tố cho route trung tâm khác | `app.visits.show` |
| Controller | Theo khu: `App\Http\Controllers\Cms\…` (CMS, route `app.*`), `…\Site\…`, `…\Admin\…`, `…\Account\…`, `…\Auth\…` | `Cms\VisitController@show` |
| Trang Vue (Inertia) | Thư mục gốc **chữ thường** giữ theo starter kit: `resources/js/pages/`, `resources/js/components/`, `resources/js/layouts/`; thư mục con theo khu **PascalCase**: `Cms`, `Site`, `Admin`, `Account`, `Auth`. Suy từ route name: `app.visits.show` → `resources/js/pages/Cms/Visits/Show.vue` | `site.booking.create` → `pages/Site/Booking/Create.vue` |
| Layout | `resources/js/layouts/{Cms,Site,Admin,Auth,Print,Bare}Layout.vue` | `Print` cho S40, `Bare` cho S16.1 TV, kiosk |
| Drawer / modal | Component riêng, không có trang: `components/{Khu}/{Tên}Drawer.vue` | `components/Cms/AppointmentFormDrawer.vue` (S2) |
| Branch, PR, commit | Nhắc mã màn trong mô tả | `feat(S15): tiếp nhận + cấp cứu` |
| Test | `describe('S15 Tiếp nhận', …)` | |

### 1.3 Đường dẫn

- CMS: `/app/{slug}/...` tiếng Anh, số nhiều (`/appointments`, `/visits/{id}`).
- Admin: `/admin/...` tiếng Anh.
- Site: `/{slug}/...` **giữ tiếng Việt theo design.md** (`/dang-nhap`, `/tai-khoan`, `/dieu-khoan`) vì khách nhìn thấy; route name vẫn tiếng Anh (`site.login`). Xem mục 5, điểm 2.
- Cột "Dạng": **Trang** (có URL), **Drawer** / **Modal** (mở trên trang khác), **Tab** (tab trong trang, có query `?tab=`).

### 1.4 Thiết bị mục tiêu

Theo design.md mục 0, 3.4. Khi đưa Claude Design, yêu cầu vẽ **khung chính** trước, rồi các khung phụ được đánh dấu.

| Khu | Khung chính | Khung phụ bắt buộc |
|---|---|---|
| Site | **Mobile 390px** (thử 360px) | Desktop chỉ cần không vỡ (một cột, căn giữa) |
| CMS | **Desktop 1440px** | Tablet 1024px cho màn quầy; mobile < 640px cho màn ở bảng dưới (bottom nav 4 mục: Hôm nay, Hàng đợi, Khách, Thêm) |
| Admin | Desktop | Không cần mobile |

Màn CMS cần vẽ thêm bản **mobile** (người dùng chính cầm điện thoại):

| Mã | Ai dùng trên điện thoại | Bản mobile khác desktop thế nào |
|---|---|---|
| S36, S37, S13, S39 | Mọi nhân viên | Form một cột |
| S5 | Chủ (bản đủ), nhân viên (rút gọn) | KPI xếp dọc, ẩn biểu đồ lớn |
| S1 | Spa | List theo giờ, chỉ lịch của mình + "Chưa phân" nhóm spa |
| S3 | Spa, BS | Bottom sheet thay drawer |
| S16 | Spa (vào thẳng tab Spa) | Một cột, thẻ lớn, nút "Nhận khách" |
| S17 (spa) | Spa | Một cột, chụp ảnh nhận/trả bằng camera, nút "Hoàn tất" dính đáy |
| S10, S4 | Chủ, LT, BS | Tab cuộn ngang, timeline một cột |
| S41 | Mọi nhân viên | Trang toàn màn thay dropdown |
| S26.1 | Mọi nhân viên (GĐ3) | Lưới 2 tuần dạng list ngày |
| S27 | Mọi nhân viên (GĐ3) | Nút chấm công lớn |
| S30 | Chủ | KPI xếp dọc, bảng chuyển thành thẻ |

Màn CMS cần bản **tablet 1024px** (lễ tân dùng iPad): S1, S2, S3, S15, S16, S17, S18, S19, S35. Kiosk S27.1 chỉ vẽ tablet ngang. Các màn CMS còn lại (cài đặt, kho, báo cáo chi tiết, lương, nhật ký) chỉ cần desktop, tablet tự co.

---

## 2. Thứ tự đưa cho Claude Design

Theo thứ tự build trong plan (Phase 4 → 12) để màn luôn có thiết kế trước khi code. Mỗi đợt: đưa lần lượt từng dòng, màn nền trước, màn phụ thuộc sau.

| Đợt | Nội dung | Mã theo thứ tự đưa | Ứng với task |
|---|---|---|---|
| 0 | Design system + khung | K0 → K1 → K2 → K3 → K4 | 4.1, 4.3, 7.1 |
| 1 | Admin nền tảng | A1 → A1.1 → A2 → A3 → A4 → A5 → A6 → A7 | 4.1, 4.3, 4.5, 4.6 |
| 2 | Vào shop + cài đặt nền | S36 → S37 → S13 → S39 → S12.1 → S12.2 → S12.3 → S11 → S11.1 → S12.6 | 4.2–4.6 |
| 3 | Danh mục | S10 → S4 → S9 → S9.1 → S8 | 5.1–5.3 |
| 4 | Lịch hẹn | S2 → S1 → S3 → S20 | 5.4–5.7 |
| 5 | Quầy + Visit + tiền | S15 → S16 → S16.1 → S17 → S35 → S18 → S19 → S40 | 5.8–5.11 |
| 6 | Nhắc, bệnh án, tiêm | S41 → S12.4 → S44 → S7 (bệnh án, sổ tiêm là tab của S4, vẽ lại nếu cần) | 6.1–6.3 |
| 7 | Site khách | S42 → S43 → S21 → S6 → S14 → S21.1 → S33 → S12.5 | 7.1 |
| 8 | Tổng quan, báo cáo | S5 → S30 → S32 | 9.1 |
| 9 | Kho (GĐ2) | S22 → S22.1 → S24 → S24.1 → S24.2 → S24.3 → S23 → S19 (bản đủ) → S31 → S25 | 11.1–11.5 |
| 10 | Ca, công, lương (GĐ3) | S26.2 → S26 → S26.1 → S27 → S27.1 → S28 → S29 → S29.1 | 12.1–12.3 |

### 2.1 Prompt mẫu cho Claude Design

**Lần đầu (một lần, mở project mới):** đính kèm `docs/design/design.md`, `docs/design/danh-muc-man-hinh.md`, `SKILL.md` của frontend-design, rồi gửi:

```
Đây là tài liệu thiết kế PawBook (design.md), danh mục mã màn (danh-muc-man-hinh.md) và một skill thiết kế.
Quy tắc:
- Token, font, icon, component ở design.md mục 3 ưu tiên hơn skill. Skill chỉ dùng để nâng chất lượng bố cục và chi tiết.
- Đặt tên frame theo danh-muc-man-hinh.md mục 1.2: "{Mã} {Tên} / {biến thể} / {trạng thái}".
- Vẽ theo thiết bị ở mục 1.4. Dữ liệu mẫu lấy design.md mục 9, tiếng Việt, có light và dark.
- Mỗi lần mình chỉ đưa một màn. Không tự vẽ màn chưa được yêu cầu.
Bắt đầu với K0 Design system: dựng token màu light/dark, typography, spacing và bộ component ở design.md mục 3.
```

**Mỗi màn sau đó:**

```
Vẽ {Mã} {Tên} (danh-muc-man-hinh.md).
Đặc tả: design.md mục {6.x}, phần {Mã}.
Thiết bị: {desktop / tablet / mobile theo mục 1.4}.
Trạng thái cần vẽ: default, loading, empty, error, no-permission{, quota, suspended nếu có}.
Dùng lại khung {K1 / K2 / K3} và component đã có, không tạo kiểu mới.
```

Ví dụ: `Vẽ S16 Hàng đợi hôm nay. Đặc tả: design.md mục 6.3, phần S16. Thiết bị: desktop, tablet, mobile cho Spa. Trạng thái: default, loading, empty, có cấp cứu. Dùng lại khung K1.`

Mẹo khi đưa: kèm mã + tên + đoạn design.md mục 6 của màn đó + "khung K1/K2/K3 đã có" + thiết bị theo mục 1.4 (vd "S16: desktop, tablet, mobile cho Spa"). Màn drawer (S2, S3) vẽ chồng lên S1.

---

## 3. Danh mục chi tiết

Vai trò viết tắt: **Chủ** (chủ shop), **QL** (quản lý chi nhánh), **LT** (lễ tân), **TN** (thu ngân), **BS** (bác sĩ), **Spa**, **Kho**, **Khách**, **SA** (super-admin), **SP** (support). Ưu tiên P0/P1 theo design.md mục 5.

### 3.1 Khung và design system

| Mã | Tên | Nội dung | GĐ |
|---|---|---|---|
| K0 | Design system | Token màu (light/dark), typography, spacing, component (design.md mục 3) | MVP |
| K1 | Khung CMS | Sidebar, topbar (`Tên shop › Cơ sở ▾`, tìm toàn cục, + Đặt lịch, chip ca thu ngân, chuông, avatar), workspace switcher, branch switcher, bottom nav mobile, banner hạn dùng / tạm khóa / đăng nhập thay | MVP |
| K2 | Khung Site | Header shop, bottom nav, footer (Điều khoản), CTA dính đáy, mobile 390px | MVP |
| K3 | Khung Admin | Sidebar tối, logo "PawBook Admin", badge môi trường | MVP |
| K4 | Trạng thái và trang lỗi chung | Skeleton, empty, error, không có quyền, vượt hạn mức gói, 403, 404, 419, 500, **423 shop tạm khóa**, Site "tạm ngưng nhận lịch" | MVP |

### 3.2 Route trung tâm (không thuộc shop nào)

| Mã | Tên | Dạng | URL | Route name | Vai trò | Bảng chính | GĐ | Ưu tiên |
|---|---|---|---|---|---|---|---|---|
| S36 | Đăng nhập CMS (có trạng thái lỗi "Tài khoản không còn hoạt động ở shop nào") | Trang | `/login` | `login` | Mọi nhân viên | users, staff | MVP | P0 |
| S37 | Quên / đặt lại mật khẩu | Trang | `/forgot-password`, `/reset-password/{token}` | `password.request`, `password.reset` | Mọi nhân viên | users | MVP | P1 |
| S13 | Nhận lời mời + checklist lần đầu | Trang | `/invite/{token}` | `invitations.accept` | Chủ, nhân viên | invitations, staff, users | MVP | P1 |
| S38 | **Đã bỏ** (gộp vào trạng thái lỗi của S36: không còn shop đang hoạt động thì không cho đăng nhập) | — | — | — | — | — | — | — |
| S39 | Tài khoản cá nhân (hồ sơ, mật khẩu, giao diện). **Không có chức năng xóa tài khoản**: tài khoản dùng chung nhiều shop | Trang | `/account` (`/account/password`, `/account/appearance`) | `account.edit`, `account.password`, `account.appearance` | Mọi nhân viên | users | MVP | P1 |

### 3.3 CMS (`/app/{slug}`)

URL dưới đây bỏ tiền tố `/app/{slug}`; route name có tiền tố `app.`.

**Vận hành hằng ngày**

| Mã | Tên | Dạng | URL | Route name | Vai trò | Bảng chính | GĐ | Ưu tiên |
|---|---|---|---|---|---|---|---|---|
| S5 | Tổng quan | Trang | `/dashboard` | `app.dashboard` | Chủ (bản đủ); LT, BS, Spa (rút gọn) | appointments, invoices, pet_vaccinations, tenant_usage, notifications | MVP | P0 |
| S1 | Lịch hẹn | Trang | `/appointments` (`?view=day\|week\|staff`) | `app.appointments.index` | Mọi nhân viên | appointments, appointment_items, staff_working_hours, branch_closures | MVP | P0 |
| S2 | Đặt lịch (CMS) | Drawer | trên S1, S5, S7, S4 | `app.appointments.store` | LT, Chủ | appointments, appointment_items, customers, pets, service_price_rules | MVP | P0 |

S2 và S6: một pet vừa khám vừa spa thì hai dòng xếp **nối tiếp** (mặc định khám trước), form hiện giờ bắt đầu riêng từng dòng; lễ tân đổi được thứ tự. Lưới khung hiện "chỗ online còn" = `min(hạn mức online còn, người trực − tổng dòng đang chiếm)` (db-schema 2.5).
| S3 | Chi tiết lịch hẹn | Drawer | `/appointments/{public_id}` | `app.appointments.show` | Mọi nhân viên | appointments, appointment_items, appointment_changes, message_deliveries | MVP | P0 |
| S20 | Cần gọi hôm nay (+ lịch bị ảnh hưởng bởi ngày nghỉ / nghỉ đột xuất) | Trang | `/appointments/call-list` | `app.appointments.call-list` | LT | appointments, reminders, message_deliveries, branch_closures, staff_time_off | MVP | P1 |
| S15 | Tiếp nhận | Trang | `/reception` | `app.reception.index` | LT | customers, pets, visits, invoices, appointments | MVP | P0 |
| S16 | Hàng đợi hôm nay | Trang | `/queue` (`?group=medical\|grooming`) | `app.queue.index` | LT, BS, Spa | visits, branch_daily_counters | MVP | P0 |
| S16.1 | Hàng đợi: chế độ TV phòng chờ | Trang | `/queue/tv` | `app.queue.tv` | Màn hình phòng chờ | visits | MVP | P1 |
| S17 | Visit (khám / spa) | Trang | `/visits/{id}` | `app.visits.show` | BS, Spa (ghi); LT (chỉ đọc) | visits, visit_items, medical_records, medical_entries, pet_vaccinations, attachments | MVP | P0 |
| S18 | Thu ngân | Trang | `/cashier` (`/cashier/invoices/{id}`) | `app.cashier.index` | LT, TN, Chủ | invoices, invoice_lines, payments, visits | MVP | P0 |
| S19 | Bán lẻ tại quầy | Trang | `/pos` | `app.pos.index` | LT, TN | invoices, invoice_lines, payments (GĐ2: products, stock_batches) | MVP khung, GĐ2 đủ | P1 |
| S35 | Ca thu ngân | Trang + Modal | `/cash-sessions` | `app.cash-sessions.index` | TN, LT; Chủ, QL xem | cash_drawers, cash_sessions, payments | MVP | P0 |
| S40 | Mẫu in | Trang in | `/print/{type}/{id}` | `app.print.show` | LT, TN | visits, invoices, cash_sessions | MVP | P1 |
| S41 | Thông báo | Dropdown + Trang | `/notifications` | `app.notifications.index` | Mọi nhân viên | notifications | MVP | P1 |

`S40 Mẫu in` gồm 4 biến thể: phiếu số thứ tự (S15), tạm tính có watermark (S18), hóa đơn (S18), báo cáo ca (S35); thêm phiếu lịch hẹn (S3).

**Khách, thú cưng, dịch vụ, tiêm**

| Mã | Tên | Dạng | URL | Route name | Vai trò | Bảng chính | GĐ | Ưu tiên |
|---|---|---|---|---|---|---|---|---|
| S10 | Khách | Trang | `/customers`, `/customers/{id}` | `app.customers.index`, `.show` | LT, Chủ, QL | customers, pets, appointments, invoices | MVP | P1 |
| S4 | Hồ sơ thú cưng | Trang + Tab | `/pets/{id}?tab=overview\|history\|medical\|vaccination` | `app.pets.show` | Mọi nhân viên (theo quyền) | pets, visits, medical_records, medical_entries, medical_entry_addenda, pet_vaccinations | MVP | P0 |
| S7 | Tiêm phòng đến hạn | Trang | `/vaccinations/due` | `app.vaccinations.due` | BS, LT | pet_vaccinations, care_protocols, vaccines, reminders | MVP | P0 |
| S44 | Phác đồ tiêm & danh mục vaccine | Trang | `/care-protocols` (`/care-protocols/{id}`) | `app.care-protocols.index`, `.show` | Chủ, BS | vaccines, care_protocols, care_protocol_steps | MVP | P1 |
| S9 | Dịch vụ, combo | Trang | `/services` | `app.services.index` | Chủ | services, service_combo_items, branch_services | MVP | P1 |
| S9.1 | Chi tiết dịch vụ: quy tắc giá/thời lượng (+ vật tư định mức GĐ2) | Trang + Tab | `/services/{id}` | `app.services.show` | Chủ | service_price_rules, service_consumables (GĐ2) | MVP | P1 |

**Nhân sự**

| Mã | Tên | Dạng | URL | Route name | Vai trò | Bảng chính | GĐ | Ưu tiên |
|---|---|---|---|---|---|---|---|---|
| S11 | Nhân viên & lời mời | Trang + Tab | `/staff?tab=members\|invitations` | `app.staff.index` | Chủ; QL xem | staff, staff_branches, invitations, roles | MVP | P1 |
| S11.1 | Hồ sơ nhân viên (form) | Trang | `/staff/{id}/edit` | `app.staff.edit` | Chủ | staff, staff_branches, model_has_roles | MVP | P1 |
| S8 | Lịch trực & ngày nghỉ | Trang | `/schedules` | `app.schedules.index` | Chủ, QL (ghi); nhân viên xem của mình | staff_working_hours, staff_time_off, online_booking_rates | MVP | P1 |
| S26 | Xếp ca | Trang | `/shifts` | `app.shifts.index` | Chủ, QL | shift_assignments, shift_availabilities, coverage_requirements | GĐ3 | P1 |
| S26.1 | Ca của tôi (đăng ký ca) | Trang | `/shifts/mine` | `app.shifts.mine` | Mọi nhân viên | shift_availabilities, shift_assignments | GĐ3 | P1 |
| S26.2 | Cài đặt mẫu ca + số người tối thiểu | Trang | `/shifts/templates` | `app.shift-templates.index` | Chủ | shift_templates, coverage_requirements | GĐ3 | P1 |
| S27 | Chấm công (CMS) | Nút topbar + Modal | trên K1, S5 | `app.attendance.store` | Mọi nhân viên có tài khoản | attendance_records, shift_assignments | GĐ3 | P1 |
| S27.1 | Kiosk chấm công | Trang toàn màn | `/{slug}/kiosk` (token thiết bị) | `kiosk.show` | Nhân viên không tài khoản | attendance_devices, attendance_records | GĐ3 | P1 |
| S28 | Bảng công | Trang | `/attendance` | `app.attendance.index` | Chủ | attendance_records | GĐ3 | P1 |
| S29 | Lương | Trang (khóa cấp 2) | `/payroll` | `app.payroll.index` | Chủ, người có `payroll.view` | payroll_periods, payroll_entries, sensitive_access_logs | GĐ3 | P1 |
| S29.1 | Đơn giá ca | Trang | `/payroll/rates` | `app.payroll.rates` | Chủ | staff_shift_rates | GĐ3 | P1 |

**Kho (GĐ2)**

| Mã | Tên | Dạng | URL | Route name | Vai trò | Bảng chính | GĐ | Ưu tiên |
|---|---|---|---|---|---|---|---|---|
| S22 | Kho: tồn, lô, sổ kho, cảnh báo, hàng lỗi | Trang + Tab | `/inventory?tab=stock\|batches\|ledger\|alerts\|defective` | `app.inventory.index` | Chủ, Kho, LT, BS | stock_items, stock_batches, stock_movements, stock_alerts | GĐ2 | P1 |
| S22.1 | Sản phẩm (danh sách + form) | Trang | `/products`, `/products/{id}/edit` | `app.products.index` | Chủ | products, product_units, product_categories | GĐ2 | P1 |
| S24 | Phiếu nhập (2 bước) | Trang | `/purchase-receipts` | `app.purchase-receipts.index` | Kho (lập), Chủ (điền giá) | purchase_receipts, purchase_receipt_lines | GĐ2 | P1 |
| S24.1 | Phiếu hao hụt | Trang | `/stock-adjustments` | `app.stock-adjustments.index` | Kho (lập), Chủ, QL (duyệt) | stock_adjustments | GĐ2 | P1 |
| S24.2 | Kiểm kê | Trang | `/stocktakes` | `app.stocktakes.index` | Kho, Chủ, QL | stocktakes | GĐ2 | P1 |
| S24.3 | Nhà cung cấp | Trang | `/suppliers` | `app.suppliers.index` | Chủ | suppliers | GĐ2 | P1 |
| S23 | Tra tồn cơ sở khác + chuyển kho | Trang + Tab | `/stock-transfers?tab=outgoing\|incoming` | `app.stock-transfers.index` | LT, BS, Kho, QL | stock_transfers, stock_reservations | GĐ2 | P1 |
| S31 | Trả hàng | Trang | `/returns/create?invoice={id}` | `app.returns.create` | LT, TN, Chủ | sales_returns, payments, stock_movements | GĐ2 | P1 |

**Báo cáo, nhật ký, cài đặt**

| Mã | Tên | Dạng | URL | Route name | Vai trò | Bảng chính | GĐ | Ưu tiên |
|---|---|---|---|---|---|---|---|---|
| S30 | Báo cáo doanh thu & lãi gộp | Trang | `/reports/revenue` | `app.reports.revenue` | Chủ, QL | invoices, payments, stock_movements (GĐ2) | MVP (lãi gộp GĐ2) | P1 |
| S32 | Nhật ký thao tác | Trang | `/activity-logs` | `app.activity-logs.index` | Chủ | activity_logs | MVP | P1 |
| S12 | Cài đặt (khung, menu trái) | Trang | `/settings` | `app.settings.index` | Chủ | — | MVP | P1 |
| S12.1 | Cài đặt › Shop | Trang | `/settings/shop` | `app.settings.shop` | Chủ | tenants, tenant_settings | MVP | P1 |
| S12.2 | Cài đặt › Cơ sở (giờ mở cửa, ngày nghỉ, tỉ lệ đặt online, két, IP chấm công GĐ3) | Trang | `/settings/branches/{id}` | `app.settings.branches` | Chủ | branches, branch_closures, online_booking_rates, cash_drawers, branch_services | MVP | P1 |
| S12.3 | Cài đặt › Phân quyền (đổi tên role, bật/tắt quyền, quyền nhạy cảm có xác nhận) | Trang | `/settings/roles` | `app.settings.roles` | Chủ | roles (display_name), permissions, role_has_permissions | MVP | P1 |
| S12.4 | Cài đặt › Kênh nhắn tin | Trang | `/settings/messaging` | `app.settings.messaging` | Chủ | tenant_settings, message_deliveries | MVP | P1 |
| S12.5 | Cài đặt › Site (giới thiệu, dịch vụ hiển thị, Điều khoản, bật Sản phẩm GĐ2) | Trang | `/settings/site` | `app.settings.site` | Chủ | tenant_settings, services | MVP | P1 |
| S12.6 | Cài đặt › Gói & hạn dùng + Lịch sử truy cập hỗ trợ | Trang | `/settings/subscription` | `app.settings.subscription` | Chủ | tenants, tenant_subscriptions, tenant_usage, impersonation_sessions | MVP | P1 |

### 3.4 Site của shop (`/{slug}`)

Mobile-first 390px. Route name có tiền tố `site.`.

| Mã | Tên | Dạng | URL | Route name | Vai trò | Bảng chính | GĐ | Ưu tiên |
|---|---|---|---|---|---|---|---|---|
| S42 | Trang chủ shop | Trang | `/{slug}` | `site.home` | Khách | tenants, branches, tenant_settings | MVP | P0 |
| S43 | Dịch vụ & giá | Trang | `/{slug}/dich-vu` | `site.services` | Khách | services, service_price_rules, branch_services | MVP | P1 |
| S21 | Đăng nhập OTP | Trang | `/{slug}/dang-nhap` | `site.login` | Khách | customers, customer_otps | MVP | P0 |
| S6 | Đặt lịch online (5 bước + thành công) | Trang (stepper) | `/{slug}/dat-lich` | `site.booking.create` | Khách | appointments, appointment_items, booking_capacity_locks, pets | MVP | P0 |
| S14 | Quản lý lịch hẹn qua link | Trang | `/{slug}/lich-hen/{public_id}` (signed URL) | `site.appointments.manage` | Khách (không cần đăng nhập) | appointments, appointment_items, appointment_changes | MVP | P1 |
| S21.1 | Tài khoản khách (lịch hẹn, thú cưng, lịch sử khám, sổ tiêm, hóa đơn) | Trang + Tab | `/{slug}/tai-khoan` | `site.account` | Khách | appointments, pets, medical_entries (trừ ghi chú nội bộ), pet_vaccinations, invoices | MVP | P0 |
| S33 | Điều khoản sử dụng | Trang | `/{slug}/dieu-khoan` | `site.terms` | Khách | tenant_settings.terms_content | MVP | P1 |
| S25 | Trưng bày sản phẩm | Trang | `/{slug}/san-pham` | `site.products` | Khách | products, stock_items | GĐ2 | P1 |

### 3.5 Admin nền tảng (`/admin`)

Route name có tiền tố `admin.`.

| Mã | Tên | Dạng | URL | Route name | Vai trò | Bảng chính | GĐ | Ưu tiên |
|---|---|---|---|---|---|---|---|---|
| A1 | Đăng nhập + nhập mã 2FA | Trang | `/admin/login` | `admin.login` | SA, SP | platform_admins | MVP | P1 |
| A1.1 | Bật 2FA lần đầu (quét QR) | Trang | `/admin/two-factor/setup` | `admin.two-factor.setup` | SA, SP | platform_admins | MVP | P1 |
| A2 | Danh sách shop | Trang | `/admin/tenants` | `admin.tenants.index` | SA, SP | tenants, plans, branches, staff, appointments (đếm) | MVP | P1 |
| A3 | Tạo shop (wizard 3 bước) | Trang | `/admin/tenants/create` | `admin.tenants.create` | SA | tenants, branches, invitations, tenant_subscriptions | MVP | P1 |
| A4 | Chi tiết shop (Tổng quan · Gói & gia hạn · Truy cập hỗ trợ · Nhật ký; modal Tạm khóa, Đăng nhập thay) | Trang + Tab | `/admin/tenants/{id}?tab=...` | `admin.tenants.show` | SA; SP (xem, đăng nhập thay) | tenants, tenant_subscriptions, impersonation_sessions, platform_audit_logs | MVP | P1 |
| A5 | Nhật ký hoạt động | Trang | `/admin/audit-logs` | `admin.audit-logs.index` | SA, SP | platform_audit_logs | MVP | P1 |
| A6 | Quản lý admin (mời, vô hiệu) | Trang | `/admin/admins` | `admin.admins.index` | SA | platform_admins, platform_admin_invitations | MVP | P1 |
| A7 | Admin nhận lời mời (đặt mật khẩu → bật 2FA) | Trang | `/admin/invite/{token}` | `admin.invitations.accept` | Admin mới | platform_admin_invitations, platform_admins | MVP | P1 |

---

## 4. Màn đã có trong design.md, nay tách màn con

Mã cha giữ nguyên; màn con chỉ để đưa Claude Design từng phần cho gọn.

| Mã cha | Tách thành | Lý do |
|---|---|---|
| S9 | S9, S9.1 | Danh sách + chi tiết quy tắc giá là 2 bố cục khác nhau |
| S11 | S11, S11.1 | Form hồ sơ nhân viên có khối chuyên môn riêng |
| S12 | S12, S12.1–S12.6 | 6 trang cài đặt độc lập |
| S16 | S16, S16.1 | Chế độ TV phòng chờ là bố cục khác |
| S21 | S21 (đăng nhập), S21.1 (tài khoản) | 2 trang khác hẳn |
| S22 | S22, S22.1 | Form sản phẩm |
| S24 | S24, S24.1–S24.3 | 4 nghiệp vụ, 4 trang |
| S26 | S26, S26.1, S26.2 | Lưới chủ shop, màn nhân viên trên điện thoại, cài mẫu ca |
| S27 | S27, S27.1 | CMS và kiosk khác thiết bị |
| S29 | S29, S29.1 | Đơn giá ca tách khỏi kỳ lương |
| A1 | A1, A1.1 | Plan task 4.1 có màn bật 2FA lần đầu |

---

## 5. Quyết định đã chốt (21/10/2026)

1. **S34** để trống, không dùng lại.
2. **URL Site tiếng Việt** (`/dang-nhap`, `/tai-khoan`, `/dieu-khoan`, `/dich-vu`, `/dat-lich`, `/lich-hen/{public_id}`, `/san-pham`); CMS, Admin tiếng Anh; route name luôn tiếng Anh.
3. **Mã mới** S36, S37, S39, S40, S41, S42, S43, S44, K4 như bảng trên. **S38 đã bỏ**: nhân viên luôn thuộc shop (admin tạo shop → chủ shop → cơ sở → mời nhân viên), user không còn dòng `staff` active ở shop nào thì bị chặn ngay ở S36.
4. **Mời admin nền tảng:** chọn lời mời, có bảng `platform_admin_invitations` (db-schema 2.1) và màn A7. Admin đầu tiên vẫn tạo bằng lệnh artisan.
5. **S19 Bán lẻ:** MVP chỉ dòng gõ tay + thanh toán, dùng khung S18; Claude Design vẽ bản đủ ở đợt 9 (GĐ2).
6. **S27.1 Kiosk:** `/{slug}/kiosk`, ngoài `/app`, không đăng nhập user; thiết bị nhận diện bằng token `attendance_devices` (chủ đăng ký thiết bị, token lưu cookie dài hạn).

Bổ sung 27/10/2026:

7. **S44 Phác đồ tiêm & danh mục vaccine** (CRUD `vaccines`, `care_protocols`, `care_protocol_steps`), làm ở task 6.3. Landing page đổi đề xuất sang **S45** (chờ xác nhận).
8. **S39** ở `/account`, controller `Account\…`, route `account.*`; bỏ chức năng xóa tài khoản của starter kit.
9. Frontend giữ `resources/js/pages/`, `components/`, `layouts/` chữ thường, thư mục con PascalCase theo khu (mục 1.2). Controller CMS ở namespace `App\Http\Controllers\Cms`, route name `app.*`.
10. Màn chưa có task trước đây nay đã gắn task trong plan: S5 khung (4.3), S12.1 (4.3), S12.5 (7.1), S12.6 (4.5, 4.6), S16.1 (5.8), S40 (5.8, 5.10, 5.11), S37 + S39 (Bước 1 / 1.3), S44 (6.3).

`docs/design/design.md` mục 5 trỏ sang file này; mọi mã màn chỉ chốt ở đây.
