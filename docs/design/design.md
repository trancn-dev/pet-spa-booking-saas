# design.md: PawBook (Pet Spa & Vet Booking SaaS)

> File này là input cho **Claude Design**. Hãy đọc hết rồi sinh prototype theo đúng design system, danh sách màn hình và luồng bên dưới. Ưu tiên làm **sâu** các màn hình P0 trước.
> Tên sản phẩm "PawBook" là tên tạm, có thể đổi.
>
> **v2 (17/10/2026):** thêm đa chi nhánh, tiếp nhận + hàng đợi, lượt khám, hóa đơn (MVP), kho + điều chuyển (Giai đoạn 2), chấm công + lương (Giai đoạn 3). Các màn đã duyệt (S1-S14, file `.dc.html`) **giữ nguyên**; chỉ thêm màn mới ở **mục 10** và ghi chú chỗ thay đổi ở màn cũ.

---

## 0. Hướng dẫn cho Claude Design

- **Nền tảng:** web app responsive. App nội bộ (staff app) thiết kế desktop-first (1440px), vẫn dùng được ở tablet 1024px (lễ tân thường dùng iPad). Cổng đặt lịch công khai (portal) thiết kế **mobile-first** (390px).
- (v2) **Web app của khách = mobile-first** (390px, thử cả 360px): mọi màn khách dùng (S6, S14, S21, S25) thiết kế cho điện thoại trước, desktop chỉ là bản giãn rộng một cột. Không có màn khách nào chỉ dùng được trên desktop.
- **Ngôn ngữ UI:** tiếng Việt. Tiền tệ VND (`350.000 ₫`), ngày `dd/MM/yyyy`, giờ 24h `14:30`, tuần bắt đầu Thứ 2.
- **Stack đích:** Laravel + Inertia + Vue 3 + Tailwind CSS. Dùng token ở mục 3 dưới dạng CSS variables / Tailwind theme để dễ chuyển sang code.
- **Icon:** Lucide (nét 1.5px, cỡ 16/20/24).
- **Dữ liệu mẫu:** dùng tên Việt, thú cưng thực tế (xem mục 8). Không dùng lorem ipsum.
- **Bắt buộc thiết kế đủ trạng thái:** loading (skeleton), empty, error, không có quyền (read-only), quá hạn mức gói.
- **Light + dark mode** đều phải có.

---

## 1. Sản phẩm

SaaS **multi-tenant** đặt lịch hẹn cho **pet spa (grooming)** và **phòng khám thú y**. Mỗi tiệm/phòng khám là một **workspace (tenant)** riêng: owner tạo workspace, mời nhân viên, cấu hình dịch vụ, lịch làm việc. Chủ nuôi tự đặt lịch qua link công khai của tiệm.

**Giá trị cốt lõi**
1. Đặt lịch không bao giờ trùng (một nhân viên không bị đặt 2 lịch chồng nhau).
2. Thời lượng dịch vụ tự tính theo loài/giống/cỡ thú cưng (chó nhỏ 45', chó lớn 90').
3. Tự nhắc hẹn và **nhắc tiêm phòng / tẩy giun** theo phác đồ.
4. Hồ sơ thú cưng + y tế tập trung, phân quyền chặt.

**Cảm giác thương hiệu:** ấm áp, sạch sẽ, đáng tin như phòng khám, nhưng thân thiện như tiệm spa. Tránh trẻ con/hoạt hình quá đà; icon dấu chân chỉ dùng điểm xuyết (logo, empty state).

---

## 2. Persona & vai trò

| Vai trò | Ai | Mục tiêu chính | Thiết bị | Quyền nổi bật |
|---|---|---|---|---|
| **Owner** | Chủ tiệm, 30-45 tuổi | Nắm doanh thu, lịch tổng, quản lý nhân viên, gói cước | Laptop, điện thoại | Toàn quyền, billing, mời/khóa nhân viên |
| **Lễ tân (Receptionist)** | Nhân viên quầy | Nhận khách, đặt/dời/hủy lịch nhanh, check-in | iPad / PC quầy | Đặt lịch, sửa chủ nuôi/pet; **chỉ xem** hồ sơ y tế |
| **Bác sĩ (Vet)** | Bác sĩ thú y | Xem lịch của mình, ghi khám, ghi mũi tiêm | PC phòng khám, tablet | **Duy nhất được sửa** hồ sơ y tế và ghi tiêm phòng |
| **Groomer** | Thợ cắt tỉa | Xem lịch hôm nay, đánh dấu hoàn thành | Điện thoại | Xem lịch của mình, ghi chú dịch vụ |
| **Chủ nuôi (Pet owner)** | Khách hàng; đăng nhập portal bằng **SĐT + OTP SMS** (v2) | Đặt/dời lịch, xem lịch sử khám, nhận nhắc | Điện thoại | Chỉ portal công khai |
| **Bảo vệ, lao công, phụ việc** (v2) | Nhân viên không có tài khoản | Chấm công | Kiosk ở quầy | Chỉ chấm công bằng mã NV + PIN |

Một user có thể làm ở **nhiều workspace** → cần **workspace switcher**.
v2: một workspace (một chủ) có **nhiều chi nhánh** → cần **branch switcher** cạnh tên workspace (mục 10.1).

UI phải phản ánh quyền: nút bị ẩn hoặc disabled kèm tooltip "Chỉ bác sĩ được chỉnh sửa", không để người dùng bấm rồi mới báo lỗi.

---

## 3. Design system

### 3.1 Màu (tokens)

Primary là teal (sạch, y tế); accent là coral ấm (thân thiện, spa). Neutral dùng stone (ấm hơn gray).

| Token | Light | Dark | Dùng cho |
|---|---|---|---|
| `--color-bg` | `#FAFAF9` | `#0F1514` | nền trang |
| `--color-surface` | `#FFFFFF` | `#161E1D` | card, panel, modal |
| `--color-surface-muted` | `#F5F5F4` | `#1D2826` | nền phụ, hàng xen kẽ, sidebar |
| `--color-border` | `#E7E5E4` | `#2A3735` | viền, divider |
| `--color-text` | `#1C1917` | `#ECF1F0` | chữ chính |
| `--color-text-muted` | `#57534E` | `#9DB0AD` | chữ phụ, label |
| `--color-text-subtle` | `#A8A29E` | `#6B7F7C` | placeholder, disabled |
| `--color-primary` | `#0D9488` | `#2DD4BF` | nút chính, link, focus |
| `--color-primary-hover` | `#0F766E` | `#5EEAD4` | hover |
| `--color-primary-soft` | `#CCFBF1` | `#123A36` | nền chip/badge primary, ô lịch được chọn |
| `--color-on-primary` | `#FFFFFF` | `#042F2E` | chữ trên nút primary |
| `--color-accent` | `#F97362` | `#FB8A7B` | điểm nhấn, CTA portal, badge "mới" |
| `--color-accent-soft` | `#FFE4DF` | `#3B201C` | nền accent |
| `--color-success` | `#16A34A` | `#4ADE80` | hoàn thành, đã gửi |
| `--color-warning` | `#D97706` | `#FBBF24` | sắp đến hạn tiêm, gần hết quota |
| `--color-danger` | `#DC2626` | `#F87171` | hủy, lỗi, quá hạn tiêm, xóa |
| `--color-info` | `#2563EB` | `#60A5FA` | thông tin, đã check-in |
| `--focus-ring` | `0 0 0 3px rgb(13 148 136 / .35)` | `0 0 0 3px rgb(45 212 191 / .4)` | mọi phần tử focus được |

