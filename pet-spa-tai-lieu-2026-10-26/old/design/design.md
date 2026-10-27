# design.md: PawBook (Pet Spa & Vet Booking SaaS)

> File này là input cho **Claude Design**. Hãy đọc hết rồi sinh prototype theo đúng design system, danh sách màn hình và luồng bên dưới. Ưu tiên làm **sâu** các màn hình P0 trước.
> Tên sản phẩm "PawBook" là tên tạm, có thể đổi.
>
> **v2 (17/10/2026):** thêm đa chi nhánh, tiếp nhận + hàng đợi, lượt khám, hóa đơn (MVP), kho + điều chuyển (Giai đoạn 2), chấm công + lương (Giai đoạn 3). Các màn đã duyệt (S1-S14, file `.dc.html`) **giữ nguyên**; chỉ thêm màn mới ở **mục 10** và ghi chú chỗ thay đổi ở màn cũ.

> **v3 (17/10/2026):** thêm **Trang quản trị nền tảng** cho nhà cung cấp (super-admin) ở mục 11: màn A1-A6, cùng app nhưng giao diện tách biệt khỏi staff app.

> **v4, v5 (19/10/2026):** lô & cận date (S19, S22, S24); bản tổng hợp nghiệp vụ (mục 12).

> **v6 (20/10/2026):** nghiệp vụ user chốt nhóm 1–14 (`analysis/so-do-nghiep-vu/nghiep-vu-da-chot.md`). 7 role mẫu + **ma trận quyền (mục 13)**; lịch hẹn bỏ "Chờ xác nhận" và "Hoàn thành", lịch online không gán người, hạn mức online theo số người trực × tỉ lệ; đặt online 5 bước có OTP; cấp cứu chen hàng đợi; lượt khám độc lập hóa đơn; bệnh án số hồ sơ + dòng chi tiết, đính kèm file; hóa đơn không giảm giá tay, có dòng gõ tay, Owner sửa = bản thay thế; **ca thu ngân (S35)**; trả hàng kiểm tay; tích điểm sau V1; nhắc khách qua Zalo + SMS. Chỗ sửa ghi **(v6)**.

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

SaaS **multi-tenant** đặt lịch hẹn cho **pet spa (grooming)** và **phòng khám thú y**. Mỗi tiệm/phòng khám là một **workspace (tenant)** riêng: (v3) nhà cung cấp tạo shop và mời Owner, Owner mời nhân viên, cấu hình dịch vụ, lịch làm việc. Chủ nuôi tự đặt lịch qua link công khai của tiệm.

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
| **Quản lý chi nhánh** (v6) | Người điều hành một cơ sở | Lịch, ca trực, duyệt kho của cơ sở mình | PC, điện thoại | Như lễ tân + duyệt kho, xếp ca; không thấy giá vốn, lương, không sửa hóa đơn đã hoàn thành |
| **Lễ tân (Receptionist)** | Nhân viên quầy | Nhận khách, đặt/dời/hủy lịch nhanh, check-in | iPad / PC quầy | Đặt lịch, sửa chủ nuôi/pet, thu tiền; (v6) hồ sơ y tế **chỉ thấy cảnh báo + ngày khám** |
| **Thu ngân** (v6) | Nhân viên thu tiền | Thu tiền, mở/đóng ca thu ngân | PC quầy | Hóa đơn chưa hoàn thành, hủy có lý do, trả hàng trong hạn; không thấy bệnh án |
| **Bác sĩ (Vet)** | Bác sĩ thú y | Xem lịch của mình, ghi khám, ghi mũi tiêm | PC phòng khám, tablet | **Duy nhất được ghi** bệnh án (dòng của mình, trong 24h) và ghi tiêm phòng; xem đầy đủ bệnh án mọi cơ sở |
| **Groomer** | Thợ cắt tỉa | Xem lịch hôm nay, nhận và hoàn tất lượt spa | Điện thoại | Xem lịch của mình, ghi chú dịch vụ, ảnh trước/sau; bệnh án chỉ thấy cảnh báo |
| **Nhân viên kho** (v6, GĐ2) | Người nhập/xuất kho | Nhập hàng, kiểm kê, giao nhận chuyển kho | PC | Ghi kho, điều chỉnh cần duyệt; không thấy giá vốn |
| **Chủ nuôi (Pet owner)** | Khách hàng; đăng nhập portal bằng **SĐT + OTP SMS** (v2) | Đặt/dời lịch, xem lịch sử khám, nhận nhắc | Điện thoại | Chỉ Site của tiệm (v3, mục 11.4) |
| **Bảo vệ, lao công, phụ việc** (v2) | Nhân viên không có tài khoản | Chấm công | Kiosk ở quầy | Chỉ chấm công bằng mã NV + PIN |
| **Super-admin / Support** (v3) | Nhà cung cấp PawBook | Tạo shop, gán gói/hạn, khóa, hỗ trợ | Laptop | Chỉ khu `/admin`; không phải user của tiệm (mục 11) |

(v6) Các vai trò trên là **role mẫu**; Owner sửa quyền và tạo role mới (Cài đặt › Phân quyền). Một người nhiều role thì cộng quyền (vd Owner kiêm Bác sĩ). Quyền chi tiết: **mục 13**.

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
| `confirmed` | Đã xác nhận | primary |
| `checked_in` | Đã đến | info (block lịch nhạt đi 40% sau giờ kết thúc) |
| `cancelled` | Đã hủy | text-subtle, chữ gạch ngang |
| `no_show` | Không đến | danger |

(v6) Không có "Chờ xác nhận" và "Hoàn thành": lịch đặt xong là Đã xác nhận, dừng ở Đã đến; tiến độ thực hiện xem ở lượt khám (S16, S17). Lịch online chưa phân người hiện ở cột **"Chưa phân"** với badge "Online".

**Màu nhân viên** (`staff.color`, viền trái block lịch + avatar ring): `#0D9488`, `#F97362`, `#8B5CF6`, `#0EA5E9`, `#EAB308`, `#EC4899`, `#22C55E`, `#64748B`.

**Màu loại dịch vụ:** grooming = accent (coral), medical = primary (teal), boarding = `#8B5CF6` (tím; v5: lưu chuồng ngoài V1, chưa dùng).

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

- **Staff app** (v3 gọi là **CMS**, `pawbook.vn/app/{slug}`): sidebar trái 248px (thu gọn 64px), topbar 56px chứa workspace switcher, ô tìm kiếm toàn cục (chủ nuôi, SĐT, tên pet; phím tắt `/`), nút "+ Đặt lịch" (phím tắt `N`), chuông thông báo, avatar. Nội dung max-width 1280px, trừ màn Lịch dùng full width.
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
Auth nhân viên (route trung tâm pawbook.vn/…, v3: không có Đăng ký)
├─ Đăng nhập · Quên mật khẩu
├─ Nhận lời mời /invite/{token} (Owner do admin mời, nhân viên do Owner mời; chưa có tài khoản thì tạo ngay trên trang)
└─ Chưa thuộc tiệm nào → "Hãy dùng link mời trong email"

CMS của shop (pawbook.vn/app/{slug}, xem 11.4)
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
└─ Cài đặt (thông tin tiệm, giờ mở cửa & ngày nghỉ lễ, thông báo, Site, gói cước)

Quản trị nền tảng (v3, pawbook.vn/admin, mục 11)
├─ Đăng nhập + 2FA
├─ Shop (danh sách · tạo · chi tiết: gói & gia hạn, truy cập hỗ trợ, nhật ký)
├─ Nhật ký hoạt động
└─ Quản lý admin

Site của shop (pawbook.vn/{slug}, xem 11.4; trước gọi là "portal")
├─ Trang chủ shop (giới thiệu, chi nhánh, giờ mở cửa) · Dịch vụ & giá · Sản phẩm (GĐ2)
├─ Đặt lịch: chọn dịch vụ
├─ Chọn thú cưng / nhập thông tin
├─ Chọn nhân viên & giờ
├─ Xác nhận
├─ Trang quản lý lịch hẹn (link trong tin nhắn: xem / hủy / dời)
└─ Tài khoản khách sau đăng nhập OTP (/tai-khoan): lịch hẹn, thú cưng, lịch sử
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
| S13 | (v3) Owner nhận lời mời + checklist lần đầu (thay onboarding tạo workspace) | MVP | Owner |
| S14 | Portal: quản lý lịch hẹn (hủy/dời) | P1 | Chủ nuôi |

### S1. Lịch hẹn (calendar)
- Toolbar: `‹ Hôm nay ›`, chọn ngày, segmented **Ngày / Tuần / Theo nhân viên**, filter nhân viên (multi-select avatar), filter loại dịch vụ, nút "+ Đặt lịch".
- **Chế độ "Theo nhân viên" (mặc định của lễ tân):** mỗi cột = 1 nhân viên (avatar + tên + số lịch hôm nay), trục dọc giờ 07:00-21:00, bước lưới 15 phút, vạch đỏ "bây giờ".
- Vùng ngoài ca làm việc của nhân viên tô xám sọc; giờ nghỉ (time off) hiển thị block xám "Nghỉ phép". Ngày tiệm đóng cửa: overlay toàn màn "Tiệm nghỉ: Quốc khánh 2/9".
- Click ô trống → mở S2 đã điền sẵn nhân viên + giờ. Click block → mở S3 drawer.
- Kéo-thả block để dời lịch: khi kéo, ô hợp lệ sáng primary-soft, ô xung đột viền đỏ + tooltip "Trùng lịch với Mochi 14:00".
- Groomer trên mobile: chỉ thấy cột của mình, dạng list theo giờ.
- (v6) Cột **"Chưa phân"** chứa lịch online chưa gán người (hiện "Mong muốn: BS. Thu Hà" nếu khách ghi). Thanh trên cùng mỗi khung giờ hiện "Khám 3/3 · Spa 1/2" (lịch / số người trực); vượt thì viền warning.