**Màu trạng thái lịch hẹn** (dùng nhất quán ở calendar, badge, list):

| Status | Nhãn UI | Màu |
|---|---|---|
| `pending` | Chờ xác nhận | warning (viền nét đứt) |
| `confirmed` | Đã xác nhận | primary |
| `checked_in` | Đã đến | info |
| `completed` | Hoàn thành | success (block lịch nhạt đi 40%) |
| `cancelled` | Đã hủy | text-subtle, chữ gạch ngang |
| `no_show` | Không đến | danger |

**Màu nhân viên** (`staff.color`, viền trái block lịch + avatar ring): `#0D9488`, `#F97362`, `#8B5CF6`, `#0EA5E9`, `#EAB308`, `#EC4899`, `#22C55E`, `#64748B`.

**Màu loại dịch vụ:** grooming = accent (coral), medical = primary (teal), boarding = `#8B5CF6` (tím).

Tương phản tối thiểu WCAG AA (4.5:1 cho chữ thường). Trạng thái không bao giờ chỉ truyền đạt bằng màu: luôn kèm icon hoặc chữ.

### 3.2 Typography

- **Font:** `Be Vietnam Pro` (hỗ trợ dấu tiếng Việt tốt) cho toàn bộ UI; `JetBrains Mono` cho mã (mã lịch hẹn, số lô vaccine).
- Số trong bảng, giờ, tiền: `font-variant-numeric: tabular-nums`.

| Token | Size / line-height | Weight | Dùng cho |
|---|---|---|---|
| `display` | 32 / 40 | 700 | Số KPI lớn, tiêu đề portal |
| `h1` | 24 / 32 | 600 | Tiêu đề trang |
| `h2` | 20 / 28 | 600 | Tiêu đề section, modal |
| `h3` | 16 / 24 | 600 | Tiêu đề card |
| `body` | 14 / 20 | 400 | Chữ mặc định (app) |
| `body-lg` | 16 / 24 | 400 | Chữ mặc định portal mobile (tránh iOS zoom input) |
| `label` | 13 / 18 | 500 | Label form, header bảng |
| `caption` | 12 / 16 | 400 | Chú thích, timestamp, giờ trên lưới lịch |

### 3.3 Spacing, radius, shadow

- **Spacing scale (4px base):** 2, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64.
- Padding card 16 (mobile) / 24 (desktop). Gap giữa field form 16. Gap giữa section 32.
- **Radius:** `sm 6px` (input, badge), `md 10px` (button, card), `lg 16px` (modal, sheet), `full` (avatar, chip).
- **Shadow:** `sm 0 1px 2px rgb(0 0 0/.05)` (card), `md 0 4px 12px rgb(0 0 0/.08)` (dropdown, popover), `lg 0 12px 32px rgb(0 0 0/.12)` (modal). Dark mode thay shadow bằng viền `--color-border`.
- **Chiều cao control:** 36px (app), 44px (portal mobile, touch target).
- **Motion:** 150ms ease-out cho hover/press, 200ms cho mở drawer/modal. Tôn trọng `prefers-reduced-motion`.

### 3.4 Layout

- **Staff app:** sidebar trái 248px (thu gọn 64px), topbar 56px chứa workspace switcher, ô tìm kiếm toàn cục (chủ nuôi, SĐT, tên pet; phím tắt `/`), nút "+ Đặt lịch" (phím tắt `N`), chuông thông báo, avatar. Nội dung max-width 1280px, trừ màn Lịch dùng full width.
- **Sidebar items:** Tổng quan · Lịch hẹn · Khách hàng & Thú cưng · Tiêm phòng · Dịch vụ · Nhân viên · Báo cáo (owner) · Cài đặt.
- **Sidebar v2** (chèn thêm, giữ thứ tự cũ): Tổng quan · **Tiếp nhận & Hàng đợi** · Lịch hẹn · **Thu ngân** · Khách hàng & Thú cưng · Tiêm phòng · Dịch vụ · **Kho** (GĐ2) · Nhân viên (thêm tab **Xếp ca**, **Chấm công**: GĐ3) · **Lương** (owner, GĐ3, icon khóa) · Báo cáo · Cài đặt (thêm **Chi nhánh**).
- Tablet < 1024px: sidebar thành drawer. Mobile < 640px: bottom nav 4 mục (Hôm nay, Lịch, Khách, Thêm).
- **Portal:** một cột, max-width 480px, header có logo + tên tiệm, footer "Đặt lịch bởi PawBook".
- **(v2) Khung web app khách trên điện thoại** (sau khi đăng nhập OTP): header 56px (logo tiệm, chi nhánh đang chọn, avatar), **bottom nav 4 mục** cố định: **Trang chủ** (lịch sắp tới + nút "Đặt lịch") · **Lịch hẹn** · **Thú cưng** (hồ sơ, lịch sử khám/spa, lịch tiêm) · **Tài khoản**. Mục "Sản phẩm" (GĐ2) vào từ Trang chủ. Chừa vùng an toàn iOS (`env(safe-area-inset-bottom)`), CTA chính dính đáy phía trên bottom nav.

### 3.5 Component

| Component | Biến thể / ghi chú |
|---|---|
| **Button** | primary, secondary (viền), ghost, danger, link; size sm/md/lg; trạng thái loading (spinner thay icon, giữ nguyên width), disabled kèm tooltip lý do |
| **Input / Select / Textarea** | label trên, helper text dưới, error đỏ kèm icon; input SĐT có tiền tố `+84`; input cân nặng có hậu tố `kg` |
| **Combobox tìm kiếm** | tìm chủ nuôi theo tên/SĐT, hiển thị avatar + SĐT + số pet; dòng cuối "+ Tạo chủ nuôi mới" |
| **Date picker / Time slot picker** | lịch tháng, ngày đóng cửa gạch chéo; slot giờ dạng lưới chip 15 phút, chip disabled khi hết chỗ |
| **Badge** | status lịch hẹn (mục 3.1), loại dịch vụ, vai trò, trạng thái tiêm (Đúng hạn / Sắp đến hạn / Quá hạn) |
| **Avatar** | nhân viên (ring màu staff), thú cưng (icon loài nếu không có ảnh: chó, mèo) |
| **Pet chip** | avatar + tên + loài/giống + cỡ (`S`, `M`…) + cân nặng; dùng trong form và block lịch |
| **Card** | tiêu chuẩn, KPI card (số lớn + delta % + sparkline), card cảnh báo |
| **Table** | header sticky, sort, hàng hover, action cuối hàng (menu `…`), phân trang, chọn nhiều |
| **Calendar block** | block lịch hẹn: viền trái màu staff, nền theo loại dịch vụ (soft), dòng 1 giờ + tên pet, dòng 2 dịch vụ, icon trạng thái; phần buffer dọn dẹp hiển thị sọc chéo nhạt ở cuối block |
| **Drawer phải** | 480px, mở chi tiết lịch hẹn / pet mà không rời màn lịch |
| **Modal** | xác nhận hủy, dời lịch; luôn có nút thoát và phím Esc |
| **Toast** | success/error/info, góc phải dưới, tự ẩn 5s, có "Hoàn tác" khi hủy lịch |
| **Banner** | cảnh báo quota gói, trial sắp hết, tài khoản bị tạm khóa |
| **Empty state** | minh họa đơn giản + 1 câu + 1 CTA |
| **Skeleton** | cho bảng, card, lưới lịch |
| **Stepper** | dùng ở portal đặt lịch (4 bước) |
| **Timeline** | lịch sử dịch vụ / y tế / tiêm của pet |
| **Tabs, Segmented control** | Ngày / Tuần / Theo nhân viên ở màn Lịch |