### S2. Đặt lịch mới (staff)
Drawer phải 480px, một form dọc theo thứ tự:
1. **Chủ nuôi**: combobox tìm theo SĐT/tên; hoặc tạo nhanh (tên + SĐT).
2. **Thú cưng**: chọn từ pet của chủ nuôi (pet chip) hoặc thêm nhanh (tên, loài, giống, cân nặng → gợi ý cỡ).
3. **Dịch vụ**: list có giá + thời lượng **đã tính theo pet đã chọn**, kèm giải thích nhỏ: "90 phút · áp dụng cho Chó cỡ L". Đổi pet thì thời lượng cập nhật.
4. **Nhân viên**: (v6) "Chưa phân, phân khi khách đến" (mặc định) hoặc chọn cụ thể (chỉ hiện nhân viên trực và làm được dịch vụ này).
5. **Ngày & giờ**: date picker + lưới slot trống. Slot hiển thị giờ bắt đầu; tooltip giờ kết thúc (đã cộng buffer).
6. Ghi chú. (v6) Nhắc khách tự động qua **Zalo, SMS dự phòng** theo SĐT; ô tick "Không gửi nhắc" nếu khách không muốn.
- Thanh tóm tắt dính đáy: `Mochi · Tắm + cắt tỉa · 90' · 450.000 ₫ · 14:00-15:30 T5 16/10` + nút "Xác nhận đặt lịch". (v6) Giá ghi "Giá tham khảo, có thể thay đổi theo thực tế". Form cho thêm nhiều dòng (pet × dịch vụ), mỗi dòng thành một lịch.
- **Trạng thái quan trọng:**
  - (v6) Khung giờ đã đủ số người trực: cảnh báo vàng "14:00 đã có 3 lịch khám / 3 bác sĩ trực", **vẫn cho lưu** (lễ tân quyết). Chọn người cụ thể mà người đó đã có lịch chồng giờ: cảnh báo inline.
  - Pet đã có lịch trùng giờ: cảnh báo inline.
  - Nút submit có loading và chặn double-click.
  - Hết quota gói: banner + nút disabled "Đã đạt 500/500 lịch tháng này · Nâng cấp".

### S3. Chi tiết lịch hẹn (drawer)
- Header: mã lịch (mono, vd `#01JA8…`), badge status, menu `…` (Dời, Hủy, In phiếu).
- Khối pet (pet chip lớn, dị ứng nổi bật bằng badge danger "Dị ứng: dầu gội có hương liệu"), khối chủ nuôi (SĐT click-to-call, (v6) nút "Nhắn Zalo").
- Dịch vụ, nhân viên, giờ, giá (snapshot, ghi chú nhỏ "giá tại thời điểm đặt").
- **Action chính theo status (v6):** `confirmed` → "Check-in" (tạo lượt, phân người nếu lịch chưa gán), "Không đến", "Dời", "Hủy"; `checked_in` → "Mở lượt khám" (S17). **Không có nút "Hoàn thành"**: hoàn tất làm ở S17.
- (v6) Khối y tế theo quyền: bác sĩ thấy tóm tắt lần khám gần nhất; lễ tân, groomer chỉ thấy **cảnh báo (dị ứng, bệnh nền) + ngày khám gần nhất**; thu ngân không có khối này.
- Lịch sử nhắc: (v6) "Đã gửi Zalo nhắc 24h · 15/10 14:00", "Zalo thất bại → đã gửi SMS", "SMS thất bại · Thử lại".
- Hủy: modal chọn lý do + toggle "Gửi thông báo cho khách"; toast có "Hoàn tác".

### S4. Hồ sơ thú cưng
- Header: ảnh/avatar loài, tên, loài · giống · giới tính · tuổi (tính từ ngày sinh) · cân nặng · cỡ · đã triệt sản; chủ nuôi (link). Badge cảnh báo dị ứng. Nút "Đặt lịch cho bé".
- Tabs:
  - **Tổng quan:** lịch hẹn sắp tới, mũi tiêm sắp đến hạn, ghi chú.
  - **Lịch sử dịch vụ:** timeline các lần grooming/khám. (v5) Mỗi sự kiện ghi ngày + **chi nhánh** + tóm tắt (vd "12/03/2026 · CN Q7 · Điều trị bệnh thận"); người có quyền bấm vào mở bệnh án chi tiết của lần đó, người không có quyền thấy biểu tượng khóa.
  - **Y tế (v6):** đầu tab là **số hồ sơ** (`HS-000123`). Timeline các **dòng chi tiết** (ngày, cơ sở, bác sĩ, chẩn đoán, điều trị, hướng dẫn chăm sóc, thuốc đã kê, lịch tái khám, file đính kèm: kết quả xét nghiệm/ảnh/PDF); khối "Ghi chú nội bộ" nền xám, nhãn "Không hiện cho khách". **Bác sĩ:** xem đầy đủ mọi cơ sở; sửa dòng **do mình lập trong 24h** (đồng hồ "Còn sửa được 5 giờ"), sau đó chỉ "Thêm ghi chú bổ sung". **Lễ tân, groomer, quản lý chi nhánh:** chỉ thấy cảnh báo + danh sách ngày khám, biểu tượng khóa + "Nội dung bệnh án chỉ bác sĩ xem". **Thu ngân, kho:** không có tab này.
  - **Tiêm phòng & tẩy giun:** bảng theo phác đồ: vaccine/thuốc, mũi số, ngày tiêm, người tiêm, số lô, **ngày đến hạn kế tiếp** + badge (Đúng hạn / Sắp đến hạn ≤7 ngày / Quá hạn). Mini timeline tiến độ phác đồ (vd 3/4 mũi). Bác sĩ có nút "Ghi mũi tiêm": chọn vaccine → hệ thống hiển thị trước "Mũi kế tiếp dự kiến: 06/11/2026".

### S5. Dashboard tổng quan
- Hàng KPI (4 card): Lịch hôm nay (đã đến / tổng), Doanh thu dự kiến hôm nay, Tỉ lệ no-show 30 ngày, Mũi tiêm đến hạn 7 ngày tới.
- **Lịch hôm nay** dạng list theo giờ, nhóm theo nhân viên, nút check-in nhanh.
- **Tiêm phòng đến hạn / quá hạn**: 5 dòng đầu + "Xem tất cả" (S7).
- Biểu đồ lịch hẹn 14 ngày (cột chồng theo loại dịch vụ). Top dịch vụ.
- **Usage gói** (owner): thanh tiến độ "Lịch tháng 10: 412/500", "Nhân viên: 4/5"; vàng khi ≥80%, đỏ khi 100%.
- Nhắc gửi thất bại (nếu có): card cảnh báo + "Xem".
- (v6) KPI no-show tính từ job cuối ngày (tự đánh dấu "Không đến"). Owner thấy card "Hóa đơn đã hủy hôm nay" (số lượng, người hủy, lý do) và "Ca thu ngân lệch tiền".
- **(v4, GĐ2)** Card **"Kho cần xử lý"** (chi nhánh đang chọn): 3 số đếm Sắp hết · Hết hàng · Cận date, bấm vào mở tab Cảnh báo ở S22. Vd "Thức ăn A1 còn 8/10 gói", "Dầu gội Bio-Groom còn 1/2 chai", "Amoxicillin lô AMX2409 hết hạn sau 45 ngày, bán/dùng trước". Lễ tân và bác sĩ cũng thấy card này (không có giá vốn).
- Lễ tân/groomer/bác sĩ thấy bản rút gọn: không có doanh thu và usage gói.

### S6. Portal đặt lịch (mobile, chủ nuôi)
(v6) Stepper **5 bước** (theo prototype), nút chính dính đáy (44px, màu accent):
1. **Chọn cơ sở + dịch vụ**: chọn cơ sở (ẩn khi shop có 1 cơ sở); header ảnh tiệm, địa chỉ, giờ mở cửa; list dịch vụ công khai dạng card (tên, mô tả ngắn, "từ 250.000 ₫", "45-90 phút").
2. **Xác minh**: đã đăng nhập thì bỏ qua bước này. Chưa đăng nhập: nhập SĐT → OTP 6 số; SĐT chưa có hồ sơ thì nhập họ tên để **tạo tài khoản** (khách tự đăng ký). Không gợi ý pet trước khi xác minh.
3. **Thú cưng**: chọn pet đã có (sau xác minh) hoặc thêm mới: loài (chip lớn), giống, cân nặng → thời lượng + **giá tham khảo**.
4. **Chọn giờ**: dải ngày ngang 14 ngày (ngày đóng cửa disabled), lưới slot theo **hạn mức online** còn lại. Không chọn nhân viên; ô "Người bạn muốn (không bắt buộc)". Empty: "Hết chỗ ngày này · Xem ngày gần nhất còn trống".
5. **Xác nhận**: tóm tắt, email (tùy chọn), (v6) dòng "Tiệm sẽ nhắc lịch qua Zalo hoặc SMS tới số {SĐT}", ghi chú, dòng "Giá tham khảo, có thể thay đổi theo thực tế", link điều khoản, checkbox đồng ý chính sách hủy. Lịch **xác nhận ngay**.
- **Thành công**: check lớn, mã lịch, "Thêm vào lịch" (.ics), link quản lý lịch hẹn ((v6) cũng gửi trong tin Zalo/SMS xác nhận).
- **Lỗi slot vừa hết**: quay lại bước 4 với thông báo thân thiện, slot đã chọn bị gạch, gợi ý giờ gần nhất.
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
- Danh sách dịch vụ: (v5) **mã**, tên, loại (badge màu), (v5) badge **Combo**, thời lượng mặc định, buffer, giá, công khai trên portal (toggle), đang hoạt động.
- (v5) Form combo: mã, tên, **giá combo riêng** (hiện tổng giá dịch vụ con để tham khảo, "Combo rẻ hơn mua lẻ 50.000 ₫"), chọn dịch vụ con theo thứ tự, thời lượng (gợi ý = tổng con). (v5) Tab **Vật tư định mức**: sản phẩm, lượng theo cỡ pet (S/M/L hoặc mọi cỡ), ghi chú "Chỉ là gợi ý, nhân viên sửa theo thực tế".
- Chi tiết dịch vụ: bảng **quy tắc thời lượng/giá** (loài, giống, cỡ → phút, giá, độ ưu tiên) + ô "Thử tính": chọn loài/giống/cỡ → hiển thị quy tắc được áp dụng. Nhân viên làm được dịch vụ này (multi-select).