---

## 4. Kiến trúc thông tin

```
Auth
├─ Đăng nhập / Đăng ký
├─ Tạo workspace (onboarding)
└─ Nhận lời mời

Staff app (/app/{workspace})
├─ Tổng quan (dashboard)
├─ Lịch hẹn
│   ├─ Lịch (ngày / tuần / theo nhân viên)
│   ├─ Danh sách lịch hẹn
│   └─ Đặt lịch mới (drawer/modal)
├─ Khách hàng & Thú cưng
│   ├─ Danh sách chủ nuôi
│   ├─ Chi tiết chủ nuôi
│   └─ Hồ sơ thú cưng (Tổng quan · Lịch sử · Y tế · Tiêm phòng)
├─ Tiêm phòng (danh sách đến hạn / quá hạn)
├─ Dịch vụ (danh sách + quy tắc thời lượng)
├─ Nhân viên (danh sách, lời mời, lịch làm việc, ngày nghỉ)
├─ Báo cáo (owner)
└─ Cài đặt (thông tin tiệm, giờ mở cửa & ngày nghỉ lễ, thông báo, portal, gói cước)

Portal công khai (/{slug})
├─ Trang tiệm + chọn dịch vụ
├─ Chọn thú cưng / nhập thông tin
├─ Chọn nhân viên & giờ
├─ Xác nhận
└─ Trang quản lý lịch hẹn (link trong tin nhắn: xem / hủy / dời)
```

---

## 5. Danh sách màn hình MVP

Ưu tiên: **P0** = phải có trong prototype đầu tiên, **P1** = làm sau.

| # | Màn hình | Ưu tiên | Vai trò |
|---|---|---|---|
| S1 | Lịch hẹn (calendar) | P0 | Tất cả staff |
| S2 | Đặt lịch mới (staff) | P0 | Lễ tân, Owner |
| S3 | Chi tiết lịch hẹn (drawer) | P0 | Tất cả staff |
| S4 | Hồ sơ thú cưng | P0 | Tất cả staff (quyền khác nhau) |
| S5 | Dashboard tổng quan | P0 | Owner (bản rút gọn cho staff) |
| S6 | Portal đặt lịch (mobile, 4 bước) | P0 | Chủ nuôi |
| S7 | Tiêm phòng đến hạn | P0 | Bác sĩ, Lễ tân |
| S8 | Lịch làm việc & ngày nghỉ nhân viên | P1 | Owner, chính nhân viên |
| S9 | Dịch vụ + quy tắc thời lượng | P1 | Owner |
| S10 | Danh sách / chi tiết chủ nuôi | P1 | Lễ tân |
| S11 | Nhân viên & lời mời | P1 | Owner |
| S12 | Cài đặt workspace + Gói cước | P1 | Owner |
| S13 | Onboarding tạo workspace | P1 | Owner |
| S14 | Portal: quản lý lịch hẹn (hủy/dời) | P1 | Chủ nuôi |

### S1. Lịch hẹn (calendar)
- Toolbar: `‹ Hôm nay ›`, chọn ngày, segmented **Ngày / Tuần / Theo nhân viên**, filter nhân viên (multi-select avatar), filter loại dịch vụ, nút "+ Đặt lịch".
- **Chế độ "Theo nhân viên" (mặc định của lễ tân):** mỗi cột = 1 nhân viên (avatar + tên + số lịch hôm nay), trục dọc giờ 07:00-21:00, bước lưới 15 phút, vạch đỏ "bây giờ".
- Vùng ngoài ca làm việc của nhân viên tô xám sọc; giờ nghỉ (time off) hiển thị block xám "Nghỉ phép". Ngày tiệm đóng cửa: overlay toàn màn "Tiệm nghỉ: Quốc khánh 2/9".
- Click ô trống → mở S2 đã điền sẵn nhân viên + giờ. Click block → mở S3 drawer.
- Kéo-thả block để dời lịch: khi kéo, ô hợp lệ sáng primary-soft, ô xung đột viền đỏ + tooltip "Trùng lịch với Mochi 14:00".
- Groomer trên mobile: chỉ thấy cột của mình, dạng list theo giờ.

### S2. Đặt lịch mới (staff)
Drawer phải 480px, một form dọc theo thứ tự:
1. **Chủ nuôi**: combobox tìm theo SĐT/tên; hoặc tạo nhanh (tên + SĐT).
2. **Thú cưng**: chọn từ pet của chủ nuôi (pet chip) hoặc thêm nhanh (tên, loài, giống, cân nặng → gợi ý cỡ).
3. **Dịch vụ**: list có giá + thời lượng **đã tính theo pet đã chọn**, kèm giải thích nhỏ: "90 phút · áp dụng cho Chó cỡ L". Đổi pet thì thời lượng cập nhật.
4. **Nhân viên**: "Bất kỳ ai phù hợp" (mặc định) hoặc chọn cụ thể (chỉ hiện nhân viên làm được dịch vụ này).
5. **Ngày & giờ**: date picker + lưới slot trống. Slot hiển thị giờ bắt đầu; tooltip giờ kết thúc (đã cộng buffer).
6. Ghi chú, kênh nhắc (Email / Telegram / Zalo, tick sẵn theo thông tin chủ nuôi).
- Thanh tóm tắt dính đáy: `Mochi · Tắm + cắt tỉa · 90' · 450.000 ₫ · 14:00-15:30 T5 16/10` + nút "Xác nhận đặt lịch".
- **Trạng thái quan trọng:**
  - Slot vừa bị người khác đặt (race condition ở server): toast lỗi "Khung giờ 14:00 vừa có người đặt", lưới slot tự refresh, gợi ý 3 slot gần nhất.
  - Pet đã có lịch trùng giờ: cảnh báo inline.
  - Nút submit có loading và chặn double-click.
  - Hết quota gói: banner + nút disabled "Đã đạt 500/500 lịch tháng này · Nâng cấp".

### S3. Chi tiết lịch hẹn (drawer)
- Header: mã lịch (mono, vd `#01JA8…`), badge status, menu `…` (Dời, Hủy, In phiếu).
- Khối pet (pet chip lớn, dị ứng nổi bật bằng badge danger "Dị ứng: dầu gội có hương liệu"), khối chủ nuôi (SĐT click-to-call, nút Telegram).
- Dịch vụ, nhân viên, giờ, giá (snapshot, ghi chú nhỏ "giá tại thời điểm đặt").
- **Action chính theo status:** `confirmed` → "Check-in"; `checked_in` → "Hoàn thành"; với bác sĩ thêm "Ghi khám" mở form hồ sơ y tế liên kết lịch hẹn.
- Lịch sử nhắc: "Đã gửi email nhắc 24h · 15/10 14:00", "Telegram thất bại · Thử lại".
- Hủy: modal chọn lý do + toggle "Gửi thông báo cho khách"; toast có "Hoàn tác".

### S4. Hồ sơ thú cưng
- Header: ảnh/avatar loài, tên, loài · giống · giới tính · tuổi (tính từ ngày sinh) · cân nặng · cỡ · đã triệt sản; chủ nuôi (link). Badge cảnh báo dị ứng. Nút "Đặt lịch cho bé".
- Tabs:
  - **Tổng quan:** lịch hẹn sắp tới, mũi tiêm sắp đến hạn, ghi chú.
  - **Lịch sử dịch vụ:** timeline các lần grooming/khám.
  - **Y tế:** timeline `medical_records` (ngày, bác sĩ, chẩn đoán, điều trị). **Bác sĩ:** có nút "Thêm ghi khám", sửa được. **Lễ tân/groomer:** read-only, biểu tượng khóa + chú thích "Chỉ bác sĩ được chỉnh sửa".
  - **Tiêm phòng & tẩy giun:** bảng theo phác đồ: vaccine/thuốc, mũi số, ngày tiêm, người tiêm, số lô, **ngày đến hạn kế tiếp** + badge (Đúng hạn / Sắp đến hạn ≤7 ngày / Quá hạn). Mini timeline tiến độ phác đồ (vd 3/4 mũi). Bác sĩ có nút "Ghi mũi tiêm": chọn vaccine → hệ thống hiển thị trước "Mũi kế tiếp dự kiến: 06/11/2026".

### S5. Dashboard tổng quan
- Hàng KPI (4 card): Lịch hôm nay (đã đến / tổng), Doanh thu dự kiến hôm nay, Tỉ lệ no-show 30 ngày, Mũi tiêm đến hạn 7 ngày tới.
- **Lịch hôm nay** dạng list theo giờ, nhóm theo nhân viên, nút check-in nhanh.
- **Tiêm phòng đến hạn / quá hạn**: 5 dòng đầu + "Xem tất cả" (S7).
- Biểu đồ lịch hẹn 14 ngày (cột chồng theo loại dịch vụ). Top dịch vụ.
- **Usage gói** (owner): thanh tiến độ "Lịch tháng 10: 412/500", "Nhân viên: 4/5"; vàng khi ≥80%, đỏ khi 100%.
- Nhắc gửi thất bại (nếu có): card cảnh báo + "Xem".
- Lễ tân/groomer/bác sĩ thấy bản rút gọn: không có doanh thu và usage gói.

### S6. Portal đặt lịch (mobile, chủ nuôi)
Stepper 4 bước, nút chính dính đáy (44px, màu accent):
1. **Chọn dịch vụ**: header ảnh tiệm, địa chỉ, giờ mở cửa; list dịch vụ công khai dạng card (tên, mô tả ngắn, "từ 250.000 ₫", "45-90 phút").
2. **Thú cưng của bạn**: loài (chó/mèo, chip lớn), giống (combobox), cân nặng → hiển thị thời lượng + giá chính xác. Nhập SĐT trước: nếu tiệm đã có hồ sơ thì gợi ý chọn pet đã có (chỉ hiện tên pet, không lộ thông tin khác).
3. **Chọn giờ**: nhân viên ("Ai cũng được" mặc định, hoặc avatar), dải ngày ngang 14 ngày (ngày đóng cửa disabled), lưới slot. Empty: "Hết chỗ ngày này · Xem ngày gần nhất còn trống".
4. **Xác nhận**: tóm tắt, họ tên, SĐT, email (tùy chọn), nhận nhắc qua (Email/Telegram/Zalo), ghi chú, checkbox đồng ý chính sách hủy.
- **Thành công**: check lớn, mã lịch, "Thêm vào lịch" (.ics), "Nhận nhắc qua Telegram" (deep link), link quản lý lịch hẹn.
- **Lỗi slot vừa hết**: quay lại bước 3 với thông báo thân thiện, slot đã chọn bị gạch, gợi ý giờ gần nhất.
- Hiển thị giờ theo **múi giờ của tiệm** và ghi rõ nếu khác thiết bị: "Giờ theo tiệm (GMT+7)".

### S7. Tiêm phòng đến hạn
- Tabs: **Quá hạn** (danger) · **7 ngày tới** (warning) · **30 ngày tới**.
- Bảng: pet (chip), chủ nuôi + SĐT, vaccine/thuốc, mũi số, ngày đến hạn, trạng thái nhắc (Chưa gửi / Đã gửi 12/10 / Thất bại), action: "Đặt lịch tiêm", "Gửi nhắc lại", "Đánh dấu đã tiêm ở nơi khác".
- Filter: loài, loại (vaccine / tẩy giun), bác sĩ.

### S8. Lịch làm việc & ngày nghỉ nhân viên (P1)
- Lưới tuần (T2-CN) cho từng nhân viên, mỗi ngày có thể nhiều ca (ca gãy, vd 08:00-12:00 + 14:00-19:00). Thêm/xóa ca bằng chip. "Áp dụng từ ngày…".
- Danh sách ngày nghỉ: khoảng thời gian, lý do; khi thêm ngày nghỉ trùng lịch hẹn đã có → cảnh báo "3 lịch hẹn bị ảnh hưởng" + link xử lý.
- Ngày đóng cửa toàn tiệm (Cài đặt) hiển thị trên cùng lịch.

### S9. Dịch vụ + quy tắc thời lượng (P1)
- Danh sách dịch vụ: tên, loại (badge màu), thời lượng mặc định, buffer, giá, công khai trên portal (toggle), đang hoạt động.
- Chi tiết dịch vụ: bảng **quy tắc thời lượng/giá** (loài, giống, cỡ → phút, giá, độ ưu tiên) + ô "Thử tính": chọn loài/giống/cỡ → hiển thị quy tắc được áp dụng. Nhân viên làm được dịch vụ này (multi-select).