### S10-S14 (P1, bố cục chuẩn)
- **S10** Chủ nuôi: bảng (tên, SĐT, số pet, lần đến gần nhất, tổng chi, (v5) badge Thành viên). Chi tiết: thông tin liên hệ ((v5) thêm **địa chỉ**, không hỏi CCCD), danh sách pet, lịch sử hẹn. (v6) Điểm tích lũy, badge Thành viên: **sau V1**.
- **S11** Nhân viên: bảng (avatar màu, tên, vai trò, nhận đặt lịch on/off, trạng thái); (v5) form hồ sơ có khối **Chuyên môn** (số chứng chỉ hành nghề, bằng cấp) chỉ hiện và bắt buộc khi vai trò là Bác sĩ; tab Lời mời (email, vai trò, hết hạn, gửi lại/thu hồi). Khi đạt giới hạn gói: nút "Mời" disabled + link nâng cấp.
- **S12** Cài đặt: thông tin tiệm (tên, slug Site `pawbook.vn/ten-tiem` có kiểm tra trùng, múi giờ, tiền tệ), giờ mở cửa & ngày lễ, mẫu tin nhắc, Gói cước (so sánh gói, gói hiện tại, usage, hóa đơn).
- **S13** (v3: **thay bằng** Owner nhận lời mời + checklist, xem 11.1; bản cũ dưới đây chỉ dùng nếu sau MVP mở tự đăng ký) Onboarding 3 bước: tên tiệm + loại hình (spa / thú y / cả hai) → giờ mở cửa → dịch vụ mẫu (tick chọn) → vào app với checklist "Mời nhân viên, chia sẻ link đặt lịch".
- **S14** Portal quản lý lịch: xem chi tiết, Dời (về bước 4 của S6), Hủy (lý do, cảnh báo nếu trong khung không được hủy; (v6) mặc định trước 24 giờ, Owner cài).

---

## 6. Luồng chính

### F1. Lễ tân đặt lịch qua điện thoại (mục tiêu: < 60 giây)
1. Bấm `N` hoặc "+ Đặt lịch" ở bất kỳ đâu → S2.
2. Gõ 4 số cuối SĐT → chọn chủ nuôi → pet "Lu" (Poodle, 6kg, cỡ S) tự chọn nếu chỉ có 1 pet.
3. Chọn "Tắm + cắt tỉa" → thời lượng tự thành 60' cho cỡ S.
4. Để "Chưa phân" (v6), chọn ngày → slot 14:00.
5. Xác nhận → toast "Đã đặt lịch cho Lu lúc 14:00 T5 16/10" + block mới nhấp nháy nhẹ trên S1.
- Nhánh lỗi: slot bị lấy mất → lưới refresh, gợi ý slot gần nhất, giữ nguyên các field đã nhập.

### F2. Chủ nuôi tự đặt qua portal
Mở link từ Facebook/Zalo của tiệm → S6 bước 1 → 4 → màn thành công → (v6) nhận Zalo (hoặc SMS) xác nhận → 24h trước nhận nhắc có link S14.

### F3. Một ngày ở quầy (lịch nhân viên)
S1 chế độ "Theo nhân viên" → khách đến → click block → "Check-in" (block đổi màu info, tạo lượt + số thứ tự) → (v6) groomer mở lượt trên điện thoại (S17) → "Hoàn tất lượt". Lịch dừng ở "Đã đến". Khách gọi dời lịch → kéo block sang cột/giờ khác → modal xác nhận "Dời Mochi từ 14:00 sang 16:00, gửi thông báo cho khách?".

### F4. Khám và ghi mũi tiêm (bác sĩ)
(v6) S16 → "Nhận khách" → S17 ghi khám (chẩn đoán/điều trị) → khối Tiêm phòng → "Ghi mũi tiêm" (Vaccine 7 bệnh, mũi 2, số lô) → hệ thống hiển thị "Mũi 3 đến hạn 06/11/2026, sẽ nhắc chủ nuôi trước 7 ngày" → lưu.
- Lễ tân mở cùng hồ sơ: (v6) chỉ thấy cảnh báo + ngày khám, nội dung bệnh án bị khóa.

### F5. Theo dõi tiêm phòng đến hạn
Dashboard card "Mũi tiêm đến hạn: 12" → S7 tab "Quá hạn" → gọi khách → "Đặt lịch tiêm" (mở S2 điền sẵn pet + dịch vụ tiêm) hoặc "Gửi nhắc lại".

### F6. Owner xem dashboard & quản lý gói
S5 → thấy banner "Đã dùng 82% lịch hẹn tháng này" → Cài đặt › Gói cước → so sánh gói → nâng cấp (Stripe Checkout) → quay lại với toast thành công. (v3: **sau MVP**; ở MVP Owner bấm "Liên hệ gia hạn", admin gia hạn tay, xem F13.)

### F7. Mời nhân viên & chuyển workspace
Owner mời email + vai trò → nhân viên mở email → (v3) mở `/invite/{token}`: chưa có tài khoản thì tạo ngay trên trang, đã có (đang làm tiệm khác) thì đăng nhập rồi nhận; không có trang Đăng ký chung → vào CMS. Người làm ở 2 tiệm dùng switcher ở topbar; tên + logo workspace hiện tại luôn hiển thị rõ để tránh thao tác nhầm tiệm.

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
| (v5) Combo Tắm + cắt móng (mã `CB01`) | grooming, combo | S 60' · M 75' · L 105' | 250.000-450.000 ₫ (giá riêng) |

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

~~Thanh toán tại quầy/POS, quản lý kho,~~ (v2: đã đưa vào, xem mục 10) **Lưu chuồng** (v5: toàn bộ nghiệp vụ lưu chuồng ngoài V1, không vẽ màn, không có dịch vụ mẫu), gợi ý/phân công bác sĩ theo chuyên môn, app native, chat với khách, **bán hàng online / giỏ hàng** (sản phẩm chỉ trưng bày), đa ngôn ngữ ngoài tiếng Việt.

---

## 10. Bổ sung v2: màn mới và màn thay đổi

Chỉ liệt kê bố cục, trạng thái và quy tắc; dùng lại design system mục 3. Ưu tiên: **MVP** làm cùng P0/P1 hiện có; **GĐ2**, **GĐ3** làm sau.

| # | Màn hình | Giai đoạn | Vai trò |
|---|---|---|---|
| 10.1 | Branch switcher + Cài đặt › Chi nhánh | MVP | Tất cả staff / Owner |
| S15 | Tiếp nhận khách (check-in, vãng lai, thú hoang) | MVP | Lễ tân |
| S16 | Bảng hàng đợi hôm nay | MVP | Lễ tân, Bác sĩ, Groomer |
| S17 | Lượt khám / lượt spa | MVP | Bác sĩ, Groomer |
| S18 | Thu ngân: lập hóa đơn, thanh toán | MVP | Lễ tân, Thu ngân, Owner |
| S19 | Bán lẻ nhanh tại quầy | MVP (sản phẩm có từ GĐ2) | Lễ tân |
| S20 | Thông báo nhắc gọi khách | MVP | Lễ tân |
| S21 | Portal: đăng nhập OTP + lịch sử khám (**mobile-first**) | MVP | Chủ nuôi |
| S22 | Kho: tồn kho, lô & hạn dùng | GĐ2 | Owner, Lễ tân, Bác sĩ |
| S23 | Tra tồn liên chi nhánh + Điều chuyển | GĐ2 | Lễ tân, Bác sĩ, Owner |
| S24 | Phiếu nhập (2 bước), hao hụt, nhà cung cấp, kiểm kê | GĐ2 | Kho (lập), Owner (điền giá, duyệt), QL chi nhánh (duyệt) |
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
- (v5) Nếu khách đã có **hóa đơn nháp đang mở** hôm nay ở chi nhánh này (vd vừa tiếp nhận pet thứ nhất): banner "Đang mở nháp · 1 lượt (Mochi)", lượt mới tự gắn vào cùng hóa đơn. Tạo nhiều lượt liền cho nhiều pet (tick nhiều pet chip → mỗi pet một số thứ tự).
- Ô lớn "Nhập SĐT khách" (focus sẵn, phím tắt `F2`). Kết quả: chủ nuôi + danh sách pet (pet chip). Nếu khách có lịch hẹn hôm nay ở chi nhánh này: card nổi bật "Có lịch 14:00 · Tắm + cắt tỉa · Ngọc Anh" + nút **Check-in**.
- Không tìm thấy: form tạo nhanh (họ tên, SĐT bắt buộc) → thêm pet (tên, loài, giống, cân nặng). SĐT là khóa: trùng thì mở hồ sơ cũ, không tạo mới.
- Nút phụ "**Thú hoang / vô chủ**": tạo pet "Thú hoang #12" (sửa tên được), bắt buộc loài + cân nặng ước lượng + ghi chú tình trạng.
- (v6) Nút đỏ "**Cấp cứu**": tạo lượt khám ngay chỉ với loài + tình trạng (hồ sơ khách làm sau, song song), lượt lên đầu hàng đợi, hiện badge "Cấp cứu".
- (v6) Lịch online chưa phân người: Check-in mở ô chọn người làm (gợi ý người khách mong muốn, người trực đang rảnh).
- Chọn loại lượt: **Khám bệnh** / **Spa** + (v5) **lý do đến & tình trạng ban đầu** (ô nhiều dòng, placeholder "Chủ nuôi kể: ói từ sáng, bỏ ăn…"; nhãn nhỏ "Lễ tân ghi lời chủ nuôi, không ghi chẩn đoán") → "Tạo lượt" → hiện số thứ tự lớn (`Số 042`) + nút In phiếu số.
- Ghi chú: khách **chỉ mua hàng** không đi qua màn này, dùng S19.