### S10-S14 (P1, bố cục chuẩn)
- **S10** Chủ nuôi: bảng (tên, SĐT, số pet, lần đến gần nhất, tổng chi). Chi tiết: thông tin liên hệ, danh sách pet, lịch sử hẹn.
- **S11** Nhân viên: bảng (avatar màu, tên, vai trò, nhận đặt lịch on/off, trạng thái), tab Lời mời (email, vai trò, hết hạn, gửi lại/thu hồi). Khi đạt giới hạn gói: nút "Mời" disabled + link nâng cấp.
- **S12** Cài đặt: thông tin tiệm (tên, slug portal `pawbook.vn/ten-tiem` có kiểm tra trùng, múi giờ, tiền tệ), giờ mở cửa & ngày lễ, mẫu tin nhắc, Gói cước (so sánh gói, gói hiện tại, usage, hóa đơn).
- **S13** Onboarding 3 bước: tên tiệm + loại hình (spa / thú y / cả hai) → giờ mở cửa → dịch vụ mẫu (tick chọn) → vào app với checklist "Mời nhân viên, chia sẻ link đặt lịch".
- **S14** Portal quản lý lịch: xem chi tiết, Dời (về bước 3 của S6), Hủy (lý do, cảnh báo nếu trong khung không được hủy).

---

## 6. Luồng chính

### F1. Lễ tân đặt lịch qua điện thoại (mục tiêu: < 60 giây)
1. Bấm `N` hoặc "+ Đặt lịch" ở bất kỳ đâu → S2.
2. Gõ 4 số cuối SĐT → chọn chủ nuôi → pet "Lu" (Poodle, 6kg, cỡ S) tự chọn nếu chỉ có 1 pet.
3. Chọn "Tắm + cắt tỉa" → thời lượng tự thành 60' cho cỡ S.
4. Để "Bất kỳ ai phù hợp", chọn ngày → slot 14:00.
5. Xác nhận → toast "Đã đặt lịch cho Lu lúc 14:00 T5 16/10 với Ngọc Anh" + block mới nhấp nháy nhẹ trên S1.
- Nhánh lỗi: slot bị lấy mất → lưới refresh, gợi ý slot gần nhất, giữ nguyên các field đã nhập.

### F2. Chủ nuôi tự đặt qua portal
Mở link từ Facebook/Zalo của tiệm → S6 bước 1 → 4 → màn thành công → nhận email/Telegram xác nhận → 24h trước nhận nhắc có link S14.

### F3. Một ngày ở quầy (lịch nhân viên)
S1 chế độ "Theo nhân viên" → khách đến → click block → "Check-in" (block đổi màu info) → groomer xong bấm "Hoàn thành" trên điện thoại → block nhạt đi. Khách gọi dời lịch → kéo block sang cột/giờ khác → modal xác nhận "Dời Mochi từ 14:00 sang 16:00, gửi thông báo cho khách?".

### F4. Khám và ghi mũi tiêm (bác sĩ)
S1 → block lịch khám → "Ghi khám" → form chẩn đoán/điều trị → tab Tiêm phòng → "Ghi mũi tiêm" (Vaccine 7 bệnh, mũi 2, số lô) → hệ thống hiển thị "Mũi 3 đến hạn 06/11/2026, sẽ nhắc chủ nuôi trước 7 ngày" → lưu.
- Lễ tân mở cùng hồ sơ: thấy dữ liệu nhưng mọi nút sửa bị khóa.

### F5. Theo dõi tiêm phòng đến hạn
Dashboard card "Mũi tiêm đến hạn: 12" → S7 tab "Quá hạn" → gọi khách → "Đặt lịch tiêm" (mở S2 điền sẵn pet + dịch vụ tiêm) hoặc "Gửi nhắc lại".

### F6. Owner xem dashboard & quản lý gói
S5 → thấy banner "Đã dùng 82% lịch hẹn tháng này" → Cài đặt › Gói cước → so sánh gói → nâng cấp (Stripe Checkout) → quay lại với toast thành công.

### F7. Mời nhân viên & chuyển workspace
Owner mời email + vai trò → nhân viên mở email → đăng nhập/đăng ký → vào workspace. Người làm ở 2 tiệm dùng switcher ở topbar; tên + logo workspace hiện tại luôn hiển thị rõ để tránh thao tác nhầm tiệm.

---

## 7. Quy tắc UX đặc thù (bắt buộc thể hiện trong thiết kế)

1. **Tách biệt tenant rõ ràng:** tên workspace luôn hiển thị ở topbar; đổi workspace có màn chuyển tiếp; không bao giờ trộn dữ liệu 2 tiệm trên một màn.
2. **Chống double-booking ở UI:** slot hết chỗ disabled; khi server từ chối thì báo lỗi thân thiện + gợi ý thay thế, không mất dữ liệu form; nút submit chặn bấm 2 lần.
3. **Thời lượng động minh bạch:** luôn cho biết vì sao thời lượng/giá là X ("áp dụng cho Chó cỡ L").
4. **Múi giờ:** mọi giờ hiển thị theo giờ tiệm; nếu thiết bị khác múi giờ thì ghi chú.
5. **Phân quyền nhìn thấy được:** read-only có biểu tượng khóa và giải thích, không ẩn dữ liệu mà người đó được xem.
6. **Thông tin an toàn sức khỏe nổi bật:** dị ứng, ghi chú "cắn người", "sợ máy sấy" hiển thị dạng badge ở mọi nơi có pet (block lịch, drawer, hồ sơ).
7. **Portal không lộ dữ liệu:** URL dùng mã ngẫu nhiên, không hiển thị thông tin của khách khác; gợi ý pet theo SĐT chỉ hiện tên.
8. **Accessibility:** điều hướng bàn phím đầy đủ trên lịch, focus ring rõ, `aria-live` cho toast và lỗi slot, touch target ≥ 44px trên mobile.

---

## 8. Dữ liệu mẫu

**Tiệm:** "Paw House Spa & Vet", slug `paw-house`, Quận 3, TP.HCM, múi giờ `Asia/Ho_Chi_Minh`, mở cửa 08:00-20:00, nghỉ trưa không có, đóng cửa 02/09.

**Nhân viên:**
| Tên | Vai trò | Màu |
|---|---|---|
| Trần Minh Khoa | Owner + Bác sĩ | `#0D9488` |
| BS. Lê Thu Hà | Bác sĩ | `#8B5CF6` |
| Nguyễn Ngọc Anh | Groomer | `#F97362` |
| Phạm Quốc Bảo | Groomer | `#0EA5E9` |
| Võ Thị Mai | Lễ tân | `#EAB308` |

**Dịch vụ:**
| Dịch vụ | Loại | Thời lượng | Giá |
|---|---|---|---|
| Tắm + sấy | grooming | S 45' · M 60' · L 90' | 200.000-400.000 ₫ |
| Tắm + cắt tỉa trọn gói | grooming | S 60' · M 90' · L 120' | 350.000-650.000 ₫ |
| Cắt móng + vệ sinh tai | grooming | 20' | 80.000 ₫ |
| Khám tổng quát | medical | 30' | 150.000 ₫ |
| Tiêm phòng | medical | 15' | 250.000-450.000 ₫ |
| Lưu chuồng qua đêm | boarding | theo ngày | 200.000 ₫/đêm |

**Chủ nuôi & thú cưng:**
| Chủ nuôi | SĐT | Thú cưng |
|---|---|---|
| Nguyễn Thị Lan | 0903 123 456 | **Mochi** (Poodle, cái, 3 tuổi, 5,2kg, S, dị ứng dầu gội hương liệu); **Bơ** (Mèo Anh lông ngắn, đực, 2 tuổi, 4,8kg) |
| Hoàng Văn Nam | 0912 987 654 | **Lu** (Golden Retriever, đực, 5 tuổi, 32kg, L, "sợ máy sấy") |
| Đặng Mỹ Linh | 0938 555 222 | **Sushi** (Corgi, cái, 6 tháng, 9kg, M, đang tiêm mũi 2/4 vaccine 7 bệnh) |
| Bùi Gia Huy | 0977 246 810 | **Tôm** (Mèo ta, đực, 1 tuổi, 3,5kg, quá hạn tẩy giun 12 ngày) |

**Phác đồ:** Vaccine 7 bệnh (chó): mũi 1 lúc 6 tuần tuổi, mũi 2 +21 ngày, mũi 3 +21 ngày, nhắc lại mỗi 365 ngày. Tẩy giun: lặp mỗi 90 ngày.

**Gói:** Starter (2 nhân viên, 150 lịch/tháng) · **Pro** (5 nhân viên, 500 lịch/tháng, đang dùng) · Clinic (không giới hạn, hồ sơ y tế nâng cao).

**Lịch hôm nay (T5 16/10/2026):** 14 lịch, gồm đủ các status; ít nhất một ca nhân viên có ca gãy, một nhân viên nghỉ buổi chiều, một lịch đang kéo dài có buffer dọn dẹp 15'.

---

## 9. Ngoài phạm vi prototype

~~Thanh toán tại quầy/POS, quản lý kho,~~ (v2: đã đưa vào, xem mục 10) Lưu chuồng chi tiết theo phòng, app native, chat với khách, **bán hàng online / giỏ hàng** (sản phẩm chỉ trưng bày), đa ngôn ngữ ngoài tiếng Việt.

---

## 10. Bổ sung v2: màn mới và màn thay đổi

Chỉ liệt kê bố cục, trạng thái và quy tắc; dùng lại design system mục 3. Ưu tiên: **MVP** làm cùng P0/P1 hiện có; **GĐ2**, **GĐ3** làm sau.

| # | Màn hình | Giai đoạn | Vai trò |
|---|---|---|---|
| 10.1 | Branch switcher + Cài đặt › Chi nhánh | MVP | Tất cả staff / Owner |
| S15 | Tiếp nhận khách (check-in, vãng lai, thú hoang) | MVP | Lễ tân |
| S16 | Bảng hàng đợi hôm nay | MVP | Lễ tân, Bác sĩ, Groomer |
| S17 | Lượt khám / lượt spa | MVP | Bác sĩ, Groomer |
| S18 | Thu ngân: lập hóa đơn, thanh toán | MVP | Lễ tân, Owner |
| S19 | Bán lẻ nhanh tại quầy | MVP (sản phẩm có từ GĐ2) | Lễ tân |
| S20 | Thông báo nhắc gọi khách | MVP | Lễ tân |
| S21 | Portal: đăng nhập OTP + lịch sử khám (**mobile-first**) | MVP | Chủ nuôi |
| S22 | Kho: tồn kho, lô & hạn dùng | GĐ2 | Owner, Lễ tân, Bác sĩ |
| S23 | Tra tồn liên chi nhánh + Điều chuyển | GĐ2 | Lễ tân, Bác sĩ, Owner |
| S24 | Phiếu nhập, nhà cung cấp, kiểm kê | GĐ2 | Owner |
| S25 | Portal: trưng bày sản phẩm (**mobile-first**) | GĐ2 | Chủ nuôi |
| S26 | Xếp ca + phủ ca | GĐ3 | Owner |
| S27 | Kiosk chấm công | GĐ3 | Mọi nhân viên |
| S28 | Bảng công | GĐ3 | Owner |
| S29 | Lương (khóa cấp 2) | GĐ3 | Chỉ Owner |

### 10.1 Branch switcher, Cài đặt › Chi nhánh
- Topbar: `Paw House Spa & Vet › CN Quận 3 ▾`. Nhân viên chỉ thấy chi nhánh mình được phân; Owner có thêm "Tất cả chi nhánh" (chỉ ở Tổng quan, Báo cáo).
- Đổi chi nhánh: toast "Đang làm việc tại CN Quận 7". Mọi màn vận hành (hàng đợi, lịch, thu ngân, kho) hiển thị badge chi nhánh ở tiêu đề để tránh thao tác nhầm.
- Cài đặt › Chi nhánh: bảng (mã, tên, địa chỉ, SĐT, số nhân viên, đang hoạt động), form chi nhánh có mục IP/WiFi chấm công (GĐ3).

### S15. Tiếp nhận khách
- Ô lớn "Nhập SĐT khách" (focus sẵn, phím tắt `F2`). Kết quả: chủ nuôi + danh sách pet (pet chip). Nếu khách có lịch hẹn hôm nay ở chi nhánh này: card nổi bật "Có lịch 14:00 · Tắm + cắt tỉa · Ngọc Anh" + nút **Check-in**.
- Không tìm thấy: form tạo nhanh (họ tên, SĐT bắt buộc) → thêm pet (tên, loài, giống, cân nặng). SĐT là khóa: trùng thì mở hồ sơ cũ, không tạo mới.
- Nút phụ "**Thú hoang / vô chủ**": tạo pet "Thú hoang #12" (sửa tên được), bắt buộc loài + cân nặng ước lượng + ghi chú tình trạng.
- Chọn loại lượt: **Khám bệnh** / **Spa** + lý do đến → "Tạo lượt" → hiện số thứ tự lớn (`Số 042`) + nút In phiếu số.
- Ghi chú: khách **chỉ mua hàng** không đi qua màn này, dùng S19.

### S16. Bảng hàng đợi hôm nay
- Hai cột tab: **Khám bệnh** · **Spa**. Mỗi thẻ: số thứ tự (mono, lớn), pet chip + badge an toàn, giờ đến, loại (Có hẹn 14:00 / Vãng lai), thời gian chờ (đổi warning > 30 phút, danger > 60 phút), người phụ trách.
- Cột trạng thái: Đang chờ · Đang khám · Xong (chờ thanh toán) · Đã thanh toán.
- Bác sĩ/groomer bấm "**Nhận khách**" → mở S17. Nếu người khác vừa nhận: toast "Số 042 đã được BS. Thu Hà nhận", thẻ tự cập nhật.
- Chế độ màn hình TV ở phòng chờ (tùy chọn): chỉ số thứ tự + tên pet, không SĐT.
- Empty: "Chưa có khách nào hôm nay".