### S16. Bảng hàng đợi hôm nay
- Hai cột tab: **Khám bệnh** · **Spa**. Mỗi thẻ: số thứ tự (mono, lớn), pet chip + badge an toàn, giờ đến, loại (Có hẹn 14:00 / Vãng lai), thời gian chờ (đổi warning > 30 phút, danger > 60 phút), người phụ trách.
- Cột trạng thái (v6): Đang chờ · Đang thực hiện · Hoàn tất. Thẻ Hoàn tất có thêm badge hóa đơn ("Chưa thu" / "Đã thu") lấy từ hóa đơn, lượt khám không có trạng thái thanh toán riêng.
- (v6) Lượt cấp cứu luôn ở đầu cột Đang chờ (viền đỏ, badge "Cấp cứu"); các số đang chờ lùi một bậc, số trên phiếu không đổi.
- Bác sĩ/groomer bấm "**Nhận khách**" → mở S17. Nếu người khác vừa nhận: toast "Số 042 đã được BS. Thu Hà nhận", thẻ tự cập nhật.
- Chế độ màn hình TV ở phòng chờ (tùy chọn): chỉ số thứ tự + tên pet, không SĐT.
- Empty: "Chưa có khách nào hôm nay".

### S17. Lượt khám / lượt spa
- Header: số thứ tự, pet (chip lớn + dị ứng), chủ nuôi, (v5) **tình trạng ban đầu lễ tân ghi**, lịch sử 3 lần gần nhất (thu gọn).
- **Khám bệnh:** khối Chẩn đoán/Điều trị (= ghi khám, chỉ bác sĩ sửa), khối **Dịch vụ đã làm** (thêm từ danh mục, giá tự điền), khối **Thuốc kê** (GĐ2: ô tìm thuốc hiện tồn tại chi nhánh; hết hàng → dòng "CN Quận 7 còn 12 · Tạo yêu cầu chuyển", mở S23), khối Tiêm phòng (dùng lại S4).
- **(v4, GĐ2)** Dòng thuốc/vật tư hiện lô sẽ dùng theo (v6) **FIFO lô nhập** ("Lô AMX2409 · HSD 03/2027", badge Cận date nếu trong ngưỡng) để bác sĩ/groomer lấy đúng lô nhập trước.
- (v6) **Xét nghiệm / chẩn đoán hình ảnh** là dịch vụ thêm vào lượt; kết quả ghi chữ + đính kèm ảnh/PDF vào dòng bệnh án. Dịch vụ phát sinh: báo giá miệng, khách đồng ý thì thêm dòng (giá hiện tại).
- (v6) Khối bệnh án có ô "Hướng dẫn chăm sóc" (khách thấy trên Site), "Lịch tái khám" và "Ghi chú nội bộ" (nền xám, "Không hiện cho khách").
- **Spa:** dịch vụ, ghi chú (dầu gội dùng, tình trạng lông/da), ảnh lúc nhận và lúc trả (tùy chọn, đính kèm lượt). (v6) Thấy dấu hiệu bệnh: nút "Báo lễ tân tạo lượt khám". (v5) Chọn **combo** hiện một dòng "Combo Tắm + cắt móng" (mở rộng xem dịch vụ con, chỉ để biết thứ tự làm).
- **(v5, GĐ2) Khối Vật tư đã dùng** (cả khám và spa): thêm dịch vụ thì vật tư định mức tự điền theo cỡ pet (nhãn "Gợi ý"); nhân viên sửa số lượng thực tế, "Đổi vật tư" sang sản phẩm khác, xóa dòng. Pet có dị ứng: dòng vật tư khớp từ khóa dị ứng bị viền đỏ + cảnh báo "Mochi dị ứng dầu gội hương liệu", gợi ý đổi. Vật tư không cộng vào tạm tính (không tính tiền riêng) nhưng trừ kho khi hoàn tất lượt.
- Footer: tạm tính (chỉ giá bán) + nút "**Hoàn tất lượt**" → trạng thái "Hoàn tất", lễ tân nhận thông báo; (v6, GĐ2) trừ kho thuốc + vật tư lúc này, thiếu hàng thì báo và không hoàn tất được.
- Read-only cho lễ tân (khóa + "Chỉ bác sĩ được chỉnh sửa").

### S18. Thu ngân
- (v5) Danh sách **"Hóa đơn nháp đang mở"** bên trái (mở từ lúc tiếp nhận): mỗi thẻ là một khách, đếm lượt "2 pet · 1 đang khám, 1 xong", badge "Sẵn sàng thanh toán" khi mọi lượt đã xong.
- Bên phải hóa đơn nháp: dòng **nhóm theo thú cưng** (Mochi: tắm, cắt móng · Bơ: khám, thuốc) + nhóm "Mua thêm" (thức ăn, đồ chơi) + (v6) nút "**Thêm dòng gõ tay**" (tên + giá), dòng dịch vụ tự xuất hiện khi bác sĩ/groomer ghi; thuế, tổng. (v6) **Không có giảm giá tay** (khuyến mãi theo chiến dịch sau V1). Lễ tân xóa/sửa dòng khi khách đổi ý (theo quyền).
- (v6) Nút "**Hủy hóa đơn**" (lễ tân, thu ngân): bắt buộc lý do; Owner nhận thông báo; lượt khám trong hóa đơn quay về trạng thái chưa gắn hóa đơn.
- (v5) Nút **"In nháp"**: bản in có watermark chéo + tiêu đề "NHÁP / TẠM TÍNH", không có số hóa đơn.
- (v5) Nút **"Hoàn tất & thu tiền"** disabled khi còn lượt chưa xong (tooltip "Mochi đang làm spa, chưa ghi nhận xong dịch vụ"); có bước xác nhận lại các khoản với khách trước khi thu.
- Thêm sản phẩm bán kèm (quét mã vạch hoặc tìm tên).
- (v6) Chưa mở ca thu ngân thì nút thu tiền mặt disabled + link "Mở ca" (S35); chuyển khoản/QR/thẻ vẫn thu được.
- Thanh toán (v6: thu đủ một lần, không cọc, không nợ): chọn cách trả (Tiền mặt / Chuyển khoản / QR / Thẻ), cho phép **chia nhiều phương thức** trong một lần thu (vd 500.000 tiền mặt + phần còn lại QR); tiền mặt có ô "Khách đưa" → "Tiền thối". Thu đủ → hóa đơn **Hoàn tất** (v5: trạng thái `completed`, cấp số hóa đơn).
- Đã hoàn tất: hóa đơn **khóa**, badge "Đã hoàn tất", lễ tân chỉ còn In / "Trả hàng" (S31). (v6) **Chỉ Owner có nút "Sửa"**: mở bản nháp thay thế chép từ bản cũ → sửa dòng → lý do bắt buộc → lưu = hủy bản cũ + bản mới số mới (ghi "Thay thế cho Q3-…-0042"), tiền đã thu chuyển sang bản mới, màn hiện chênh lệch "Thu thêm 50.000 ₫" hoặc "Trả lại khách 30.000 ₫". Mọi thao tác vào Nhật ký.
- Trạng thái: nút thanh toán chặn double-click; hóa đơn vừa bị người khác hủy → banner + tải lại.
- Lễ tân và nhân viên **không bao giờ thấy giá nhập**.

### S19. Bán lẻ nhanh tại quầy
- Kiểu POS: ô quét mã vạch/tìm sản phẩm, giỏ bên phải, thanh toán như S18.
- **Không bắt buộc thông tin khách.** Ô "SĐT khách (không bắt buộc)" để lưu lịch sử mua nếu khách muốn. (v6) Điểm thành viên: sau V1.
- Bán được thức ăn, đồ chơi, phụ kiện và (v5) **thuốc không kê đơn**; thuốc **kê đơn** và dịch vụ không thêm được ở đây, tooltip "Thuốc kê đơn/dịch vụ phải đi qua lượt khám".
- **(v4, GĐ2)** Mỗi dòng giỏ có chọn đơn vị (Gói / Thùng 24) khi sản phẩm có nhiều đơn vị; quét mã vạch thùng tự chọn đơn vị Thùng.
- **(v4, GĐ2)** Dưới tên sản phẩm hiện dòng phụ lô sẽ xuất theo (v6) FIFO lô nhập: "Lô L1 · HSD 31/12/2026" (chữ mono, badge vàng "Cận date" nếu trong ngưỡng). Lấy từ 2 lô thì hiện cả hai ("20 gói L1 + 10 gói L2"). Người có quyền thấy link "Đổi lô" (chọn lô khác, ghi lý do).

### S20. Thông báo nhắc gọi khách
- Chuông topbar + trang "Cần gọi hôm nay": danh sách lịch hẹn **ngày mai** của chi nhánh, mỗi dòng: giờ, khách, SĐT (click-to-call), pet, dịch vụ, trạng thái nhắc tự động (SMS/Zalo đã gửi / thất bại).
- Action: "Đã gọi · Khách xác nhận", "Dời lịch" (mở modal dời của S3), "Không liên lạc được".
- Thông báo được tạo trước 1 ngày; hiển thị đếm trên chuông.