### S17. Lượt khám / lượt spa
- Header: số thứ tự, pet (chip lớn + dị ứng), chủ nuôi, lịch sử 3 lần gần nhất (thu gọn).
- **Khám bệnh:** khối Chẩn đoán/Điều trị (= ghi khám, chỉ bác sĩ sửa), khối **Dịch vụ đã làm** (thêm từ danh mục, giá tự điền), khối **Thuốc kê** (GĐ2: ô tìm thuốc hiện tồn tại chi nhánh; hết hàng → dòng "CN Quận 7 còn 12 · Tạo yêu cầu chuyển", mở S23), khối Tiêm phòng (dùng lại S4).
- **Spa:** dịch vụ, ghi chú (dầu gội dùng, tình trạng lông/da), ảnh trước/sau (tùy chọn).
- Footer: tạm tính (chỉ giá bán) + nút "**Hoàn tất lượt**" → trạng thái "Chờ thanh toán", lễ tân nhận thông báo.
- Read-only cho lễ tân (khóa + "Chỉ bác sĩ được chỉnh sửa").

### S18. Thu ngân
- Danh sách "Chờ thanh toán" (từ S16) bên trái; bên phải hóa đơn nháp sinh sẵn từ lượt khám: dòng dịch vụ / thuốc / sản phẩm, giảm giá (theo dòng hoặc cả đơn, có lý do), thuế, tổng.
- Thêm sản phẩm bán kèm (quét mã vạch hoặc tìm tên).
- Thanh toán: chọn cách trả (Tiền mặt / Chuyển khoản / QR / Thẻ), cho phép **nhiều lần trả** (vd 500.000 tiền mặt + phần còn lại QR); tiền mặt có ô "Khách đưa" → "Tiền thối".
- Đã thanh toán: hóa đơn **khóa**, badge "Đã thanh toán", chỉ còn In / Gửi Zalo / **Hủy hóa đơn** (modal: lý do bắt buộc, "Hủy và lập hóa đơn mới"). Hoàn tiền là thao tác riêng.
- Trạng thái: nút thanh toán chặn double-click; hóa đơn vừa bị người khác hủy → banner + tải lại.
- Lễ tân và nhân viên **không bao giờ thấy giá nhập**.

### S19. Bán lẻ nhanh tại quầy
- Kiểu POS: ô quét mã vạch/tìm sản phẩm, giỏ bên phải, thanh toán như S18.
- **Không bắt buộc thông tin khách.** Ô "SĐT khách (không bắt buộc)" để lưu lịch sử mua nếu khách muốn.
- Chỉ bán sản phẩm bán lẻ (thức ăn, đồ chơi, phụ kiện); thuốc kê đơn và dịch vụ không thêm được ở đây, tooltip "Thuốc/dịch vụ phải đi qua lượt khám".

### S20. Thông báo nhắc gọi khách
- Chuông topbar + trang "Cần gọi hôm nay": danh sách lịch hẹn **ngày mai** của chi nhánh, mỗi dòng: giờ, khách, SĐT (click-to-call), pet, dịch vụ, trạng thái nhắc tự động (SMS/Zalo đã gửi / thất bại).
- Action: "Đã gọi · Khách xác nhận", "Dời lịch" (mở modal dời của S3), "Không liên lạc được".
- Thông báo được tạo trước 1 ngày; hiển thị đếm trên chuông.

### S21. Portal: đăng nhập OTP + lịch sử
- Đăng nhập: nhập SĐT → mã OTP 6 số (đếm ngược 60s gửi lại, sai 5 lần khóa 15 phút).
- Trang "Của tôi": pet của tôi, lịch hẹn sắp tới (Dời / Hủy), **lịch sử khám/spa** (ngày, chi nhánh, dịch vụ; chẩn đoán dạng tóm tắt), lịch tiêm sắp tới.
- Dời lịch: nếu còn ít hơn hạn dời (chủ tiệm cấu hình, vd 24h) thì nút Dời disabled + "Vui lòng gọi tiệm để dời lịch trong vòng 24 giờ".
- Thay đổi S6: bước 2 dùng đăng nhập OTP thay cho nhập SĐT tự do khi khách muốn chọn pet đã có.

### S22. Kho (GĐ2)
- Tabs: **Tồn kho** · **Lô & hạn dùng** · **Sổ kho** (lịch sử nhập/xuất).
- Tồn kho: sản phẩm, loại (Thuốc / Vật tư / Bán lẻ), tồn, đang giữ, khả dụng, đơn vị, giá bán; **cột Giá vốn và Giá trị tồn chỉ Owner** (role khác: cột không tồn tại, không chỉ ẩn).
- Badge: "Sắp hết" (warning), "Hết hàng" (danger), "Lô hết hạn trong 30 ngày".
- Lô & hạn dùng: lô, HSD, số lượng; lô quá hạn gạch ngang, không xuất được.

### S23. Tra tồn liên chi nhánh + Điều chuyển (GĐ2)
- Tìm sản phẩm → bảng các chi nhánh: tồn khả dụng, HSD gần nhất, khoảng cách/ghi chú. Chi nhánh hiện tại ở dòng đầu.
- "**Yêu cầu chuyển**" (chọn chi nhánh nguồn, số lượng, gắn khách đang chờ + "hẹn lấy ngày 18/10") → tạo phiếu.
- Danh sách phiếu: tabs Gửi đi / Nhận về; trạng thái **Yêu cầu → Đã duyệt → Đang vận chuyển → Đã nhận / Đã hủy** (stepper ngang trên chi tiết phiếu).
- Chi nhánh nguồn: Duyệt / Từ chối, chọn lô, nhập shipper (tên, SĐT, mã vận đơn). Chi nhánh nhận: "Xác nhận đã nhận" với số lượng thực nhận (lệch thì bắt buộc ghi chú).
- Nhận xong: thông báo cho lễ tân "Thuốc XYZ cho khách Nguyễn Thị Lan đã về, giữ đến 19/10".

### S24. Phiếu nhập, nhà cung cấp, kiểm kê (GĐ2, Owner)
- Phiếu nhập: nhà cung cấp, số hóa đơn NCC, dòng (sản phẩm, lô, HSD, SL, giá nhập), tổng; Lưu nháp / **Ghi sổ** (sau khi ghi sổ không sửa).
- Kiểm kê: chọn chi nhánh, nhập số đếm thực tế, hiển thị chênh lệch, ghi sổ điều chỉnh.

### 10.5 Quy tắc mobile cho web app khách (áp dụng S6, S14, S21, S25)
- Touch target ≥ 44px, chữ body 16px (tránh iOS tự zoom khi focus input), một cột, không bảng ngang: dữ liệu dạng card/list.
- Input: SĐT dùng `inputmode="tel"`; OTP 6 ô dùng `autocomplete="one-time-code"` để iOS/Android tự điền từ SMS; ngày giờ dùng dải chip ngang có thể vuốt thay cho date picker nhỏ.
- Hành động chính luôn dính đáy (sticky), một nút; hành động phụ trong bottom sheet thay cho dropdown/modal giữa màn.
- Lịch sử khám/spa: timeline dọc, mỗi lần một card (ngày, chi nhánh, dịch vụ, tóm tắt chẩn đoán), chạm để mở chi tiết toàn màn.
- Click-to-call SĐT tiệm, nút chỉ đường (mở Google Maps), chia sẻ lịch hẹn (.ics / Zalo).
- **PWA nhẹ:** manifest + icon để "Thêm vào màn hình chính"; không cần offline. Phiên đăng nhập OTP giữ 30 ngày trên thiết bị (**[Mặc định, cần xác nhận]**).
- Hiệu năng trên 4G: trang đầu < 200KB JS, ảnh sản phẩm lazy-load + kích thước theo màn hình, skeleton thay spinner.
- Thiết kế đủ trạng thái trên mobile: chưa đăng nhập, OTP sai/hết hạn, không có pet, không có lịch, mất mạng (toast "Mất kết nối, thử lại").

### S25. Portal: trưng bày sản phẩm (GĐ2)
- Lưới sản phẩm công khai 2 cột trên điện thoại (ảnh, tên, giá bán, "Còn hàng tại CN Quận 3"), lọc theo loại bằng chip ngang. **Không có giỏ hàng / nút mua**; CTA "Đến tiệm mua" + chỉ đường.

### S26. Xếp ca + phủ ca (GĐ3, Owner)
- Lưới tuần: hàng = nhân viên (cả nhân viên không có tài khoản), cột = ngày, ô = chip ca (Ca sáng / Ca chiều / Ca tối; ca tối có icon trăng). Kéo-thả chip để xếp.
- Thanh phủ ca dưới lưới theo từng ngày/ca: "Bác sĩ 1/2 · Lễ tân 1/1 · Bảo vệ 0/1" (thiếu = danger).
- Cài đặt mẫu ca (giờ, hệ số lương) và yêu cầu tối thiểu theo vai trò.

### S27. Kiosk chấm công (GĐ3)
- Màn toàn màn hình trên tablet ở quầy, chữ lớn: đồng hồ, tên chi nhánh, bàn phím số "Mã nhân viên" → "PIN" → "Chào Phạm Quốc Bảo · Vào ca 07:58 · Ca sáng".
- Lỗi: sai PIN, thiết bị chưa đăng ký, mạng không thuộc chi nhánh.

### S28. Bảng công (GĐ3, Owner)
- Bảng tháng: nhân viên × ngày, ô = giờ vào/ra; "Quên chấm ra" badge warning cần duyệt; Owner sửa giờ (bắt buộc lý do, ghi lịch sử).

### S29. Lương (GĐ3, chỉ Owner)
- Vào trang: modal "Nhập lại mật khẩu để xem lương" → mở 10 phút, đồng hồ đếm ngược ở header ("Tự khóa sau 09:12"), nút "Khóa ngay". Hết giờ: nội dung mờ + modal mở lại.
- Bảng kỳ lương: nhân viên, ngày công, giờ ca tối, tăng ca, ngày lễ, lương cơ bản, phụ cấp ca, thưởng/phạt/tạm ứng, **thực lãnh**. Click dòng → chi tiết công thức từng ca.
- Cài đặt lương theo nhân viên: kiểu lương, mức, ngày công chuẩn, hệ số tăng ca/ngày lễ/ca đêm (validate mức tối thiểu theo luật, báo lỗi inline).
- "**Chốt kỳ lương**" (modal xác nhận) → khóa, badge "Đã chốt". Tab "Lịch sử truy cập" (ai mở trang lương, lúc nào).

### 10.2 Thay đổi ở màn đã có (không vẽ lại)
- **S1 Lịch:** lọc theo chi nhánh hiện tại. Block lịch có trạng thái `checked_in` thêm số thứ tự lượt.
- **S3 Chi tiết lịch hẹn:** nút "Check-in" giờ tạo lượt khám và hiện số thứ tự; "Ghi khám" chuyển sang mở S17.
- **S5 Dashboard:** Owner thêm KPI doanh thu thực thu hôm nay, lợi nhuận gộp (GĐ2), lọc chi nhánh. **Lễ tân chỉ xem số liệu hôm nay của chi nhánh mình** (không có bộ chọn ngày).
- **S11 Nhân viên:** thêm nhân viên **không có tài khoản** (vai trò Bảo vệ / Lao công / Phụ việc), cột chi nhánh, nút "Đặt PIN chấm công".
- **S12 Cài đặt:** thêm "Hạn khách tự dời/hủy lịch (giờ)", thuế mặc định, mẫu SMS/Zalo nhắc hẹn.

### 10.3 Luồng mới
- **F8. Một lượt khám từ đầu đến cuối:** S15 nhập SĐT → (có hẹn: Check-in / vãng lai: tạo lượt) → số 042 → S16 bác sĩ "Nhận khách" → S17 ghi khám + dịch vụ + thuốc → "Hoàn tất lượt" → S18 lễ tân thấy hóa đơn nháp → thanh toán 2 lần (tiền mặt + QR) → hóa đơn khóa.
- **F9. Hết thuốc, chi nhánh khác còn (GĐ2):** S17 kê thuốc XYZ → "Hết tại CN Q3 · CN Q7 còn 12" → hẹn khách mai lấy → S23 yêu cầu chuyển (giữ hàng ở Q7) → Q7 duyệt + giao shipper → Q3 xác nhận nhận → thông báo lễ tân, giữ hàng 48h cho khách → khách đến, S18 bán.
- **F10. Khách chỉ mua thức ăn:** S19 quét sản phẩm → (tùy chọn SĐT) → thanh toán. Không tạo hồ sơ.
- **F11. Chấm công và tính lương (GĐ3):** nhân viên chấm ở S27 → Owner xem S28, duyệt "quên chấm ra" → cuối tháng S29 mở khóa, kiểm tra, chốt kỳ.

### 10.4 Dữ liệu mẫu bổ sung
- Chi nhánh: **CN Quận 3** (`Q3`, chi nhánh chính) và **CN Quận 7** (`Q7`).
- Nhân viên không tài khoản: Lê Văn Tư (Bảo vệ, Q3), Nguyễn Thị Sáu (Lao công, Q3).
- Hàng đợi hôm nay ở Q3: 9 lượt (6 khám, 3 spa), trong đó 1 thú hoang "Thú hoang #3" (mèo, ~2kg, bị thương chân).
- Kho: thuốc "Amoxicillin 250mg" hết ở Q3, Q7 còn 12 viên (lô `AMX2409`, HSD 03/2027); dầu gội "Bio-Groom" sắp hết ở Q3; thức ăn "Royal Canin Mini Adult 2kg" giá bán 420.000 ₫.
- Lương: Bảo vệ 6.500.000 ₫/tháng, 26 công chuẩn, ca tối ×1,3, tăng ca ×1,5, ngày lễ ×3.