### S21. Portal: đăng nhập OTP + lịch sử
- Đăng nhập: nhập SĐT → mã OTP 6 số (đếm ngược 60s gửi lại, sai 5 lần khóa 15 phút).
- Trang "Của tôi": pet của tôi, lịch hẹn sắp tới (Dời / Hủy), **lịch sử khám/spa** (ngày, chi nhánh, dịch vụ), lịch tiêm sắp tới. (v6) Chi tiết một lần khám: chẩn đoán, hướng dẫn chăm sóc, thuốc đã kê, lịch tái khám, sổ tiêm; **không bao giờ** hiện ghi chú nội bộ của bác sĩ. Xem hóa đơn đã hoàn thành của mình.
- (v6) Lần đầu đăng nhập OTP với SĐT chưa có hồ sơ: nhập họ tên → tạo tài khoản (khách tự đăng ký).
- Dời lịch: nếu còn ít hơn hạn dời (chủ tiệm cấu hình, vd 24h) thì nút Dời disabled + "Vui lòng gọi tiệm để dời lịch trong vòng 24 giờ".
- Thay đổi S6: bước 2 dùng đăng nhập OTP thay cho nhập SĐT tự do khi khách muốn chọn pet đã có.

### S22. Kho (GĐ2)
- Tabs: **Tồn kho** · **Lô & hạn dùng** · **Sổ kho** (lịch sử nhập/xuất).
- Tồn kho: sản phẩm, loại (Thuốc / Vật tư / Bán lẻ), tồn, đang giữ, khả dụng, đơn vị, giá bán; **cột Giá vốn và Giá trị tồn chỉ Owner** (role khác: cột không tồn tại, không chỉ ẩn).
- Badge: "Sắp hết" (warning), "Hết hàng" (danger), "Lô hết hạn trong 30 ngày".
- Lô & hạn dùng: lô, HSD, số lượng; lô quá hạn gạch ngang, không xuất được.
- **(v4)** Lô & hạn dùng áp dụng cho **mọi hàng có HSD** (thức ăn, pate, vật tư), không chỉ thuốc. Mỗi sản phẩm liệt kê lô theo thứ tự (v6) **nhập trước** (FIFO), lô đầu có nhãn "Xuất trước". Bộ lọc nhanh: **Cận date** (trong ngưỡng cảnh báo của sản phẩm, mặc định 30 ngày, thức ăn có thể 60-90) · **Quá hạn** · Tất cả. Cột "Còn … ngày".
- **(v4)** Lô quá hạn: nút "Hủy hàng" (Owner; (v6) nhân viên kho tạo, quản lý chi nhánh duyệt; bắt buộc lý do) → ghi sổ `write_off`.
- (v6) Tab "**Hàng lỗi chờ xử lý**" (Owner): hàng khách trả bị lỗi; action Hủy / Trả nhà cung cấp / Chuyển sang mã hàng lỗi để bán giá thấp.
- **(v4)** Tồn hiển thị theo đơn vị nhỏ nhất kèm quy đổi: "30 gói (1 thùng 6 gói)".
- **(v4)** Tab thứ 4 **Cảnh báo**: danh sách cảnh báo mở của chi nhánh (Sắp hết / Hết hàng / Cận date / Quá hạn), mỗi dòng: sản phẩm, lô, tồn/ngưỡng hoặc "còn N ngày", từ khi nào; action "Đã xem", "Tạo phiếu nhập" (Owner), "Tạo yêu cầu chuyển", "Hủy hàng" (lô quá hạn).
- **(v4)** **Cài ngưỡng** (Owner, form sản phẩm + sửa nhanh hàng loạt trong bảng Tồn kho): "Báo sắp hết khi còn ≤ [10] gói" (mặc định mọi chi nhánh, có thể ghi đè từng chi nhánh) và "Báo cận date trước [60] ngày" (để trống = theo nhóm sản phẩm). Form nhóm sản phẩm có ô "Báo cận date trước … ngày" áp cho cả nhóm.
- Ví dụ dữ liệu (v4): "Thức ăn A1": lô `L1` HSD 31/12/2026 còn 6 gói (Cận date), lô `L2` HSD 30/06/2027 còn 24 gói.

### S23. Tra tồn liên chi nhánh + Điều chuyển (GĐ2)
- Tìm sản phẩm → bảng các chi nhánh: tồn khả dụng, HSD gần nhất, khoảng cách/ghi chú. Chi nhánh hiện tại ở dòng đầu.
- "**Yêu cầu chuyển**" (chọn chi nhánh nguồn, số lượng, gắn khách đang chờ + "hẹn lấy ngày 18/10") → tạo phiếu.
- Danh sách phiếu: tabs Gửi đi / Nhận về; trạng thái **Yêu cầu → Đã duyệt → Đang vận chuyển → Đã nhận / Đã hủy** (stepper ngang trên chi tiết phiếu).
- Chi nhánh nguồn: Duyệt / Từ chối, chọn lô, nhập shipper (tên, SĐT, mã vận đơn). Chi nhánh nhận: "Xác nhận đã nhận" với số lượng thực nhận (lệch thì bắt buộc ghi chú).
- Nhận xong: thông báo cho lễ tân "Thuốc XYZ cho khách Nguyễn Thị Lan đã về, giữ đến 19/10".

### S24. Phiếu nhập, hao hụt, nhà cung cấp, kiểm kê (GĐ2; v6: kho lập, Owner điền giá)
- (v6) **Phiếu nhập hai bước.** Bước 1 (nhân viên kho, cơ sở nhận hàng): nhà cung cấp, số hóa đơn NCC, dòng (sản phẩm, lô, HSD, SL); **không có cột giá**; Lưu nháp / **Lưu & nhập kho** (cộng tồn ngay, sau đó không sửa số lượng). Bước 2 (Owner): danh sách "**Phiếu chờ điền giá**" (badge đếm ở sidebar Kho), mở phiếu, nhập đơn giá từng dòng → tổng tiền → "Lưu giá". Báo cáo lãi gộp hiện cảnh báo khi còn phiếu chưa điền giá.
- (v6) **Phiếu hao hụt / điều chỉnh** (nhân viên kho): chọn sản phẩm + lô, số lượng, lý do (Chuột cắn, Vỡ/hỏng, Hết hạn, Mất, Khác + ghi chú), ảnh (tùy chọn) → "Gửi duyệt". Owner/quản lý chi nhánh: tab "**Chờ duyệt**", Duyệt (trừ tồn) hoặc Từ chối (lý do).
- **(v4)** Dòng phiếu nhập: chọn đơn vị nhập (mặc định Thùng nếu có), hiển thị quy đổi "2 thùng = 48 gói". HSD nhập được dạng **tháng/năm** (lưu ngày cuối tháng); bắt buộc với hàng theo lô. Bỏ trống số lô → tự sinh theo mã phiếu. Cảnh báo khi nhập lô có HSD **sớm hơn** lô đang tồn ("Lô mới hết hạn trước lô cũ, sẽ được xuất trước").
- **(v4)** Kiểm kê đếm **theo lô** (mỗi dòng: sản phẩm + lô + HSD).
- Kiểm kê: chọn chi nhánh, nhập số đếm thực tế, hiển thị chênh lệch, (v6) gửi duyệt → quản lý chi nhánh/Owner duyệt thì ghi sổ điều chỉnh.

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
- (v5) Ô có **đăng ký** của nhân viên hiện chip viền đứt "Đã đăng ký"; Owner bấm ✓ Nhận (thành ca được xếp) hoặc ✗ Từ chối. Bộ lọc "Chỉ ô có đăng ký".
- (v5) Màn **"Đăng ký ca của tôi"** cho nhân viên (CMS, mobile được): lưới 2 tuần tới, tick ca có thể làm, ghi chú; thấy trạng thái Chờ duyệt / Đã nhận / Từ chối. Không thấy đăng ký của người khác.
- Thanh phủ ca dưới lưới theo từng ngày/ca: "Bác sĩ 1/2 · Lễ tân 1/1 · Bảo vệ 0/1" (thiếu = danger).
- Cài đặt mẫu ca (giờ, hệ số lương) và yêu cầu tối thiểu theo vai trò.

### S27. Kiosk chấm công (GĐ3)
- (v5) **Cách chính: chấm trên CMS**: nhân viên có tài khoản thấy nút lớn "Chấm công vào" / "Chấm công ra" ở topbar và Tổng quan, kèm ca hôm nay ("Ca sáng 07:30-12:00"). Lỗi: ngoài mạng chi nhánh (nếu Owner bật kiểm IP), không có ca hôm nay (vẫn cho chấm, (v6) **chọn cơ sở**, đánh dấu "Ngoài lịch" chờ Owner duyệt mới tính công). Có ca thì cơ sở tự lấy theo ca. Kiosk dưới đây chỉ cho nhân viên không có tài khoản.
- Màn toàn màn hình trên tablet ở quầy, chữ lớn: đồng hồ, tên chi nhánh, bàn phím số "Mã nhân viên" → "PIN" → "Chào Phạm Quốc Bảo · Vào ca 07:58 · Ca sáng".
- Lỗi: sai PIN, thiết bị chưa đăng ký, mạng không thuộc chi nhánh.

### S28. Bảng công (GĐ3, Owner)
- Bảng tháng: nhân viên × ngày, ô = giờ vào/ra; "Quên chấm ra" badge warning cần duyệt; Owner sửa giờ (bắt buộc lý do, ghi lịch sử).

### S29. Lương (GĐ3, chỉ Owner)
- (v5) Ai vào được: Owner và người được Owner ủy quyền (Cài đặt › Phân quyền › "Xem lương"). Người khác: không có menu và gọi thẳng URL cũng bị chặn.
- Vào trang: modal "Nhập lại mật khẩu để xem lương" → mở 10 phút, đồng hồ đếm ngược ở header ("Tự khóa sau 09:12"), nút "Khóa ngay". Hết giờ: nội dung mờ + modal mở lại.
- (v5) Bảng kỳ lương (chọn **tuần hoặc tháng**): nhân viên, số ca sáng / chiều / tối đã chấm, **tiền dự kiến** (= Σ đơn giá ca). Click dòng → danh sách từng ca (ngày, ca, giờ vào/ra, đơn giá). Cột lương tháng, phụ cấp, tăng ca, thưởng/phạt để sau (**[Cần nghiên cứu]**).
- (v5) Cài đặt **đơn giá ca theo nhân viên**: bảng nhân viên × ca (Sáng / Chiều / Tối), ô nhập số tiền/ca, áp dụng từ ngày.
- "**Chốt kỳ lương**" (modal xác nhận) → khóa, badge "Đã chốt". Tab "Lịch sử truy cập" (ai mở trang lương, lúc nào).

### 10.2 Thay đổi ở màn đã có (không vẽ lại)
- **S1 Lịch:** lọc theo chi nhánh hiện tại. Block lịch có trạng thái `checked_in` thêm số thứ tự lượt.
- **S3 Chi tiết lịch hẹn:** nút "Check-in" giờ tạo lượt khám và hiện số thứ tự; "Ghi khám" chuyển sang mở S17.
- **S5 Dashboard:** Owner thêm KPI doanh thu thực thu hôm nay, lợi nhuận gộp (GĐ2), lọc chi nhánh. **Lễ tân chỉ xem số liệu hôm nay của chi nhánh mình** (không có bộ chọn ngày).
- **S11 Nhân viên:** thêm nhân viên **không có tài khoản** (vai trò Bảo vệ / Lao công / Phụ việc), cột chi nhánh, nút "Đặt PIN chấm công".
- **S12 Cài đặt:** thêm "Hạn khách tự dời/hủy lịch (giờ)" ((v6) mặc định 24), thuế mặc định, mẫu tin nhắc hẹn. (v6) Mục **Kênh nhắn tin**: kết nối Zalo OA (ZNS, mẫu tin đã duyệt), nhà cung cấp SMS (brandname), gửi tin thử, thống kê số tin đã gửi tháng này. (v6) Theo từng cơ sở: **tỉ lệ đặt online** khám / spa (vd 1/3, có ghi đè theo ca), hạn trả hàng (ngày), két.

### 10.3 Luồng mới
- **F8. Một lượt khám từ đầu đến cuối (v5):** S15 nhập SĐT → (có hẹn: Check-in / vãng lai: tạo lượt, ghi tình trạng ban đầu) → số 042 + **hóa đơn nháp mở sẵn** → trong lúc chờ, khách mua thêm thức ăn, lễ tân thêm vào nháp → S16 bác sĩ "Nhận khách" → S17 ghi khám + dịch vụ + thuốc (dòng tự vào nháp) → "Hoàn tất lượt" → S18 lễ tân in nháp cho khách kiểm → khách xác nhận → thu tiền (tiền mặt + QR) → hóa đơn **Hoàn tất**, khóa.
- **F8b. Một khách hai pet (v5):** S15 tick Mochi (spa) + Bơ (khám) → 2 số thứ tự, 1 hóa đơn nháp → S18 dòng nhóm theo pet → thu một lần khi cả hai lượt xong.
- **F9. Hết thuốc, chi nhánh khác còn (GĐ2):** S17 kê thuốc XYZ → "Hết tại CN Q3 · CN Q7 còn 12" → hẹn khách mai lấy → S23 yêu cầu chuyển (giữ hàng ở Q7) → Q7 duyệt + giao shipper → Q3 xác nhận nhận → thông báo lễ tân, giữ hàng 48h cho khách → khách đến, S18 bán.
- **F10. Khách chỉ mua thức ăn:** S19 quét sản phẩm → (tùy chọn SĐT) → thanh toán. Không tạo hồ sơ.
- **F11. Đăng ký ca, chấm công và tính lương (GĐ3, v5):** nhân viên đăng ký ca → Owner nhận/từ chối ở S26, kiểm đủ người → nhân viên bấm chấm công trên CMS (không tài khoản: kiosk S27) → Owner xem S28, duyệt "quên chấm ra" → cuối tuần/tháng S29 mở khóa, xem tiền dự kiến theo đơn giá ca, chốt kỳ.

### 10.4 Dữ liệu mẫu bổ sung
- Chi nhánh: **CN Quận 3** (`Q3`, chi nhánh chính) và **CN Quận 7** (`Q7`).
- Nhân viên không tài khoản: Lê Văn Tư (Bảo vệ, Q3), Nguyễn Thị Sáu (Lao công, Q3).
- Hàng đợi hôm nay ở Q3: 9 lượt (6 khám, 3 spa), trong đó 1 thú hoang "Thú hoang #3" (mèo, ~2kg, bị thương chân).
- Kho: thuốc "Amoxicillin 250mg" hết ở Q3, Q7 còn 12 viên (lô `AMX2409`, HSD 03/2027); dầu gội "Bio-Groom" sắp hết ở Q3; thức ăn "Royal Canin Mini Adult 2kg" giá bán 420.000 ₫.
- Lương (v5, theo ca): Ngọc Anh (Groomer) ca sáng 180.000 ₫, ca chiều 180.000 ₫, ca tối 220.000 ₫; Lê Văn Tư (Bảo vệ) ca tối 250.000 ₫.

---

## 11. Bổ sung v3: Trang quản trị nền tảng (super-admin)

**Thứ tự (đã chốt):** admin có trước → admin tạo shop + tài khoản Owner → Owner nhận lời mời, vào CMS → Owner mời nhân viên, cấu hình Site → khách dùng Site. Không có trang tự đăng ký shop ở MVP.

Người dùng: **nhà cung cấp PawBook** (bạn và nhân viên hỗ trợ), không phải nhân viên tiệm. Cùng app, URL `pawbook.vn/admin`. Dùng lại design system mục 3 nhưng phải **nhận ra ngay đây không phải app của tiệm**: sidebar nền tối (`--neutral-900`), logo "PawBook Admin", badge môi trường (Production đỏ / Demo xám) ở topbar. Desktop-first, không cần mobile.

| Vai trò | Ai | Quyền |
|---|---|---|
| **Super-admin** (v3) | Chủ nền tảng | Toàn quyền khu admin: tạo/khóa shop, gán gói, gia hạn, impersonate, quản lý admin khác |
| **Support** (v3) | Nhân viên hỗ trợ | Xem shop, impersonate (có lý do); **không** khóa shop, đổi gói, gia hạn |

| # | Màn hình | Giai đoạn | Vai trò |
|---|---|---|---|
| A1 | Đăng nhập admin + 2FA | MVP | Super-admin, Support |
| A2 | Danh sách shop | MVP | Super-admin, Support |
| A3 | Tạo shop (wizard) | MVP | Super-admin |
| A4 | Chi tiết shop | MVP | Super-admin, Support |
| A5 | Nhật ký hoạt động (audit) | MVP | Super-admin |
| A6 | Quản lý admin | P1 | Super-admin |
| 10.x | Banner impersonate + banner hết hạn/tạm khóa trong staff app | MVP | Owner |

### A1. Đăng nhập admin
- Form email + mật khẩu, bước 2 nhập mã 6 số (TOTP). Không có "Đăng ký", không có "Đăng nhập bằng Google".
- Lỗi: sai thông tin (thông báo chung, không nói email có tồn tại không), khóa tạm sau 5 lần sai.

### A2. Danh sách shop
- KPI trên cùng: tổng shop, đang hoạt động, sắp hết hạn (≤7 ngày), quá hạn/tạm khóa.
- Bảng: tên shop + slug, chủ (tên, SĐT), gói, **hạn dùng** (đỏ nếu quá hạn, vàng ≤7 ngày), trạng thái (badge: Dùng thử, Hoạt động, Quá hạn, Tạm khóa, Đã hủy), số chi nhánh, nhân viên, lịch tháng này, ngày tạo.
- Lọc trạng thái, gói, "sắp hết hạn"; tìm theo tên/slug/SĐT/email Owner. Nút chính "+ Tạo shop".
- Empty: "Chưa có shop nào · Tạo shop đầu tiên".

### A3. Tạo shop (wizard 3 bước, drawer rộng hoặc trang riêng)
1. **Thông tin shop:** tên, slug (gợi ý từ tên, kiểm tra trùng realtime, xem trước `pawbook.vn/paw-house`; domain riêng để sau MVP), loại hình, múi giờ (mặc định Asia/Ho_Chi_Minh), chi nhánh đầu tiên (tên, mã, địa chỉ).
2. **Tài khoản Owner:** họ tên, email, SĐT. Ghi chú nhỏ: "Owner sẽ nhận email mời. Nếu email đã có tài khoản PawBook (đang làm ở tiệm khác) thì chỉ cần đăng nhập để nhận".
3. **Gói & hạn:** gói (Starter/Pro/Clinic), dùng thử N ngày **hoặc** đã bán (từ ngày, đến ngày, số tiền, mã chuyển khoản), ghi chú nội bộ.
- Xác nhận: tóm tắt → "Tạo shop". Thành công: toast + mở A4, nút "Gửi lại lời mời", "Sao chép link mời".
- Lỗi: slug trùng/bị cấm, email sai định dạng, ngày kết thúc trước ngày bắt đầu.

### A4. Chi tiết shop (tabs)
- **Header:** tên, badge trạng thái, gói + hạn, nút "Đăng nhập như Owner", menu ⋯ (Tạm khóa / Mở khóa, Hủy shop).
- **Tổng quan:** thông tin shop, Owner (trạng thái: "Chưa nhận lời mời · Gửi lại" / "Đã nhận"), usage (chi nhánh, nhân viên x/giới hạn gói, lịch tháng này x/giới hạn), ghi chú nội bộ. **Không hiện** danh sách khách, pet, hóa đơn.
- **Gói & gia hạn:** bảng `tenant_subscriptions` (gói, từ, đến, số tiền, đã thu, mã chuyển khoản, người tạo). Nút "Gia hạn" mở form: gói, số tháng (1/3/6/12) tự tính ngày kết thúc nối tiếp hạn hiện tại, số tiền, mã chuyển khoản.
- **Truy cập hỗ trợ:** lịch sử impersonate (admin, lý do, bắt đầu, kết thúc, số thao tác ghi).
- **Nhật ký:** audit của shop này.
- **Modal Tạm khóa:** chọn lý do (Chưa thanh toán, Vi phạm, Chủ yêu cầu, Khác + mô tả), cảnh báo rõ: "Nhân viên vẫn đăng nhập và xem được dữ liệu nhưng không thao tác được. Portal ngừng nhận lịch." Nút đỏ "Tạm khóa".
- **Modal Đăng nhập như Owner:** ô lý do bắt buộc, ghi chú "Phiên tối đa 30 phút, mọi thao tác được ghi lại và chủ tiệm xem được". Mở tab mới.

### A5. Nhật ký hoạt động
- Bảng: thời gian, admin, shop, hành động (tiếng Việt: "Tạo shop", "Gia hạn 3 tháng gói Pro", "Tạm khóa: Chưa thanh toán", "Bắt đầu đăng nhập thay"…), chi tiết (diff trước/sau mở rộng được). Lọc theo admin, shop, loại hành động, khoảng ngày. Chỉ đọc.

### A6. Quản lý admin (P1)
- Bảng admin (tên, email, vai trò, 2FA đã bật, đăng nhập lần cuối, trạng thái). Thêm admin = gửi lời mời; vô hiệu hóa (không xóa). Không tự vô hiệu chính mình.

### 11.1 Thay đổi trong CMS (v3)
- **Banner impersonate** (cố định trên cùng, đỏ, không đóng được): "Admin Nguyễn Văn A đang xem với tư cách Trần Minh Khoa · còn 24:10 · **Thoát**". Nút bị chặn trong phiên hiển thị disabled + tooltip "Không khả dụng khi đăng nhập thay".
- **Banner hạn dùng** (Owner): vàng "Gói Pro hết hạn ngày 24/10/2026, liên hệ PawBook để gia hạn"; đỏ khi quá hạn: "Đã quá hạn 3 ngày. Tài khoản sẽ tạm khóa sau 4 ngày nữa."
- **Trạng thái tạm khóa:** banner đỏ toàn app "Tài khoản đang tạm khóa · Liên hệ 0909 xxx xxx"; mọi nút tạo/sửa disabled; xem và xuất dữ liệu vẫn được.
- **Nhận lời mời** (`/invite/{token}`, dùng cho Owner và nhân viên): logo + tên tiệm, "Bạn được mời làm {vai trò} tại {tiệm}"; chưa có tài khoản → form tên + mật khẩu; đã có → nút "Đăng nhập để nhận" (email khác email được mời thì báo lỗi). Link hết hạn → "Link đã hết hạn, liên hệ người mời để gửi lại".
- **S12 Cài đặt › Gói cước:** ở MVP chỉ hiển thị gói hiện tại, hạn dùng, usage, lịch sử gia hạn và "Liên hệ gia hạn" (không có nút thanh toán online). Thêm mục "Lịch sử truy cập hỗ trợ".
- **S13 Onboarding:** (đã chốt) không có ở MVP, shop do admin tạo; Owner lần đầu đăng nhập thấy checklist "Thêm dịch vụ, mời nhân viên, chia sẻ link đặt lịch".

### 11.2 Luồng mới
- **F0. Khởi tạo hệ thống (một lần):** deploy → `php artisan platform:admin-create` → super-admin đăng nhập A1, bật 2FA → (tùy chọn) A6 thêm support. Chưa có admin thì chưa có shop nào.
- **F12. Bán shop mới:** khách đồng ý mua → admin A3 tạo shop (gói Pro, 12 tháng, mã CK) → Owner nhận mail mời → tạo tài khoản ở `/invite/...` → vào `pawbook.vn/app/paw-house` với checklist → bắt đầu dùng.
- **F13. Hết hạn và gia hạn:** còn 7 ngày: banner vàng + mail → quá hạn: banner đỏ 7 ngày → tự tạm khóa → khách chuyển khoản → admin A4 "Gia hạn" → trạng thái về Hoạt động ngay.
- **F14. Hỗ trợ khách:** Owner gọi báo lỗi → support A4 "Đăng nhập như Owner" (lý do "Khách báo không thấy lịch CN Q7") → tab mới có banner đỏ → kiểm tra → Thoát.

### 11.3 Dữ liệu mẫu bổ sung
- Admin: **Nguyễn Văn A** (Super-admin), **Lê Thị Hỗ Trợ** (Support).
- Shop: Paw House Spa & Vet (Pro, hết hạn 15/10/2027, Hoạt động), Meow Clinic (Starter, quá hạn 3 ngày), Happy Tails (Dùng thử, còn 5 ngày), Bé Cún Spa (Tạm khóa: Chưa thanh toán).

### 11.4 (v3) Mỗi shop có 2 khu: Site (khách) và CMS (shop)

Cùng domain `pawbook.vn`, tách theo đường dẫn và guard (khớp code nhánh `dev`: route tiệm `/app/{slug}`, `users` toàn cục). **Khách đăng nhập xong luôn ở lại Site, không bao giờ vào CMS.**

| | **Site** (khách hàng) | **CMS** (nhân viên shop) | **Admin** (nhà cung cấp) |
|---|---|---|---|
| URL | `pawbook.vn/{slug}` | `pawbook.vn/app/{slug}` | `pawbook.vn/admin` |
| Ai | Chủ nuôi | Owner, lễ tân, bác sĩ, groomer | Super-admin, support |
| Đăng nhập | `/{slug}/dang-nhap`: SĐT + OTP, guard `customer` (bảng `owners`, theo từng tiệm) | `/login` chung: email + mật khẩu, guard `web` (bảng `users`, một tài khoản dùng cho nhiều tiệm) | `/admin/login`: email + mật khẩu + 2FA, guard `platform` |
| Sau đăng nhập về | `/{slug}/tai-khoan` | `/app/{slug}/dashboard` (tiệm dùng gần nhất) | `/admin` (Danh sách shop) |
| Giao diện | mobile-first, layout Site (mục 3.4 "Portal" + khung bottom nav) | desktop-first, sidebar (mục 3.4 "Staff app") | sidebar tối (mục 11) |

**Cấu trúc Site** (thay cho "Portal công khai" ở mục 4; S6, S14, S21, S25 thuộc Site):
- Công khai, không cần đăng nhập: **Trang chủ shop** (ảnh bìa, giới thiệu, chi nhánh + địa chỉ + giờ mở cửa + nút gọi/chỉ đường, dịch vụ nổi bật, nút "Đặt lịch"), **Dịch vụ & bảng giá**, **Sản phẩm / đồ ăn** (S25, **GĐ2**: chỉ trưng bày, không giỏ hàng; MVP ẩn mục này), **Đặt lịch** (S6).
- Sau đăng nhập OTP (`/{slug}/tai-khoan`): khung bottom nav 4 mục đã mô tả ở 3.4 (Trang chủ · Lịch hẹn · Thú cưng · Tài khoản).
- Header Site: logo + tên shop, nút "Đặt lịch", nút "Đăng nhập" (đã đăng nhập thì avatar). **Không có** link sang CMS.

**Quy tắc chuyển hướng:**
- Khách (guard `customer`) mở `/app/*` → bị chuyển tới `/login` như người lạ (guard `customer` không có nghĩa gì với CMS); không bao giờ thấy dữ liệu CMS.
- Phiên khách gắn với tiệm: đăng nhập ở Site tiệm A rồi mở Site tiệm B → ở tiệm B vẫn là khách chưa đăng nhập.
- Nhân viên (guard `web`) mở Site → thấy Site như khách vãng lai; hai guard độc lập, một người vừa là nhân viên vừa là khách vẫn dùng được cả hai.
- Đăng nhập Site thành công: về trang khách đang xem dở (`intended`, chỉ chấp nhận đường dẫn thuộc Site của đúng tiệm), mặc định `/{slug}/tai-khoan`.
- Cài đặt › Site (trong CMS, Owner): ảnh bìa, giới thiệu, bật/tắt mục Sản phẩm, dịch vụ hiển thị trên Site (thay cho mục "portal" ở S12).

---

## 12. Bổ sung v5: theo bản tổng hợp nghiệp vụ (19/10/2026)

User chọn bản tổng hợp mới cho mọi điểm lệch (xem `old/analysis/doi-chieu-nghiep-vu-chatgpt.md`). Sửa tại chỗ ở các màn S4, S9, S10, S11, S15, S17, S18, S19, S26, S27, S29, F8, F11, dữ liệu mẫu (đánh dấu **(v5)**). Màn mới dưới đây. **[Cần nghiên cứu]** = chưa chốt, thiết kế chỉ chừa chỗ, không tự đặt quy tắc.

| # | Màn hình | Giai đoạn | Vai trò |
|---|---|---|---|
| S30 | Báo cáo doanh thu & lãi gộp | GĐ2 (doanh thu: MVP) | Owner |
| S31 | Trả hàng | GĐ2 | Lễ tân (tạo), Owner (duyệt hoàn tiền) |
| S32 | Cài đặt › Nhật ký thao tác | MVP | Owner |
| S33 | Site: Điều khoản sử dụng + Cài đặt › Site › Điều khoản | MVP | Khách / Owner |
| S34 | Cài đặt › Thành viên & tích điểm | (v6) **Sau V1** | Owner |
| S35 | (v6) Ca thu ngân: mở / đóng ca, báo cáo ca | MVP | Thu ngân, Lễ tân, Owner |

### S30. Báo cáo doanh thu & lãi gộp
- Bộ lọc: Tuần · Tháng · Khoảng tùy chọn; chi nhánh (từng chi nhánh hoặc "Tất cả").
- KPI: Doanh thu bán lẻ · Doanh thu dịch vụ · Giá vốn hàng bán · Giá vốn vật tư tiêu hao · **Lãi gộp** (+ % biên). Chú thích cố định: "Lãi gộp = Doanh thu − Giá vốn hàng bán − Giá vốn vật tư đã dùng; chưa trừ lương và chi phí vận hành". Doanh thu trước thuế, thuế hiện riêng.
- Bảng theo chi nhánh (khi chọn "Tất cả") và bảng top sản phẩm/dịch vụ theo lãi gộp. Combo tính như một dịch vụ.
- MVP chỉ có phần doanh thu (chưa có kho → chưa có giá vốn, các cột giá vốn hiện "Có từ khi dùng Kho").

### S31. Trả hàng (v6)
- Lễ tân/thu ngân mở hóa đơn đã hoàn tất (tìm theo số hóa đơn hoặc SĐT) › "Trả hàng": chọn **sản phẩm** + số lượng (không vượt số đã bán; dịch vụ không trả được), lý do, **kiểm tay từng dòng**: Nguyên vẹn (cộng tồn ngay) · Lỗi (vào "Hàng lỗi chờ xử lý", Owner quyết) · Hết hạn (báo hủy). Hiện lô sẽ hoàn lại.
- Hạn trả mặc định 7 ngày (Owner cài); quá hạn thì nút bị khóa với tooltip "Quá hạn trả, cần chủ shop", Owner vẫn làm được.
- Hoàn tiền mặt (cần ca thu ngân đang mở) hoặc chuyển khoản; trừ vào doanh thu ngày trả; ghi Nhật ký.

### S32. Nhật ký thao tác (Owner)
- Bảng: thời gian, người làm, chi nhánh, hành động (Hoàn tất hóa đơn, Hủy hóa đơn, Hoàn tiền, Điều chỉnh kho, Trả hàng, Sửa giờ công, Đổi giá bán…), đối tượng (link), lý do. Click → so sánh trước/sau. Lọc theo người, loại, ngày. Chỉ đọc.

### S33. Điều khoản sử dụng
- Site: trang `/{slug}/dieu-khoan` (mobile-first, chữ 16px, mục lục neo), link ở footer mọi trang Site và cạnh nút xác nhận đặt lịch S6 ("Xem điều khoản").
- CMS › Cài đặt › Site › Điều khoản: trình soạn văn bản đơn giản (tiêu đề, đoạn, danh sách), nút "Dùng mẫu gợi ý" (giải thích dùng tên, SĐT, địa chỉ để liên hệ lịch hẹn/dịch vụ/giao nhận), ngày cập nhật. Ghi chú cố định: "Chủ shop tự rà soát nội dung theo quy định áp dụng."
- Tại quầy không có bước bắt khách đồng ý.

### S34. Thành viên & tích điểm ((v6) sau V1, cùng khuyến mãi theo chiến dịch)
- Bật/tắt chương trình; chính sách tích và đổi điểm **[Cần nghiên cứu]** (chừa ô cấu hình, không đặt mặc định).
- S15/S18/S19: tìm khách theo SĐT → badge Thành viên + điểm. Khách không cho SĐT vẫn bán bình thường.

### 12.1 Thay đổi kiến trúc thông tin
- Sidebar: Báo cáo (Owner) gồm S30; Cài đặt thêm **Nhật ký**, **Phân quyền** ((v6) quản lý role + ma trận quyền, gồm "Xem lương"), **Site › Điều khoản**, (v6) **Cơ sở › Tỉ lệ đặt online, Hạn trả hàng, Két**. Thành viên: sau V1.
- Nhân viên (mọi vai trò có tài khoản) thêm mục **"Ca của tôi"** (đăng ký ca, chấm công, xem ca đã xếp; GĐ3).
- Site: thêm "Điều khoản sử dụng" ở footer.

### S35. Ca thu ngân (v6, MVP)
- Thu ngân/lễ tân bấm "**Mở ca**": chọn két của cơ sở, nhập tiền đầu ca. Topbar hiện chip "Ca thu ngân · Két 1 · mở 07:58". Mỗi két chỉ một ca mở; nhiều người thu chung ca, mỗi khoản ghi tên người thu.
- "**Đóng ca**": hệ thống hiện tiền mặt dự kiến (đầu ca + thu − hoàn tiền mặt), nhập số đếm thực tế; lệch thì bắt buộc lý do. Báo cáo ca: tiền mặt, chuyển khoản, QR, thẻ, hoàn tiền, theo người thu; in được.
- Owner thấy danh sách ca, ca lệch tiền nổi bật; quản lý chi nhánh xem ca của cơ sở mình.
- Cài đặt › Cơ sở › Két: thêm/tắt két.

---

## 13. (v6) Ma trận quyền của role mẫu

Đã duyệt 20/10/2026 (sơ đồ 9). (v6) Bản đã cập nhật theo câu 16.1 (kho lập phiếu nhập không thấy giá, báo hao hụt chờ duyệt). Owner sửa được từng role và tạo role mới; một người nhiều role thì cộng quyền; mọi role trừ Chủ shop chỉ thao tác ở cơ sở được gán. Chủ shop không tự ghi chẩn đoán/kê thuốc (kiêm bác sĩ thì gán thêm role Bác sĩ). Thao tác nhạy cảm (hủy/sửa hóa đơn, trả hàng, điều chỉnh kho, đổi quyền, đổi giá, sửa bệnh án) ghi Nhật ký.

| Dữ liệu / việc | Chủ shop | QL chi nhánh | Lễ tân | Thu ngân | Bác sĩ | Spa | Kho | Khách (Site) |
|---|---|---|---|---|---|---|---|---|
| Hồ sơ chủ nuôi | Toàn quyền | Ghi | Ghi | Xem | Xem | Xem | — | Của mình |
| Hồ sơ Pet, cảnh báo dị ứng | Toàn quyền | Ghi | Ghi | Xem | Ghi | Xem | — | Của mình |
| Nội dung bệnh án | Xem | Cảnh báo | Cảnh báo, ngày khám | — | Ghi (dòng mình, 24h) | Cảnh báo | — | Xem, trừ ghi chú nội bộ |
| Sổ tiêm | Xem | Xem | Xem | — | Ghi | Xem | — | Xem |
| Lịch hẹn | Toàn quyền | Ghi | Ghi | — | Xem | Xem | — | Đặt, đổi, hủy của mình |
| Tạo lượt, hàng đợi | Toàn quyền | Ghi | Ghi | — | Xem | Xem | — | — |
| Nhận, hoàn tất lượt khám/tiêm | Ghi | — | — | — | Ghi | — | — | — |
| Nhận, hoàn tất lượt spa | Ghi | — | — | — | — | Ghi | — | — |
| Kê thuốc, xét nghiệm | — | — | — | — | Ghi | — | Xem (GĐ2) | — |
| Hóa đơn chưa hoàn thành: thêm dòng, thu tiền | Toàn quyền | Ghi | Ghi | Ghi | — | — | — | — |
| Hủy hóa đơn chưa hoàn thành | Toàn quyền | Ghi | Ghi, có lý do | Ghi, có lý do | — | — | — | — |
| Sửa hóa đơn đã hoàn thành | Sửa (bản thay thế) | — | — | — | — | — | — | — |
| Trả hàng, hoàn tiền | Toàn quyền (cả quá hạn) | Ghi | Ghi, trong hạn | Ghi, trong hạn | — | — | — | — |
| Mở, đóng ca thu ngân | Toàn quyền | Xem | Ghi | Ghi | — | — | — | — |
| Xem hóa đơn đã hoàn thành | Xem | Xem | Xem, in | Xem, in | — | — | — | Của mình |
| Xem tồn cơ sở mình, tra tồn cơ sở khác (GĐ2) | Xem | Xem | Xem | Xem | Xem | Xem | Xem | — |
| Lập phiếu nhập: sản phẩm, số lượng, lô, hạn; kiểm kê (GĐ2) | Toàn quyền | Duyệt | — | — | — | — | Ghi, không thấy giá | — |
| Điền giá nhập vào phiếu, xem giá vốn (GĐ2) | Toàn quyền | — | — | — | — | — | — | — |
| Báo hao hụt (chuột cắn, vỡ), điều chỉnh tồn, hủy hàng hết hạn (GĐ2) | Toàn quyền | Duyệt | — | — | — | — | Ghi, cần duyệt | — |
| Xử lý hàng lỗi (GĐ2) | Toàn quyền | — | — | — | — | — | Xem | — |
| Chuyển kho (GĐ2) | Toàn quyền | Duyệt | Tạo yêu cầu | — | Tạo yêu cầu | — | Ghi (giao, nhận) | — |
| Giá vốn | Xem | — | — | — | — | — | — | — |
| Danh mục dịch vụ, sản phẩm, giá | Toàn quyền | Xem | Xem | Xem | Xem | Xem | Xem | Xem dịch vụ |
| Nhân viên, role, quyền | Toàn quyền | Xem | — | — | — | — | — | — |
| Lịch làm việc, ca trực | Toàn quyền | Ghi | Xem của mình | Xem của mình | Xem của mình | Xem của mình | Xem của mình | — |
| Duyệt chấm công ngoài lịch (GĐ3) | Duyệt | — | — | — | — | — | — | — |
| Lương (GĐ3) | Toàn quyền | — | — | — | — | — | — | — |
| Báo cáo doanh thu, lãi gộp | Mọi cơ sở | Cơ sở mình, không lãi gộp | — | Báo cáo ca | — | — | — | — |
| Nhật ký thao tác | Xem | — | — | — | — | — | — | — |
| Cài đặt cơ sở: giờ mở cửa, tỉ lệ online, hạn trả hàng | Toàn quyền | Xem | — | — | — | — | — | — |
