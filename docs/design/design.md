# design.md: PawBook (Pet Spa & Thú y)

Tài liệu thiết kế giao diện, là input cho **Claude Design**. Đọc hết rồi sinh prototype theo design system, danh sách màn hình và luồng bên dưới. Tên "PawBook" là tên tạm.

Nguồn nghiệp vụ: `analysis/so-do-nghiep-vu/nghiep-vu-da-chot.md`. Dữ liệu: `design/db-schema.md`. Giai đoạn: **MVP**, **GĐ2** (kho), **GĐ3** (ca, chấm công, lương), **Sau V1** (không vẽ).

---

## 0. Hướng dẫn chung

- **Ba khu giao diện** (mục 4): **CMS** cho nhân viên shop (desktop-first 1440px, dùng được ở tablet 1024px vì lễ tân hay dùng iPad), **Site** cho khách (mobile-first 390px, thử cả 360px), **Admin** cho nhà cung cấp (desktop).
- **Ngôn ngữ:** tiếng Việt. Tiền `350.000 ₫`, ngày `dd/MM/yyyy`, giờ 24h `14:30`, tuần bắt đầu Thứ 2. Giờ luôn theo giờ shop.
- **Stack đích:** Laravel + Inertia + Vue 3 + Tailwind CSS. Token ở mục 3 dùng dạng CSS variables / Tailwind theme.
- **Icon:** Lucide (nét 1.5px, cỡ 16/20/24).
- **Dữ liệu mẫu:** tên Việt, thú cưng thực tế (mục 9). Không lorem ipsum.
- **Đủ trạng thái:** loading (skeleton), empty, error, không có quyền (read-only có giải thích), vượt hạn mức gói, shop tạm khóa.
- **Light + dark mode.**

---

## 1. Sản phẩm

SaaS **multi-tenant** cho **pet spa** và **phòng khám thú y**. Mỗi shop là một workspace riêng, có thể nhiều cơ sở. Nhà cung cấp (admin nền tảng) tạo shop và mời chủ shop; chủ shop mời nhân viên, cấu hình dịch vụ, ca trực. Khách tự đặt lịch trên Site của shop.

**Giá trị cốt lõi**
1. Đặt lịch online không bao giờ vượt sức phục vụ: số lịch online cùng lúc ≤ số người trực × tỉ lệ chủ shop đặt.
2. Thời lượng, giá dịch vụ tự tính theo loài/giống/cỡ (chó nhỏ 45', chó lớn 90').
3. Tiếp nhận, hàng đợi, Visit, hóa đơn chạy liền mạch tại quầy; một khách nhiều pet, một hóa đơn.
4. Tự nhắc hẹn, nhắc tiêm phòng / tẩy giun, nhắc tái khám qua Zalo (SMS dự phòng).
5. Bệnh án tập trung mọi cơ sở, phân quyền chặt.

**Cảm giác thương hiệu:** ấm áp, sạch sẽ, đáng tin như phòng khám, thân thiện như tiệm spa. Không hoạt hình quá đà; icon dấu chân chỉ điểm xuyết (logo, empty state).

---

## 2. Vai trò

Shop có 7 **role mẫu**; chủ shop đổi tên role, sửa quyền và tạo role mới (Cài đặt › Phân quyền). Danh mục quyền do hệ thống định nghĩa. Quyền nhạy cảm mặc định chỉ chủ shop, chủ giao được cho role khác. Một người nhiều role thì cộng quyền (vd chủ shop kiêm bác sĩ). Ma trận quyền: **mục 8**.

| Vai trò | Ai | Mục tiêu chính | Thiết bị |
|---|---|---|---|
| **Chủ shop** | Chủ tiệm | Doanh thu, lợi nhuận, nhân viên, cài đặt, mọi cơ sở | Laptop, điện thoại |
| **Quản lý chi nhánh** | Người điều hành một cơ sở | Lịch, ca trực, duyệt kho của cơ sở mình | PC, điện thoại |
| **Lễ tân** | Nhân viên quầy | Đặt lịch, tiếp nhận, hàng đợi, thu tiền, trả hàng | iPad / PC quầy |
| **Thu ngân** | Nhân viên thu tiền | Thu tiền, mở/đóng ca thu ngân | PC quầy |
| **Bác sĩ** | Bác sĩ thú y | Khám, ghi bệnh án, kê thuốc, tiêm | PC phòng khám, tablet |
| **Nhân viên spa** | Groomer | Nhận và hoàn tất lượt spa, ảnh nhận/trả | Điện thoại |
| **Nhân viên kho** (GĐ2) | Người giữ kho | Lập phiếu nhập (không thấy giá), kiểm kê, báo hao hụt, giao nhận chuyển kho | PC |
| **Khách** | Chủ nuôi, đăng nhập Site bằng SĐT + OTP | Đặt/dời/hủy lịch, xem lịch sử, sổ tiêm | Điện thoại |
| **Bảo vệ, lao công** (GĐ3) | Nhân viên không có tài khoản | Chấm công bằng mã + PIN ở kiosk | Kiosk |
| **Super-admin / Support** | Nhà cung cấp PawBook | Tạo shop, gói, hạn, khóa, hỗ trợ | Laptop |

- Một tài khoản làm được ở nhiều shop → **workspace switcher**. Một shop nhiều cơ sở → **branch switcher** cạnh tên shop.
- Nhân viên có cơ sở chính + cơ sở phụ; chỉ thao tác ở cơ sở được gán (trừ chủ shop).
- UI phản ánh quyền: nút ẩn hoặc disabled kèm tooltip ("Chỉ bác sĩ được chỉnh sửa"), không để bấm rồi mới báo lỗi. Dữ liệu không có quyền xem thì **không có trong trang** (không chỉ ẩn bằng CSS).

---

## 3. Design system

### 3.1 Màu (tokens)

Primary teal (sạch, y tế); accent coral (thân thiện, spa); neutral stone.

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
| `--color-primary-soft` | `#CCFBF1` | `#123A36` | nền chip/badge primary, ô lịch chọn |
| `--color-on-primary` | `#FFFFFF` | `#042F2E` | chữ trên nút primary |
| `--color-accent` | `#F97362` | `#FB8A7B` | điểm nhấn, CTA Site |
| `--color-accent-soft` | `#FFE4DF` | `#3B201C` | nền accent |
| `--color-success` | `#16A34A` | `#4ADE80` | hoàn tất, đã gửi |
| `--color-warning` | `#D97706` | `#FBBF24` | sắp đến hạn, gần hết hạn mức |
| `--color-danger` | `#DC2626` | `#F87171` | hủy, lỗi, quá hạn, cấp cứu |
| `--color-info` | `#2563EB` | `#60A5FA` | thông tin, đã đến |
| `--focus-ring` | `0 0 0 3px rgb(13 148 136 / .35)` | `0 0 0 3px rgb(45 212 191 / .4)` | mọi phần tử focus được |

**Trạng thái lịch hẹn**

| Status | Nhãn | Màu |
|---|---|---|
| `confirmed` | Đã xác nhận | primary |
| `checked_in` | Đã đến | info (block nhạt 40% sau giờ kết thúc) |
| `cancelled` | Đã hủy | text-subtle, gạch ngang |
| `no_show` | Không đến | danger |

Lịch đặt xong là Đã xác nhận ngay, dừng ở Đã đến; tiến độ phục vụ xem ở Visit. Lịch có nhiều pet hiện trạng thái từng pet (vd "Mochi: Đã đến · Bơ: Đã hủy").

**Trạng thái Visit:** Đang chờ (text-muted) · Đang thực hiện (info) · Hoàn tất (success) · Đã hủy (subtle). Cấp cứu: viền + badge danger.

**Trạng thái hóa đơn:** Chưa hoàn thành (warning) · Hoàn thành (success) · Đã hủy (subtle, gạch ngang).

**Màu nhân viên** (viền trái block, avatar ring): `#0D9488`, `#F97362`, `#8B5CF6`, `#0EA5E9`, `#EAB308`, `#EC4899`, `#22C55E`, `#64748B`.

**Màu nhóm dịch vụ:** spa = accent (coral), khám = primary (teal).

Tương phản tối thiểu WCAG AA. Trạng thái không bao giờ chỉ bằng màu: luôn kèm icon hoặc chữ.

### 3.2 Typography

- Font `Be Vietnam Pro` cho UI; `JetBrains Mono` cho mã (mã lịch, số hóa đơn, số lô, số hồ sơ).
- Số trong bảng, giờ, tiền: `tabular-nums`.

| Token | Size / line-height | Weight | Dùng cho |
|---|---|---|---|
| `display` | 32 / 40 | 700 | KPI lớn, tiêu đề Site |
| `h1` | 24 / 32 | 600 | Tiêu đề trang |
| `h2` | 20 / 28 | 600 | Section, modal |
| `h3` | 16 / 24 | 600 | Tiêu đề card |
| `body` | 14 / 20 | 400 | Chữ mặc định CMS |
| `body-lg` | 16 / 24 | 400 | Chữ mặc định Site (tránh iOS zoom input) |
| `label` | 13 / 18 | 500 | Label form, header bảng |
| `caption` | 12 / 16 | 400 | Chú thích, timestamp, giờ lưới lịch |

### 3.3 Spacing, radius, shadow

- Spacing 4px base: 2, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64. Padding card 16 (mobile) / 24 (desktop). Gap field 16, gap section 32.
- Radius: `sm 6px` (input, badge), `md 10px` (button, card), `lg 16px` (modal, sheet), `full` (avatar, chip).
- Shadow: `sm` card, `md` dropdown, `lg` modal. Dark mode thay shadow bằng viền.
- Chiều cao control: 36px (CMS), 44px (Site, touch target).
- Motion: 150ms hover/press, 200ms drawer/modal; tôn trọng `prefers-reduced-motion`.

### 3.4 Layout

- **CMS** (`pawbook.vn/app/{slug}`): sidebar trái 248px (thu gọn 64px); topbar 56px: `Tên shop › Cơ sở ▾`, ô tìm toàn cục (khách, SĐT, tên pet; phím `/`), "+ Đặt lịch" (phím `N`), chip ca thu ngân (nếu đang mở), chuông, avatar. Nội dung max-width 1280px, trừ Lịch full width.
- **Sidebar CMS:** Tổng quan · Tiếp nhận & Hàng đợi · Lịch hẹn · Thu ngân · Khách & Thú cưng · Tiêm phòng · Dịch vụ · Kho (GĐ2) · Nhân viên (Lịch trực; Xếp ca, Chấm công GĐ3) · Lương (GĐ3, icon khóa) · Báo cáo · Cài đặt. Mục không có quyền thì không hiện.
- Tablet < 1024px: sidebar thành drawer. Mobile < 640px: bottom nav 4 mục (Hôm nay, Hàng đợi, Khách, Thêm). Nhân viên spa trên điện thoại vào thẳng Hàng đợi spa.
- **Site** (`pawbook.vn/{slug}`): một cột, max-width 480px; header logo + tên shop + "Đặt lịch" + "Đăng nhập"/avatar; footer "Điều khoản sử dụng · Đặt lịch bởi PawBook". Sau đăng nhập: **bottom nav 4 mục** Trang chủ · Lịch hẹn · Thú cưng · Tài khoản; chừa `env(safe-area-inset-bottom)`, CTA chính dính đáy trên bottom nav.
- **Admin** (`pawbook.vn/admin`): sidebar nền tối, logo "PawBook Admin", badge môi trường (Production đỏ / Demo xám). Nhận ra ngay không phải app của shop.

### 3.5 Component

| Component | Ghi chú |
|---|---|
| **Button** | primary, secondary, ghost, danger, link; sm/md/lg; loading giữ width; disabled kèm tooltip lý do |
| **Input / Select / Textarea** | label trên, helper dưới, lỗi đỏ kèm icon; SĐT tiền tố `+84`; cân nặng hậu tố `kg` |
| **Combobox khách** | tìm theo tên/SĐT, hiện SĐT + số pet; dòng cuối "+ Tạo khách mới" |
| **Date picker / Slot picker** | ngày đóng cửa gạch chéo; slot dạng chip 15 phút, hiện "còn N chỗ", disabled khi hết |
| **Badge** | trạng thái (3.1), nhóm dịch vụ, vai trò, tiêm (Đúng hạn / Sắp đến hạn / Quá hạn), "Online", "Cấp cứu", "Cận date" |
| **Avatar** | nhân viên (ring màu), pet (icon loài nếu chưa có ảnh) |
| **Pet chip** | avatar + tên + loài/giống + cỡ + cân nặng; **badge an toàn** (dị ứng, bệnh nền, lưu ý hành vi như "cắn người", "sợ máy sấy") luôn đi kèm |
| **Card** | chuẩn, KPI (số + delta + sparkline), cảnh báo |
| **Table** | header sticky, sort, hover, menu `…`, phân trang, chọn nhiều |
| **Calendar block** | viền trái màu nhân viên, nền theo nhóm dịch vụ, dòng 1 giờ + tên pet (nhiều pet: "Mochi, Bơ"), dòng 2 dịch vụ, icon trạng thái; buffer dọn dẹp sọc chéo cuối block |
| **Capacity bar** | trên lưới lịch mỗi khung: "Khám 2/3 · Spa 1/2" (lịch / người trực); vượt = viền warning |
| **Drawer phải** | 480px, chi tiết lịch / pet không rời màn |
| **Modal** | xác nhận hủy, dời, sửa hóa đơn; luôn có thoát + Esc |
| **Toast** | góc phải dưới, 5s, "Hoàn tác" khi hủy lịch |
| **Banner** | hạn mức gói, hạn dùng, tạm khóa, đăng nhập thay, hóa đơn chưa hoàn thành từ ngày trước |
| **Empty state, Skeleton** | minh họa đơn giản + 1 câu + 1 CTA; skeleton cho bảng, card, lưới |
| **Stepper** | đặt lịch trên Site (5 bước), phiếu chuyển kho |
| **Timeline** | lịch sử dịch vụ / bệnh án / tiêm của pet |
| **Tabs, Segmented** | Ngày / Tuần / Theo nhân viên ở Lịch |

---

## 4. Kiến trúc thông tin

Cùng domain, tách theo đường dẫn và đăng nhập:

| | **Site** (khách) | **CMS** (nhân viên shop) | **Admin** (nhà cung cấp) |
|---|---|---|---|
| URL | `pawbook.vn/{slug}` | `pawbook.vn/app/{slug}` | `pawbook.vn/admin` |
| Đăng nhập | `/{slug}/dang-nhap`: SĐT + OTP (theo từng shop) | `/login` chung: email + mật khẩu (một tài khoản nhiều shop) | `/admin/login`: email + mật khẩu + 2FA |
| Sau đăng nhập | `/{slug}/tai-khoan` | `/app/{slug}/dashboard` (shop dùng gần nhất) | `/admin` |

Quy tắc: khách mở `/app/*` → về `/login` như người lạ. Phiên khách gắn với shop: đăng nhập Site shop A không có nghĩa đã đăng nhập Site shop B. Nhân viên mở Site thấy như khách vãng lai. Đăng nhập Site xong về trang đang xem dở (chỉ nhận đường dẫn thuộc Site đúng shop). Không có trang Đăng ký: shop do admin tạo, nhân viên vào bằng lời mời.

```
Route trung tâm
├─ Đăng nhập · Quên mật khẩu
├─ Nhận lời mời /invite/{token} (chủ shop do admin mời, nhân viên do chủ mời)
└─ Không còn shop đang hoạt động (bị vô hiệu ở mọi shop, hoặc shop đã hủy) → **không cho đăng nhập**, báo lỗi ngay ở form: "Tài khoản không còn hoạt động ở shop nào. Liên hệ chủ shop." Đang trong phiên mà bị vô hiệu → request kế tiếp đăng xuất về `/login`. Shop đã vô hiệu mình biến khỏi workspace switcher.

CMS /app/{slug}
├─ Tổng quan (S5)
├─ Tiếp nhận (S15) · Hàng đợi (S16) · Visit (S17)
├─ Lịch hẹn (S1 lịch, danh sách) · Đặt lịch (S2) · Chi tiết (S3) · Cần gọi hôm nay (S20)
├─ Thu ngân (S18) · Bán lẻ (S19) · Trả hàng (S31) · Ca thu ngân (S35)
├─ Khách & Thú cưng (S10, S4)
├─ Tiêm phòng (S7)
├─ Dịch vụ (S9)
├─ Kho, GĐ2 (S22, S23, S24)
├─ Nhân viên (S11) · Lịch trực (S8) · Xếp ca, Chấm công, Bảng công (GĐ3: S26, S27, S28)
├─ Lương, GĐ3 (S29)
├─ Báo cáo (S30)
└─ Cài đặt (S12): Shop · Cơ sở (giờ mở cửa, tỉ lệ đặt online, két) · Phân quyền · Kênh nhắn tin · Site (giới thiệu, Điều khoản) · Nhật ký (S32) · Gói & hạn dùng · Lịch sử truy cập hỗ trợ

Site /{slug}
├─ Trang chủ shop (giới thiệu, cơ sở, giờ mở cửa, gọi, chỉ đường) · Dịch vụ & giá · Sản phẩm (GĐ2, S25)
├─ Đặt lịch (S6, 5 bước)
├─ Quản lý lịch hẹn qua link trong tin nhắn (S14)
├─ Đăng nhập OTP + Tài khoản (S21)
└─ Điều khoản sử dụng (S33)

Admin /admin
├─ Đăng nhập + 2FA (A1)
├─ Shop: danh sách (A2) · tạo (A3) · chi tiết (A4)
├─ Nhật ký hoạt động (A5)
└─ Quản lý admin (A6)
```

---

## 5. Danh sách màn hình

Mã, tên, đường dẫn, giai đoạn, ưu tiên (**P0** vẽ trước, **P1** bố cục chuẩn) và thứ tự đưa Claude Design của **mọi** màn (K0–K4, S1–S43, A1–A7, màn con `Sx.n`) nằm ở **`design/danh-muc-man-hinh.md`**: nguồn mã màn duy nhất. Mục 6 dưới đây chỉ đặc tả nội dung các màn chính; màn không có đặc tả riêng ở đây (S36 Đăng nhập CMS, S37 Quên mật khẩu, S39 Tài khoản cá nhân, S40 Mẫu in, S41 Thông báo, S42 Trang chủ shop, S43 Dịch vụ & giá, A7 Admin nhận lời mời) vẽ theo mô tả ngắn trong danh mục, dùng lại component mục 3.

---

## 6. Màn hình

### 6.1 Lịch hẹn

**S1. Lịch hẹn**
- Toolbar: `‹ Hôm nay ›`, chọn ngày, segmented **Ngày / Tuần / Theo nhân viên**, lọc nhân viên, lọc nhóm (Khám / Spa), "+ Đặt lịch". Lọc theo cơ sở đang chọn.
- **Theo nhân viên** (mặc định của lễ tân): mỗi cột một người trực (avatar, tên, số lịch hôm nay), trục 07:00–21:00, lưới 15 phút, vạch đỏ "bây giờ". Cột đầu **"Chưa phân"** chứa lịch online chưa gán người (badge "Online", dòng "Mong muốn: BS. Thu Hà" nếu khách ghi).
- **Capacity bar** trên mỗi khung giờ: "Khám 2/3 · Spa 1/2" (lịch đang chiếm chỗ / người trực); vượt số người trực thì viền warning.
- Ngoài khung trực tô xám sọc; ngày nghỉ của nhân viên là block xám "Nghỉ"; cơ sở đóng cửa: overlay "Cơ sở nghỉ: Quốc khánh 2/9".
- Lịch nhiều pet: một block, dòng 1 "14:00 · Mochi, Bơ", tooltip liệt kê từng pet + dịch vụ. Lịch có cả khám và spa hiện 2 block (mỗi nhóm một block) nối bằng icon liên kết.
- Click ô trống → S2 điền sẵn giờ (và người nếu click vào cột người). Click block → S3.
- Kéo-thả để dời: ô hợp lệ primary-soft; ô vượt số người trực viền warning + tooltip ("Đã có 3 lịch khám / 3 bác sĩ trực"), vẫn thả được sau khi xác nhận.
- Nhân viên spa trên điện thoại: chỉ thấy lịch của mình + "Chưa phân" nhóm spa, dạng list theo giờ.

**S2. Đặt lịch (CMS)**
Drawer phải 480px, form dọc:
1. **Khách**: combobox SĐT/tên, hoặc tạo nhanh (tên + SĐT).
2. **Thú cưng & dịch vụ**: danh sách dòng **pet × dịch vụ**. Tick một hoặc nhiều pet của khách (pet chip), mỗi pet chọn dịch vụ; thêm pet nhanh (tên, loài, giống, cân nặng → gợi ý cỡ). Mỗi dòng hiện thời lượng + giá tham khảo đã tính theo pet ("90 phút · áp dụng cho Chó cỡ L"). Một pet vừa khám vừa spa = 2 dòng. Combo chỉ có ở dịch vụ spa.
3. **Ngày & giờ**: date picker + lưới slot. Mặc định mọi dòng bắt đầu cùng giờ; chip mỗi slot hiện còn bao nhiêu chỗ theo nhóm.
4. **Người làm** (mỗi dòng): "Chưa phân, phân khi khách đến" (mặc định) hoặc chọn người đang trực nhóm đó.
5. Ghi chú. Nhắc khách tự động qua Zalo, SMS dự phòng; ô "Không gửi nhắc".
- Thanh tóm tắt dính đáy: `Mochi, Bơ · 2 dịch vụ · 14:00–15:30 T5 16/10 · 650.000 ₫ (tham khảo)` + "Xác nhận đặt lịch". Dòng nhỏ "Giá tham khảo, có thể thay đổi theo thực tế".
- **Trạng thái:**
  - Khung đã đủ số người trực: cảnh báo vàng "14:00 đã có 3 lịch khám / 3 bác sĩ trực", **vẫn cho lưu** (lễ tân quyết; lịch lễ tân đặt không tính vào hạn mức online).
  - Người được chọn đã có lịch chồng giờ: cảnh báo inline.
  - Pet đã có lịch chồng giờ: cảnh báo inline ở dòng đó.
  - Submit có loading, chặn double-click.
  - Hết hạn mức gói: banner + nút disabled "Đã đạt 500/500 lịch tháng này".

**S3. Chi tiết lịch hẹn (drawer)**
- Header: mã lịch (mono), badge trạng thái, nguồn (Online / Lễ tân), menu `…` (Dời, Hủy, In phiếu). Lịch đặt với "Không gửi nhắc" có badge "Không nhắc".
- Khối khách (SĐT click-to-call, "Nhắn Zalo"). Danh sách **dòng pet × dịch vụ**: pet chip + badge an toàn, dịch vụ, thời lượng, giá tham khảo, người làm (hoặc "Chưa phân"), trạng thái dòng.
- **Action theo trạng thái:** `confirmed` → "Check-in" (chọn pet nào đã đến, phân người làm cho lịch chưa gán; mỗi pet một Visit + số thứ tự cho mỗi nhóm: khám + tiêm = 1 Visit khám, khám + spa = 2 Visit), "Không đến", "Dời", "Hủy" (cả lịch hoặc từng pet); `checked_in` → "Mở Visit" (S17). Không có nút "Hoàn thành": hoàn tất làm ở Visit.
- Khối y tế theo quyền: bác sĩ thấy tóm tắt lần khám gần nhất; lễ tân, spa chỉ thấy **cảnh báo + ngày khám gần nhất**; thu ngân không có khối này.
- **Lịch sử:** đổi giờ, hủy, check-in (ai, lúc nào, lý do); tin nhắn: "Đã gửi Zalo nhắc 24h · 15/10 14:00", "Zalo lỗi → đã gửi SMS", "SMS lỗi · Thử lại".
- Hủy: modal chọn lý do + "Báo khách"; toast "Hoàn tác".

**S20. Cần gọi hôm nay**
- Chuông + trang danh sách lịch **ngày mai** của cơ sở: giờ, khách, SĐT (click-to-call), pet, dịch vụ, trạng thái nhắc tự động (Zalo/SMS đã gửi / lỗi).
- Action: "Đã gọi · Khách xác nhận", "Dời" (modal của S3), "Không liên lạc được".

### 6.2 Site của shop (mobile-first)

**S6. Đặt lịch** — stepper **5 bước**, nút chính dính đáy (44px, accent):
1. **Cơ sở + dịch vụ**: chọn cơ sở (ẩn khi shop có 1 cơ sở); ảnh, địa chỉ, giờ mở cửa; dịch vụ công khai dạng card ("từ 250.000 ₫", "45–90 phút").
2. **Xác minh**: đã đăng nhập thì bỏ qua. Chưa: SĐT → OTP 6 số; SĐT chưa có hồ sơ thì nhập họ tên để tạo tài khoản. Không gợi ý pet trước khi xác minh.
3. **Thú cưng**: chọn **một hoặc nhiều** pet đã có, hoặc thêm mới (loài chip lớn, giống, cân nặng); mỗi pet chọn dịch vụ (mặc định dịch vụ ở bước 1). Hiện thời lượng + giá tham khảo từng pet.
4. **Chọn giờ**: dải 14 ngày (ngày đóng cửa disabled), lưới slot theo **hạn mức online** còn lại, đủ chỗ cho **mọi** pet đã chọn mới hiện là còn trống ("Còn chỗ cho 2 bé"). Không chọn nhân viên; ô "Người bạn muốn (không bắt buộc)". Empty: "Hết chỗ ngày này · Xem ngày gần nhất còn trống".
5. **Xác nhận**: tóm tắt từng pet, ghi chú, "Shop sẽ nhắc lịch qua Zalo hoặc SMS tới số {SĐT}", "Giá tham khảo, có thể thay đổi theo thực tế", link điều khoản, tick đồng ý chính sách hủy ("Đổi/hủy miễn phí trước 24 giờ"). Lịch **xác nhận ngay**.
- **Thành công:** check lớn, mã lịch, "Thêm vào lịch" (.ics), link quản lý (cũng gửi trong tin Zalo/SMS xác nhận).
- **Slot vừa hết:** quay về bước 4, slot gạch, gợi ý giờ gần nhất đủ chỗ.
- Giờ theo múi giờ shop; khác thiết bị thì ghi "Giờ theo shop (GMT+7)".

**S14. Quản lý lịch hẹn** (link trong tin nhắn, không cần đăng nhập): xem chi tiết, Dời (về bước 4), Hủy cả lịch hoặc từng pet (lý do). Trong vòng hạn đổi/hủy (mặc định 24 giờ, chủ shop cài): nút disabled + "Vui lòng gọi shop để đổi lịch trong vòng 24 giờ".

**S21. Đăng nhập OTP + Tài khoản**
- Đăng nhập: SĐT → OTP 6 số (đếm ngược 60s gửi lại, tối đa 5 lần/giờ; sai 5 lần khóa 15 phút). Lần đầu với SĐT chưa có hồ sơ: nhập họ tên → tạo tài khoản. Phiên giữ 30 ngày trên thiết bị.
- Trang chủ: lịch sắp tới + "Đặt lịch". Lịch hẹn: sắp tới (Dời / Hủy), đã qua. Thú cưng: hồ sơ, **lịch sử khám/spa** (ngày, cơ sở, dịch vụ), sổ tiêm, lịch tái khám. Chi tiết một lần khám: chẩn đoán, hướng dẫn chăm sóc, thuốc đã kê, ngày tái khám, kết quả xét nghiệm bác sĩ tick "Chia sẻ cho khách"; **không bao giờ** có ghi chú nội bộ của bác sĩ. Hóa đơn đã hoàn thành của mình.

**S25. Trưng bày sản phẩm** (GĐ2): lưới 2 cột (ảnh, tên, giá, "Còn hàng tại CN Quận 3"), lọc bằng chip. **Không có giỏ hàng**; CTA "Đến shop mua" + chỉ đường.

**S33. Điều khoản sử dụng:** `/{slug}/dieu-khoan` (chữ 16px, mục lục neo), link ở footer và cạnh nút xác nhận S6. CMS › Cài đặt › Site › Điều khoản: trình soạn đơn giản, "Dùng mẫu gợi ý", ngày cập nhật, ghi chú "Chủ shop tự rà soát nội dung theo quy định áp dụng". Tại quầy không bắt khách đồng ý.

**Quy tắc mobile cho Site**
- Touch target ≥ 44px, chữ body 16px, một cột, không bảng ngang.
- SĐT `inputmode="tel"`; OTP 6 ô `autocomplete="one-time-code"`; ngày giờ là dải chip vuốt ngang.
- Một hành động chính dính đáy; hành động phụ trong bottom sheet.
- Click-to-call, chỉ đường (Google Maps), chia sẻ lịch (.ics / Zalo).
- PWA nhẹ (manifest, icon); không cần offline. Trang đầu < 200KB JS, ảnh lazy-load, skeleton.
- Đủ trạng thái: chưa đăng nhập, OTP sai/hết hạn, chưa có pet, chưa có lịch, mất mạng.

### 6.3 Tiếp nhận, hàng đợi, Visit

**S15. Tiếp nhận**
- Ô lớn "Nhập SĐT khách" (focus sẵn, phím `F2`). Kết quả: khách + pet chip. Có lịch hôm nay ở cơ sở này: card "Có lịch 14:00 · Mochi (Tắm + cắt tỉa), Bơ (Khám)" + **Check-in** (tick pet đã đến; lịch online chưa phân người thì chọn người làm, gợi ý người khách mong muốn và người trực đang rảnh).
- Không tìm thấy: tạo nhanh (họ tên, SĐT bắt buộc, địa chỉ tùy chọn) → thêm pet (tên, loài, giống, cân nặng, dị ứng, bệnh nền, triệt sản). SĐT trùng thì mở hồ sơ cũ.
- Khách đã có **hóa đơn chưa hoàn thành** hôm nay ở cơ sở này: banner "Đang mở hóa đơn · 1 Visit (Mochi)"; Visit mới tự gắn vào hóa đơn đó.
- Tạo Visit: tick một hoặc nhiều pet; mỗi pet chọn **Khám** hoặc **Spa** (pet làm cả hai thì tạo 2 Visit) + **lý do đến, tình trạng ban đầu** (nhãn nhỏ "Ghi lời chủ nuôi, không ghi chẩn đoán") + cân nặng hôm nay → "Tạo Visit" → số thứ tự lớn (`Số 042`) mỗi Visit + In phiếu số.
- Nút đỏ **"Cấp cứu"**: tạo Visit khám ngay chỉ với nhãn ("Chó cấp cứu 14:05") + loài + tình trạng; hồ sơ khách, pet làm song song, gắn vào Visit sau. Visit lên đầu hàng đợi.
- Nút phụ **"Thú hoang / vô chủ"**: tạo pet "Thú hoang #12" (sửa tên được) trong hồ sơ hệ thống của cơ sở, bắt buộc loài + cân nặng ước lượng + tình trạng. Visit thú hoang **không tự mở hóa đơn**; có người trả thì tạo hóa đơn ở S18 với tên, SĐT người trả.
- Khách **chỉ mua hàng** không qua màn này, dùng S19.

**S16. Hàng đợi hôm nay**
- Tab **Khám** · **Spa**. Cột: Đang chờ · Đang thực hiện · Hoàn tất.
- Thẻ: số thứ tự (mono, lớn), pet chip + badge an toàn, giờ đến, loại ("Có hẹn 14:00" / "Vãng lai"), thời gian chờ (warning > 30 phút, danger > 60 phút), người phụ trách. Thẻ Hoàn tất có badge hóa đơn ("Chưa thu" / "Đã thu").
- Thứ tự: **cấp cứu** luôn đầu (viền đỏ, badge "Cấp cứu"); khách có hẹn đến trong ±15 phút giờ hẹn xếp theo giờ hẹn; đến muộn hơn xếp như vãng lai theo giờ đến. Số trên phiếu không đổi khi có cấp cứu chen.
- Bác sĩ/spa bấm **"Nhận khách"** → S17. Người khác vừa nhận: toast "Số 042 đã được BS. Thu Hà nhận", thẻ tự cập nhật.
- Chế độ TV phòng chờ (tùy chọn): chỉ số thứ tự + tên pet, không SĐT.

**S17. Visit**
- Header: số thứ tự, pet chip lớn + cảnh báo, khách, tình trạng ban đầu lễ tân ghi, cân nặng hôm nay, 3 lần gần nhất (thu gọn). Visit cấp cứu chưa gắn hồ sơ: banner "Chưa gắn khách/pet · Lễ tân đang làm hồ sơ", không hoàn tất được.
- **Khám:**
  - Khối **bệnh án** (một dòng chi tiết cho Visit này): triệu chứng, sinh hiệu, chẩn đoán, điều trị, **hướng dẫn chăm sóc** (khách thấy), **lịch tái khám**, chuyển tuyến (nếu có), **ghi chú nội bộ** (nền xám, "Không hiện cho khách"). Số hồ sơ cấp ở lần khám đầu. File đính kèm có ô "Chia sẻ cho khách" (mặc định tắt).
  - Khối **Dịch vụ**: thêm từ danh mục (giá hiện hành tự điền). Xét nghiệm, chẩn đoán hình ảnh là dịch vụ; kết quả ghi chữ + đính kèm ảnh/PDF. Phát sinh: báo giá miệng, khách đồng ý thì thêm dòng.
  - Khối **Thuốc kê**: GĐ2 ô tìm thuốc hiện tồn khả dụng tại cơ sở + lô sẽ dùng (FIFO, badge "Cận date"); hết hàng → "CN Quận 7 còn 12 · Tạo yêu cầu chuyển" (S23) / đổi thuốc / ghi "Mua ngoài" vào hướng dẫn. MVP: thuốc nhập dạng dòng gõ tay ở hóa đơn.
  - Khối **Tiêm**: lịch sử tiêm + phác đồ; "Ghi mũi tiêm" (vaccine, số lô, hạn dùng lô, người tiêm) → "Mũi kế tiếp dự kiến 06/11/2026, sẽ nhắc trước 7 ngày"; hoặc "Hoãn" (lý do + ngày hẹn tiêm lại).
- **Spa:** dịch vụ/combo spa (mở rộng xem các bước), ghi chú (dầu gội, tình trạng lông/da, sự cố), **ảnh lúc nhận và lúc trả** (tùy chọn). Thấy dấu hiệu bệnh: "Báo lễ tân tạo Visit khám".
- **Vật tư đã dùng** (GĐ2, cả khám và spa): thêm dịch vụ thì vật tư định mức tự điền theo cỡ pet (nhãn "Gợi ý"); sửa số thực tế, "Đổi vật tư", xóa. Pet dị ứng: dòng vật tư khớp từ khóa bị viền đỏ + gợi ý đổi. Vật tư không tính tiền riêng.
- Footer: tạm tính (giá bán) + **"Hoàn tất Visit"** → Hoàn tất, lễ tân nhận thông báo, khách spa nhận tin "mời đến đón"; GĐ2 trừ kho thuốc + vật tư lúc này (thiếu hàng thì báo, không hoàn tất được).
- Sau hoàn tất, dòng bệnh án sửa được **24 giờ, chỉ người lập** (đồng hồ "Còn sửa được 5 giờ"); sau đó chỉ "Thêm ghi chú bổ sung".
- Lễ tân mở Visit: read-only, khóa + "Chỉ bác sĩ/nhân viên spa được chỉnh sửa".

### 6.4 Thu ngân

**S18. Thu ngân**
- Trái: **"Hóa đơn chưa hoàn thành"** của cơ sở, mỗi thẻ một khách: "2 pet · 1 đang khám, 1 xong", badge "Sẵn sàng thu" khi mọi Visit xong. Nhóm riêng **"Từ ngày trước"** (badge warning, đếm số) để dọn.
- Phải: hóa đơn, dòng **nhóm theo pet** (Mochi: tắm, cắt móng · Bơ: khám, thuốc) + nhóm "Mua thêm" + "**Thêm dòng gõ tay**" (tên + giá). Dòng từ Visit tự xuất hiện khi bác sĩ/spa ghi và **chỉ đọc** ở đây (icon khóa, tooltip "Sửa ở Visit"); dòng mua thêm, gõ tay sửa/xóa tự do. Thuế, tổng. **Không có giảm giá tay.**
- **"In tạm tính"**: watermark chéo + tiêu đề "TẠM TÍNH, chưa phải hóa đơn", không có số.
- **"Hoàn thành & thu tiền"** disabled khi còn Visit chưa xong (tooltip "Mochi đang làm spa"). Bước xác nhận lại các khoản với khách.
- Thanh toán: thu đủ một lần, không cọc, không nợ; **chia nhiều phương thức** (Tiền mặt / Chuyển khoản / QR / Thẻ); tiền mặt có "Khách đưa" → "Tiền thối". Chưa mở ca thu ngân thì tiền mặt disabled + link "Mở ca" (S35). Thu đủ → **Hoàn thành**, cấp số hóa đơn (`Q3-20261016-0042`).
- **"Hủy hóa đơn"** (lễ tân, thu ngân, QL chi nhánh; hóa đơn chưa hoàn thành): lý do bắt buộc; chủ shop nhận thông báo; Visit quay về chưa gắn hóa đơn.
- Đã hoàn thành: **khóa**, badge "Hoàn thành"; lễ tân chỉ còn In / "Trả hàng" (S31). **Chỉ chủ shop có "Sửa"**: lý do bắt buộc → bản thay thế chép từ bản cũ → sửa dòng → lưu = bản cũ "Đã hủy" + bản mới số mới ("Thay thế cho Q3-…-0042"), tiền đã thu chuyển sang bản mới (bút toán chuyển, không tính vào Thực thu và tiền mặt ca), màn hiện "Thu thêm 50.000 ₫" hoặc "Trả lại khách 30.000 ₫". Hóa đơn đã có phiếu trả hàng thì nút "Sửa" khóa.
- Hóa đơn thú hoang / người khác trả: ô "Người thanh toán" (tên, SĐT).
- Nút thu chặn double-click; hóa đơn vừa bị người khác hủy → banner + tải lại. Màn thu ngân **không hiện giá vốn** với bất kỳ ai.

**S19. Bán lẻ tại quầy**
- Kiểu POS: quét mã vạch / tìm sản phẩm, giỏ bên phải, thanh toán như S18. **Không bắt buộc khách**; ô "SĐT khách (không bắt buộc)": khớp khách có sẵn thì gắn hồ sơ, không khớp thì chỉ in trên hóa đơn, không tạo hồ sơ. MVP chỉ có dòng gõ tay (sản phẩm từ GĐ2).
- Bán được thức ăn, đồ chơi, phụ kiện, thuốc không kê đơn. Thuốc kê đơn và dịch vụ không thêm được (tooltip "Phải qua Visit").
- Chọn đơn vị (Gói / Thùng 24); quét mã thùng tự chọn Thùng. Dòng phụ lô sẽ xuất (FIFO): "Lô L1 · HSD 31/12/2026", badge "Cận date"; lấy từ 2 lô hiện cả hai. Người có quyền thấy "Đổi lô" (lý do).

**S31. Trả hàng** (GĐ2)
- Mở hóa đơn đã hoàn thành (số hóa đơn hoặc SĐT) › "Trả hàng": chọn **sản phẩm** + số lượng (không vượt số đã mua trừ số đã trả; dịch vụ không trả được), lý do, **kiểm tay từng dòng**: Nguyên vẹn (cộng tồn ngay) · Lỗi (vào "Hàng lỗi chờ xử lý", chủ quyết) · Hết hạn (hủy). Hiện lô sẽ hoàn lại.
- Hạn trả mặc định 7 ngày; quá hạn: nút khóa + "Quá hạn trả, cần chủ shop", chủ vẫn làm được.
- Hoàn tiền mặt (cần ca đang mở) hoặc chuyển khoản (mã giao dịch); trừ doanh thu ngày trả; chủ nhận thông báo; ghi Nhật ký.

**S35. Ca thu ngân**
- "**Mở ca**": chọn két của cơ sở, nhập tiền đầu ca. Topbar hiện chip "Ca thu ngân · Két 1 · mở 07:58". Mỗi két một ca mở; nhiều người thu chung ca, mỗi khoản ghi tên người thu.
- "**Đóng ca**": hiện tiền mặt dự kiến (đầu ca + thu tiền mặt − hoàn tiền mặt), nhập số đếm; lệch thì bắt buộc lý do, chủ nhận thông báo. Báo cáo ca: tiền mặt, chuyển khoản, QR, thẻ, hoàn tiền, theo người thu; in được.
- Chủ thấy mọi ca, ca lệch nổi bật; quản lý chi nhánh xem ca cơ sở mình.

### 6.5 Khách, thú cưng, tiêm phòng, dịch vụ

**S4. Hồ sơ thú cưng**
- Header: avatar, tên, loài · giống · giới tính · tuổi · cân nặng · cỡ · triệt sản; khách (link); **badge cảnh báo** (dị ứng, bệnh nền) ai cũng thấy; "Đặt lịch cho bé".
- Tabs:
  - **Tổng quan:** lịch sắp tới, mũi tiêm sắp đến hạn, tái khám, ghi chú.
  - **Lịch sử dịch vụ:** timeline mọi Visit (ngày, **cơ sở**, khám/spa, tóm tắt). Người có quyền bấm mở chi tiết; người không có quyền thấy icon khóa.
  - **Bệnh án:** **số hồ sơ** (`HS-000123`) ở đầu; timeline **dòng chi tiết** (ngày, cơ sở, bác sĩ, chẩn đoán, điều trị, hướng dẫn, thuốc đã kê, tái khám, file đính kèm, ghi chú bổ sung); khối ghi chú nội bộ nền xám "Không hiện cho khách". **Bác sĩ:** xem đủ mọi cơ sở; sửa dòng **do mình lập trong 24 giờ**, sau đó "Thêm ghi chú bổ sung". **Lễ tân, spa, QL chi nhánh:** chỉ cảnh báo + danh sách ngày khám, khóa + "Nội dung bệnh án chỉ bác sĩ xem". **Thu ngân, kho:** không có tab.
  - **Tiêm phòng & tẩy giun:** bảng theo phác đồ (vaccine, mũi, ngày tiêm, người tiêm, số lô, **ngày đến hạn kế tiếp** + badge), mũi hoãn có lý do; mini timeline tiến độ phác đồ (3/4 mũi).

**S7. Tiêm phòng đến hạn**
- Tabs **Quá hạn** · **7 ngày tới** · **30 ngày tới**. Bảng: pet, khách + SĐT, vaccine/thuốc, mũi, ngày đến hạn, trạng thái nhắc (Chưa gửi / Đã gửi Zalo 12/10 / Lỗi), action "Đặt lịch tiêm", "Gửi nhắc lại", "Đã tiêm ở nơi khác".
- Lọc: loài, loại (vaccine / tẩy giun), bác sĩ.

**S10. Khách:** bảng (tên, SĐT, số pet, lần đến gần nhất, tổng chi). Chi tiết: liên hệ (tên, SĐT, địa chỉ; không hỏi CCCD), pet, lịch sử lịch hẹn, hóa đơn.

**S9. Dịch vụ**
- Danh sách: mã, tên, nhóm (Khám / Spa, badge màu), badge **Combo**, thời lượng, buffer, giá, hiện trên Site, cơ sở áp dụng, đang hoạt động.
- Form combo (**chỉ nhóm Spa**): mã, tên, giá combo riêng (hiện tổng giá lẻ để tham khảo), chọn dịch vụ spa con theo thứ tự, thời lượng (gợi ý = tổng). Khám + spa không gộp combo: là các dịch vụ riêng, gom chung hóa đơn.
- Chi tiết dịch vụ: bảng **quy tắc thời lượng/giá** (loài, giống, cỡ → phút, giá) + ô "Thử tính". Tab **Vật tư định mức** (GĐ2): sản phẩm, lượng theo cỡ, ghi chú "Chỉ là gợi ý, nhân viên sửa theo thực tế".

### 6.6 Nhân viên, lịch trực

**S8. Lịch trực & ngày nghỉ**
- Lưới tuần (T2–CN) mỗi nhân viên, mỗi ngày nhiều khung (ca gãy). Mỗi khung chọn **cơ sở** và **trực nhóm nào** (Khám / Spa / Không tính: lễ tân, thu ngân, kho). Người có cả role bác sĩ và spa vẫn chỉ là **một** người trực trong khung đó. "Áp dụng từ ngày…".
- Không cho 2 khung chồng giờ của cùng người, kể cả khác cơ sở (báo lỗi ngay trên lưới).
- Ngày nghỉ: khoảng thời gian, lý do; trùng lịch đã gán người → "3 lịch bị ảnh hưởng" + link xử lý.
- Thanh dưới lưới: số người trực theo nhóm mỗi ngày, kèm "Đặt online tối đa: Khám 1 · Spa 1" theo tỉ lệ của cơ sở.

**S11. Nhân viên & lời mời:** bảng (avatar, tên, role, cơ sở chính/phụ, trạng thái); lời mời chọn một hoặc nhiều role + cơ sở chính/phụ; thêm nhân viên **không có tài khoản** (bảo vệ, lao công; đặt PIN chấm công, GĐ3). Form có khối **Chuyên môn** (số chứng chỉ hành nghề, bằng cấp) khi có role Bác sĩ. Gán nhiều role. Tab Lời mời (email, role, hết hạn, gửi lại/thu hồi). Đạt giới hạn gói: "Mời" disabled + "Liên hệ nâng gói".

**S13. Nhận lời mời** (`/invite/{token}`): logo + tên shop, "Bạn được mời làm {role} tại {shop}"; chưa có tài khoản → tên + mật khẩu; đã có → "Đăng nhập để nhận" (email khác email được mời thì báo lỗi). Hết hạn → "Link đã hết hạn, liên hệ người mời". Chủ shop vào lần đầu thấy checklist: Thêm cơ sở, Thêm dịch vụ, Mời nhân viên, Đặt lịch trực, Đặt tỉ lệ đặt online, Chia sẻ link Site.

### 6.7 Tổng quan, báo cáo, cài đặt

**S5. Tổng quan**
- KPI: Lịch hôm nay (đã đến / tổng), Doanh thu hôm nay, Tỉ lệ không đến 30 ngày, Mũi tiêm đến hạn 7 ngày.
- Lịch hôm nay theo giờ, check-in nhanh. Tiêm phòng đến hạn (5 dòng + "Xem tất cả").
- Biểu đồ lịch 14 ngày (cột chồng theo nhóm). Top dịch vụ.
- **Cảnh báo cần xử lý:** "Hóa đơn chưa hoàn thành từ ngày trước: 3" (lễ tân, thu ngân, chủ), "Hóa đơn đã hủy hôm nay" (chủ: số, người hủy, lý do), "Ca thu ngân lệch tiền" (chủ), "Tin nhắn gửi lỗi", GĐ2 "Kho cần xử lý" (Sắp hết · Hết hàng · Cận date · Phiếu chờ điền giá · Phiếu chờ duyệt), GĐ3 "Chấm công ngoài lịch chờ duyệt".
- **Hạn mức gói** (chủ): "Lịch tháng 10: 412/500", "Nhân viên: 4/5", "Cơ sở: 2/3"; vàng ≥ 80%, đỏ 100%.
- Chủ: lọc cơ sở hoặc "Tất cả cơ sở". Lễ tân, spa, bác sĩ: bản rút gọn, **chỉ số hôm nay của cơ sở mình**, không hạn mức gói; lễ tân thấy thêm doanh thu hôm nay của cơ sở mình (quyền `reports.view_today`), bác sĩ và spa không thấy doanh thu.

**S30. Báo cáo doanh thu & lãi gộp**
- Lọc: tuần, tháng, khoảng tùy chọn; từng cơ sở hoặc "Tất cả".
- KPI: Doanh thu dịch vụ · Doanh thu bán lẻ · (GĐ2) Giá vốn hàng bán · Giá vốn vật tư · **Lãi gộp** (+ % biên). Chú thích: "Doanh thu trước thuế, theo ngày hoàn thành hóa đơn; trả hàng trừ vào ngày trả. Lãi gộp = Doanh thu − Giá vốn hàng bán − Giá vốn vật tư; chưa trừ lương, chi phí". Thẻ riêng **Thực thu** (dòng tiền theo ngày thu).
- Mục "Hóa đơn đã hủy" theo ngày (người hủy, lý do). Cảnh báo "N phiếu nhập chưa điền giá, lãi gộp chưa đủ".
- QL chi nhánh: cơ sở mình, không có giá vốn, lãi gộp. MVP: các cột giá vốn hiện "Có khi dùng Kho".

**S32. Nhật ký thao tác** (chủ): thời gian, người, cơ sở, hành động (Hoàn thành / Hủy / Sửa hóa đơn, Hoàn tiền, Trả hàng, Điều chỉnh kho, Duyệt phiếu, Đổi giá, Đổi quyền, Sửa bệnh án, Đóng ca lệch tiền…), đối tượng (link), lý do; click xem trước/sau. Chỉ đọc.

**S12. Cài đặt** (chủ)
- **Shop:** tên, slug Site (kiểm trùng), múi giờ (mọi cơ sở dùng chung), thuế mặc định, hạn khách tự đổi/hủy lịch (giờ, mặc định 24), hạn trả hàng (ngày, mặc định 7).
- **Cơ sở:** mã, tên, địa chỉ, SĐT, giờ mở cửa, ngày nghỉ, dịch vụ có ở cơ sở; **tỉ lệ đặt online** Khám / Spa (vd 1/3; ghi đè theo thứ/khung giờ) kèm ví dụ "3 bác sĩ trực × 1/3 = 1 lịch online cùng lúc"; két; IP mạng chấm công (GĐ3).
- **Phân quyền:** danh sách role (đổi tên hiển thị được), ma trận quyền tick được nhóm theo module (tên + mô tả quyền do hệ thống ghi, không sửa), tạo role mới; role Chủ shop khóa. Quyền nhạy cảm (xem giá vốn, điền giá nhập, sửa hóa đơn đã hoàn thành, mọi cơ sở, xem lương) có badge "Nhạy cảm"; tick cho role khác → modal xác nhận "Nhân viên có role này sẽ thấy giá vốn…", ghi Nhật ký. Quyền "Phân quyền" không tick được cho role khác.
- **Kênh nhắn tin:** kết nối Zalo OA (ZNS, mẫu tin đã duyệt), nhà cung cấp SMS (brandname), gửi tin thử, số tin đã gửi tháng này, mẫu nội dung (xác nhận, nhắc 24h, nhắc 2h, nhắc tiêm, nhắc tái khám, mời đến đón). Ghi rõ: "Gửi Zalo trước, không gửi được mới gửi SMS".
- **Site:** ảnh bìa, giới thiệu, dịch vụ hiển thị, bật/tắt Sản phẩm (GĐ2), Điều khoản (S33).
- **Gói & hạn dùng:** gói hiện tại, hạn, hạn mức đã dùng, lịch sử gia hạn, "Liên hệ gia hạn" (không thanh toán online). **Lịch sử truy cập hỗ trợ** (admin đăng nhập thay: ai, khi nào, lý do).

### 6.8 Kho (GĐ2)

**S22. Kho**
- Tabs: **Tồn kho** · **Lô & hạn dùng** · **Sổ kho** · **Cảnh báo** · **Hàng lỗi chờ xử lý** (chủ).
- Tồn kho: sản phẩm, loại (Thuốc / Vật tư / Bán lẻ), tồn, đang giữ, khả dụng, đơn vị (kèm quy đổi "30 gói (1 thùng 6 gói)"), giá bán; **cột Giá vốn, Giá trị tồn chỉ người có quyền xem giá vốn** (mặc định chủ) (role khác không có cột trong dữ liệu).
- Lô & hạn dùng: mỗi sản phẩm liệt kê lô theo thứ tự **nhập trước** (FIFO), lô đầu nhãn "Xuất trước"; HSD, số lượng, "Còn … ngày"; lọc **Cận date** · **Quá hạn** · Tất cả. Lô quá hạn gạch ngang, không xuất được. Lô nhập sau mà hạn gần hơn: badge "Cận date" để chủ xử lý (FIFO không tự ưu tiên).
- Cảnh báo: Sắp hết / Hết hàng / Cận date / Quá hạn; action "Đã xem", "Tạo yêu cầu chuyển", "Báo hủy" (lô quá hạn, tạo phiếu hao hụt).
- Hàng lỗi chờ xử lý (chủ): hàng khách trả bị lỗi; action Hủy / Trả nhà cung cấp / Chuyển sang mã hàng lỗi để bán giá thấp.
- Cài ngưỡng (chủ): "Báo sắp hết khi còn ≤ [10] gói" (mặc định mọi cơ sở, ghi đè từng cơ sở), "Báo cận date trước [60] ngày" (trống = theo nhóm sản phẩm, mặc định 30).
- Form sản phẩm (chủ): SKU, mã vạch, tên, nhóm, loại, **bán lẻ được** (tick), **thuốc kê đơn** (tick), đơn vị nhỏ nhất + đơn vị quy đổi, **giá bán** (một giá hiện hành cho mọi lô), theo lô, ngưỡng.

**S23. Tra tồn cơ sở khác + chuyển kho**
- Tìm sản phẩm → bảng cơ sở: **khả dụng**, HSD gần nhất (không giá vốn). Cơ sở hiện tại ở dòng đầu.
- "**Yêu cầu chuyển**": cơ sở nguồn, số lượng, gắn khách đang chờ + "hẹn lấy ngày 18/10" → phiếu; cơ sở nguồn giữ hàng.
- Phiếu: tabs Gửi đi / Nhận về; stepper **Yêu cầu → Đã duyệt → Đang vận chuyển → Đã nhận** (nhánh: Nhận thiếu, Trả về, Đã hủy). Nguồn: duyệt/từ chối, lô xuất (FIFO), người giao (tên, SĐT). Nhận: số thực nhận; lệch thì "Nhận thiếu" + lý do.
- Nhận xong: thông báo lễ tân "Thuốc XYZ cho khách Nguyễn Thị Lan đã về, giữ đến 19/10".

**S24. Phiếu nhập, hao hụt, kiểm kê, nhà cung cấp**
- **Phiếu nhập 2 bước.** Bước 1 (nhân viên kho, cơ sở nhận hàng): nhà cung cấp, số hóa đơn NCC, dòng (sản phẩm, đơn vị nhập "2 thùng = 48 gói", lô, HSD dạng tháng/năm, số lượng); **không có cột giá**; "Lưu & nhập kho" = **cộng tồn ngay**, sau đó không sửa số lượng. Bỏ trống số lô → tự sinh theo mã phiếu. Bước 2 (chủ): danh sách "**Phiếu chờ điền giá**" (badge đếm ở sidebar Kho), nhập đơn giá từng dòng → tổng → "Lưu giá".
- **Phiếu hao hụt:** sản phẩm + lô, số lượng, lý do (Chuột cắn, Vỡ/hỏng, Hết hạn, Mất, Khác), ảnh tùy chọn → "Gửi duyệt". Chủ / QL chi nhánh: tab "**Chờ duyệt**" → Duyệt (trừ tồn) / Từ chối (lý do).
- **Kiểm kê** theo lô: nhập số đếm, hiện chênh lệch + lý do → gửi duyệt → duyệt mới ghi sổ.
- **Nhà cung cấp:** tên, SĐT, mã số thuế, địa chỉ.

### 6.9 Ca, chấm công, lương (GĐ3)

**S26. Xếp ca + đăng ký ca**
- Lưới tuần: hàng = nhân viên (cả người không có tài khoản), cột = ngày, ô = chip ca (Sáng / Chiều / Tối, ca tối icon trăng) kèm cơ sở và nhóm trực. Kéo-thả để xếp; không cho 2 ca chồng giờ dù khác cơ sở.
- Ô có **đăng ký** của nhân viên: chip viền đứt "Đã đăng ký"; chủ ✓ Nhận / ✗ Từ chối. Lọc "Chỉ ô có đăng ký".
- "**Ca của tôi**" (nhân viên, dùng được trên điện thoại): lưới 2 tuần tới, tick ca có thể làm, ghi chú; thấy Chờ duyệt / Đã nhận / Từ chối.
- Thanh phủ ca: "Bác sĩ 1/2 · Lễ tân 1/1 · Bảo vệ 0/1" (thiếu = danger). Đổi ca: chủ sửa tay (ghi Nhật ký).
- Cài đặt mẫu ca (giờ, ngày hoạt động) và số người tối thiểu theo role.

**S27. Chấm công**
- Nhân viên có tài khoản: nút lớn "Chấm công vào / ra" ở topbar và Tổng quan, kèm ca hôm nay ("Ca sáng 07:30–12:00 · CN Quận 3"). Có ca: cơ sở tự lấy theo ca (kiểm IP nếu cơ sở có cài; sai mạng → "Bạn không ở mạng của CN Quận 3"). Không có ca: chọn cơ sở, ghi "Ngoài lịch", chờ chủ duyệt.
- Kiosk (chỉ người không có tài khoản): toàn màn hình trên tablet, đồng hồ, tên cơ sở, bàn phím "Mã nhân viên" → "PIN" → "Chào Phạm Quốc Bảo · Vào ca 07:58". Lỗi: sai PIN, thiết bị chưa đăng ký.

**S28. Bảng công** (chủ): bảng nhân viên × ngày, ô giờ vào/ra; "Quên chấm ra" (hệ thống tự đóng lúc hết ca) và "Ngoài lịch" cần duyệt; sửa giờ bắt buộc lý do, có lịch sử. Đi trễ/về sớm chỉ hiển thị.

**S29. Lương**
- Ai vào: chủ và người được ủy quyền (quyền "Xem lương"). Người khác: không có menu, gọi thẳng URL cũng bị chặn.
- Vào trang: "Nhập lại mật khẩu để xem lương" → mở 10 phút, đếm ngược ("Tự khóa sau 09:12"), "Khóa ngay".
- Kỳ lương theo kiểu chủ chọn ở Cài đặt (**tuần hoặc tháng**, một kiểu cho cả shop): nhân viên, số ca sáng/chiều/tối đã chấm hợp lệ, **tiền dự kiến** (= Σ đơn giá ca). Click dòng → từng ca.
- Đơn giá ca: bảng nhân viên × ca, áp dụng từ ngày.
- "**Chốt kỳ**" → khóa. Tab "Lịch sử truy cập".

### 6.10 Admin nền tảng

**Thứ tự:** admin có trước → admin tạo shop + mời chủ shop → chủ nhận lời mời, vào CMS → chủ mời nhân viên, cấu hình → khách dùng Site.

| Vai trò | Quyền |
|---|---|
| **Super-admin** | Toàn quyền khu admin: tạo/khóa shop, gói, gia hạn, đăng nhập thay, quản lý admin |
| **Support** | Xem shop, đăng nhập thay (có lý do); không khóa shop, đổi gói, gia hạn |

- **A1. Đăng nhập:** email + mật khẩu, bước 2 mã 6 số (TOTP). Không có Đăng ký. Lỗi thông báo chung; khóa tạm sau 5 lần sai.
- **A2. Danh sách shop:** KPI (tổng, hoạt động, sắp hết hạn ≤ 7 ngày, quá hạn/tạm khóa). Bảng: tên + slug, chủ (tên, SĐT), gói, **hạn dùng** (đỏ quá hạn, vàng ≤ 7 ngày), trạng thái (Dùng thử, Hoạt động, Quá hạn, Tạm khóa, Đã hủy), số cơ sở, nhân viên, lịch tháng này. Lọc, tìm, "+ Tạo shop".
- **A3. Tạo shop** (wizard 3 bước): (1) tên, slug (kiểm trùng realtime, xem trước `pawbook.vn/paw-house`), loại hình (spa / thú y / cả hai, chỉ để gợi ý dịch vụ mẫu), múi giờ, cơ sở đầu tiên; (2) chủ shop: họ tên, email, SĐT ("Chủ shop sẽ nhận email mời"); (3) gói, dùng thử N ngày **hoặc** đã bán (từ ngày, đến ngày, số tiền, mã chuyển khoản), ghi chú. Thành công: mở A4, "Gửi lại lời mời", "Sao chép link mời".
- **A4. Chi tiết shop:** header (tên, trạng thái, gói + hạn, "Đăng nhập như chủ shop", menu Tạm khóa / Mở khóa / Hủy). Tabs: Tổng quan (thông tin, trạng thái lời mời, hạn mức đã dùng, ghi chú; **không** hiện khách, pet, hóa đơn) · Gói & gia hạn (lịch sử; "Gia hạn": gói, số tháng, tự nối hạn, số tiền, mã CK) · Truy cập hỗ trợ · Nhật ký. Modal Tạm khóa (lý do; "Nhân viên vẫn xem được nhưng không thao tác; Site ngừng nhận lịch"). Modal Đăng nhập thay (lý do bắt buộc; "Tối đa 30 phút, mọi thao tác được ghi lại, chủ shop xem được").
- **A5. Nhật ký hoạt động:** thời gian, admin, shop, hành động ("Tạo shop", "Gia hạn 3 tháng gói Pro", "Tạm khóa: Chưa thanh toán", "Bắt đầu đăng nhập thay"), diff trước/sau. Chỉ đọc.
- **A6. Quản lý admin** (P1): bảng admin (tên, email, vai trò, 2FA, đăng nhập cuối, trạng thái); thêm = gửi lời mời qua email (hạn 72 giờ) → **A7** admin mới đặt mật khẩu rồi bắt buộc bật 2FA; vô hiệu hóa, không xóa; không tự vô hiệu chính mình.
- **Trong CMS:** banner đăng nhập thay (đỏ, cố định): "Admin Nguyễn Văn A đang xem với tư cách Trần Minh Khoa · còn 24:10 · Thoát"; nút bị chặn trong phiên disabled + tooltip. Banner hạn dùng: vàng "Gói Pro hết hạn ngày 24/10/2026, liên hệ PawBook để gia hạn"; đỏ khi quá hạn "Đã quá hạn 3 ngày. Tài khoản sẽ tạm khóa sau 4 ngày nữa". Tạm khóa: banner đỏ toàn app, mọi nút tạo/sửa disabled, xem và xuất vẫn được.

---

## 7. Luồng chính

- **F1. Lễ tân đặt lịch qua điện thoại (< 60 giây):** `N` → S2 → gõ 4 số cuối SĐT → chọn khách → tick "Mochi" và "Bơ" → Mochi "Tắm + cắt tỉa" (60' cỡ S), Bơ "Khám tổng quát" → để "Chưa phân" → chọn ngày, slot 14:00 → Xác nhận → toast "Đã đặt lịch cho Mochi, Bơ lúc 14:00 T5 16/10". Slot vừa đầy (vượt người trực) → cảnh báo vàng, lễ tân vẫn lưu được.
- **F2. Khách tự đặt trên Site:** link từ Facebook/Zalo của shop → S6 (cơ sở + dịch vụ → OTP → chọn 2 bé → giờ còn chỗ cho 2 bé → xác nhận) → tin Zalo xác nhận (lỗi thì SMS) → 24 giờ và 2 giờ trước nhận nhắc có link S14.
- **F3. Khách có hẹn đến:** S15 nhập SĐT → card "Có lịch 14:00" → Check-in, tick 2 pet, phân người cho dòng chưa phân → 2 Visit (Số 041, 042) + một hóa đơn chưa hoàn thành → S16.
- **F4. Một Visit khám từ đầu đến cuối:** S16 bác sĩ "Nhận khách" → S17 ghi bệnh án, thêm xét nghiệm (khách đồng ý), đính kèm kết quả, kê thuốc, hẹn tái khám → "Hoàn tất Visit" (GĐ2 trừ kho thuốc) → trong lúc chờ, lễ tân thêm thức ăn vào hóa đơn → S18 "In tạm tính" → khách xác nhận → thu (tiền mặt + QR) → Hoàn thành, khóa → hệ thống tạo nhắc tái khám.
- **F5. Spa:** S16 spa nhận → ảnh lúc nhận → làm → sửa vật tư thực tế → ảnh lúc trả → "Hoàn tất Visit" → khách nhận tin "mời đến đón" → S18 thu tiền. Thấy dấu hiệu bệnh → báo lễ tân → tạo thêm Visit khám, cùng hóa đơn.
- **F6. Tiêm:** S17 khám điều kiện → "Ghi mũi tiêm" (vaccine, số lô, hạn lô) → "Mũi 3 đến hạn 06/11/2026" → hoàn tất. Không đủ điều kiện → "Hoãn" + ngày hẹn mới → hệ thống nhắc theo ngày mới.
- **F7. Cấp cứu:** S15 "Cấp cứu" → Visit lên đầu hàng đợi, pet vào khám ngay → lễ tân làm hồ sơ khách, pet song song rồi gắn vào Visit → bác sĩ hoàn tất → thu tiền.
- **F8. Thú hoang:** S15 "Thú hoang / vô chủ" → Visit (không mở hóa đơn) → khám → có người trả thì S18 tạo hóa đơn ghi tên, SĐT người trả.
- **F9. Khách chỉ mua hàng:** S19 quét sản phẩm → (tùy chọn SĐT) → thu tiền. Không tạo hồ sơ.
- **F10. Chủ sửa hóa đơn đã hoàn thành:** S18 "Sửa" + lý do → sửa dòng → lưu → bản cũ Đã hủy, bản thay thế số mới → "Trả lại khách 30.000 ₫" → ghi hoàn tiền → Nhật ký.
- **F11. Dọn hóa đơn chưa hoàn thành:** đầu ngày chuông báo "3 hóa đơn chưa hoàn thành từ ngày trước" → S18 nhóm "Từ ngày trước" → thu nốt (khách quay lại trả) hoặc hủy có lý do.
- **F12. Ca thu ngân:** đầu ca S35 mở ca, đếm két → thu trong ca → cuối ca đóng, đếm, lệch thì ghi lý do → chủ nhận thông báo.
- **F13. Theo dõi tiêm đến hạn:** S5 card "Mũi tiêm đến hạn: 12" → S7 tab Quá hạn → gọi khách → "Đặt lịch tiêm" (S2 điền sẵn) hoặc "Gửi nhắc lại".
- **F14. Hết thuốc, cơ sở khác còn (GĐ2):** S17 kê thuốc → "Hết tại CN Q3 · CN Q7 còn 12" → hẹn khách → S23 yêu cầu chuyển (Q7 giữ hàng) → Q7 duyệt, giao → Q3 nhận → thông báo lễ tân, giữ hàng 48 giờ → khách đến, S18 bán.
- **F15. Nhập hàng (GĐ2):** hàng về CN Q3 → nhân viên kho S24 lập phiếu (không giá) → "Lưu & nhập kho", tồn cộng ngay → chủ thấy badge "Phiếu chờ điền giá" → điền giá → báo cáo lãi gộp đủ dữ liệu.
- **F16. Khách trả hàng (GĐ2):** S31 tìm hóa đơn → chọn 1 bịch thức ăn → kiểm tay "Nguyên vẹn" → hoàn tiền mặt từ két → tồn cộng lại đúng lô, doanh thu ngày trả giảm.
- **F17. Đăng ký ca, chấm công, lương (GĐ3):** nhân viên đăng ký ca → chủ nhận ở S26 → nhân viên chấm công trên CMS (không tài khoản: kiosk) → chủ duyệt ở S28 → cuối kỳ S29 mở khóa, xem tiền dự kiến, chốt kỳ.
- **F18. Mời nhân viên, đổi shop:** chủ mời email + role → nhân viên mở `/invite/{token}` (chưa có tài khoản thì tạo, có rồi thì đăng nhập) → vào CMS. Người làm ở 2 shop dùng switcher; tên + logo shop hiện tại luôn rõ.
- **F19. Admin bán shop mới:** `php artisan platform:admin-create` (một lần) → A1 + bật 2FA → A3 tạo shop (gói Pro, 12 tháng, mã CK) → chủ nhận mail mời → tạo tài khoản → vào CMS với checklist.
- **F20. Hết hạn, gia hạn:** còn 7 ngày: banner vàng + mail → quá hạn: banner đỏ 7 ngày → tự tạm khóa → khách chuyển khoản → admin A4 "Gia hạn" → về Hoạt động ngay.
- **F21. Hỗ trợ khách:** chủ shop gọi báo lỗi → support A4 "Đăng nhập như chủ shop" (lý do) → tab mới có banner đỏ → kiểm tra → Thoát.

---

## 8. Ma trận quyền của role mẫu

Chủ shop đổi tên hiển thị, sửa quyền từng role và tạo role mới (danh mục quyền do hệ thống định nghĩa, không tạo quyền mới); role Chủ shop khóa. Một người nhiều role thì cộng quyền; mọi role trừ người có quyền "mọi cơ sở" chỉ thao tác ở cơ sở được gán. Chủ shop không tự nhận Visit khám, ghi chẩn đoán, kê thuốc (kiêm bác sĩ thì gán thêm role Bác sĩ). Ô ghi "nhạy cảm" mặc định chỉ Chủ shop, giao được cho role khác kèm xác nhận + Nhật ký; quyền Phân quyền không giao được. Thao tác nhạy cảm ghi Nhật ký.

| Dữ liệu / việc | Chủ shop | QL chi nhánh | Lễ tân | Thu ngân | Bác sĩ | Spa | Kho | Khách (Site) |
|---|---|---|---|---|---|---|---|---|
| Hồ sơ khách | Toàn quyền | Ghi | Ghi | Xem | Xem | Xem | — | Của mình |
| Hồ sơ pet, cảnh báo dị ứng | Toàn quyền | Ghi | Ghi | Xem | Ghi | Xem | — | Của mình |
| Nội dung bệnh án | Xem | Cảnh báo | Cảnh báo, ngày khám | — | Ghi (dòng mình, 24 giờ) | Cảnh báo | — | Xem, trừ ghi chú nội bộ |
| Sổ tiêm | Xem | Xem | Xem | — | Ghi | Xem | — | Xem |
| Lịch hẹn | Toàn quyền | Ghi | Ghi | — | Xem | Xem | — | Đặt, đổi, hủy của mình |
| Lịch trực, tỉ lệ đặt online | Toàn quyền | Ghi (cơ sở mình) | Xem | Xem của mình | Xem của mình | Xem của mình | Xem của mình | — |
| Tiếp nhận, tạo Visit, hàng đợi | Toàn quyền | Ghi | Ghi | — | Xem | Xem | — | — |
| Nhận, hoàn tất Visit khám/tiêm | — (gán thêm role Bác sĩ) | — | — | — | Ghi | — | — | — |
| Nhận, hoàn tất Visit spa | Ghi | — | — | — | — | Ghi | — | — |
| Kê thuốc, xét nghiệm | — | — | — | — | Ghi | — | Xem (GĐ2) | — |
| Hóa đơn chưa hoàn thành: thêm dòng, thu tiền | Toàn quyền | Ghi | Ghi | Ghi | — | — | — | — |
| Hủy hóa đơn chưa hoàn thành | Toàn quyền | Ghi | Ghi, có lý do | Ghi, có lý do | — | — | — | — |
| Sửa hóa đơn đã hoàn thành (nhạy cảm, chủ giao được) | Sửa (bản thay thế) | — | — | — | — | — | — | — |
| Trả hàng, hoàn tiền (GĐ2) | Toàn quyền (cả quá hạn) | Ghi | Ghi, trong hạn | Ghi, trong hạn | — | — | — | — |
| Mở, đóng ca thu ngân | Toàn quyền | Xem | Ghi | Ghi | — | — | — | — |
| Xem hóa đơn đã hoàn thành | Xem | Xem | Xem, in | Xem, in | — | — | — | Của mình |
| Xem tồn cơ sở mình, tra tồn cơ sở khác (GĐ2) | Xem | Xem | Xem | Xem | Xem | Xem | Xem | — |
| Lập phiếu nhập, kiểm kê (GĐ2) | Toàn quyền | Duyệt kiểm kê | — | — | — | — | Ghi, không thấy giá | — |
| Điền giá nhập, xem giá vốn (GĐ2) (nhạy cảm, chủ giao được) | Toàn quyền | — | — | — | — | — | — | — |
| Báo hao hụt, hủy hàng hết hạn (GĐ2) | Toàn quyền | Duyệt | — | — | — | — | Ghi, cần duyệt | — |
| Xử lý hàng lỗi (GĐ2) | Toàn quyền | — | — | — | — | — | Xem | — |
| Chuyển kho (GĐ2) | Toàn quyền | Duyệt | Tạo yêu cầu | — | Tạo yêu cầu | — | Ghi (giao, nhận) | — |
| Danh mục dịch vụ, sản phẩm, giá bán | Toàn quyền | Xem | Xem | Xem | Xem | Xem | Xem | Xem dịch vụ |
| Nhân viên, role, quyền (Phân quyền chỉ Chủ shop) | Toàn quyền | Xem | — | — | — | — | — | — |
| Xếp ca, duyệt chấm công (GĐ3) | Toàn quyền | Xếp ca cơ sở mình | — | — | — | — | — | — |
| Lương (GĐ3) (nhạy cảm, chủ giao được) | Toàn quyền | — | — | — | — | — | — | — |
| Báo cáo doanh thu, lãi gộp | Mọi cơ sở | Cơ sở mình, không lãi gộp | Hôm nay, cơ sở mình | Báo cáo ca | — | — | — | — |
| Nhật ký thao tác | Xem | — | — | — | — | — | — | — |
| Cài đặt shop, cơ sở | Toàn quyền | Xem | — | — | — | — | — | — |

---

## 9. Dữ liệu mẫu

**Shop:** "Paw House Spa & Vet", slug `paw-house`, múi giờ `Asia/Ho_Chi_Minh`. Cơ sở **CN Quận 3** (`Q3`, chính, 08:00–20:00, nghỉ 02/09) và **CN Quận 7** (`Q7`). Tỉ lệ đặt online Q3: Khám 1/3, Spa 1/2.

**Nhân viên:**

| Tên | Role | Cơ sở | Màu |
|---|---|---|---|
| Trần Minh Khoa | Chủ shop + Bác sĩ | Q3, Q7 | `#0D9488` |
| BS. Lê Thu Hà | Bác sĩ | Q3 | `#8B5CF6` |
| BS. Phan Đức Trí | Bác sĩ | Q3 | `#22C55E` |
| Nguyễn Ngọc Anh | Spa | Q3 | `#F97362` |
| Phạm Quốc Bảo | Spa | Q3, Q7 | `#0EA5E9` |
| Võ Thị Mai | Lễ tân + Thu ngân | Q3 | `#EAB308` |
| Đỗ Văn Hùng | Quản lý chi nhánh | Q7 | `#EC4899` |
| Lý Thị Kim | Nhân viên kho | Q3 | `#64748B` |
| Lê Văn Tư | Bảo vệ (không tài khoản) | Q3 | — |

**Dịch vụ:**

| Dịch vụ | Nhóm | Thời lượng | Giá |
|---|---|---|---|
| Tắm + sấy | Spa | S 45' · M 60' · L 90' | 200.000–400.000 ₫ |
| Cắt tỉa | Spa | S 45' · M 60' · L 90' | 200.000–350.000 ₫ |
| Combo Cắt tỉa + tắm gội (`CB01`) | Spa, combo | S 75' · M 105' · L 150' | 350.000–650.000 ₫ (giá riêng) |
| Cắt móng + vệ sinh tai | Spa | 20' | 80.000 ₫ |
| Khám tổng quát | Khám | 30' | 150.000 ₫ |
| Tiêm phòng | Khám | 15' | 250.000–450.000 ₫ |
| Xét nghiệm máu | Khám | 20' | 300.000 ₫ |
| Siêu âm | Khám | 20' | 250.000 ₫ |

**Khách & thú cưng:**

| Khách | SĐT | Thú cưng |
|---|---|---|
| Nguyễn Thị Lan | 0903 123 456 | **Mochi** (Poodle, cái, 3 tuổi, 5,2kg, S, dị ứng dầu gội hương liệu, HS-000101); **Bơ** (Mèo Anh lông ngắn, đực, 2 tuổi, 4,8kg) |
| Hoàng Văn Nam | 0912 987 654 | **Lu** (Golden Retriever, đực, 5 tuổi, 32kg, L, "sợ máy sấy") |
| Đặng Mỹ Linh | 0938 555 222 | **Sushi** (Corgi, cái, 6 tháng, 9kg, M, đang tiêm mũi 2/4 vaccine 7 bệnh) |
| Bùi Gia Huy | 0977 246 810 | **Tôm** (Mèo ta, đực, 1 tuổi, 3,5kg, quá hạn tẩy giun 12 ngày) |

**Phác đồ:** Vaccine 7 bệnh (chó): mũi 1 lúc 6 tuần tuổi, mũi 2 +21 ngày, mũi 3 +21 ngày, nhắc lại mỗi 365 ngày. Tẩy giun: lặp mỗi 90 ngày.

**Gói:** Starter (1 cơ sở, 3 nhân viên, 150 lịch/tháng) · **Pro** (3 cơ sở, 10 nhân viên, 500 lịch/tháng, đang dùng) · Clinic (không giới hạn).

**Hôm nay (T5 16/10/2026) ở Q3:** 14 lịch đủ trạng thái, có lịch 2 pet (Mochi + Bơ, 14:00), 2 lịch online chưa phân; một nhân viên có ca gãy, một người nghỉ buổi chiều; hàng đợi 9 Visit (6 khám, 3 spa), 1 cấp cứu, 1 "Thú hoang #3" (mèo, ~2kg, bị thương chân); 2 hóa đơn chưa hoàn thành từ hôm qua; ca thu ngân Két 1 đang mở.

**Kho (GĐ2):** "Amoxicillin 250mg" hết ở Q3, Q7 còn 12 viên (lô `AMX2409`, HSD 03/2027); dầu gội "Bio-Groom" sắp hết ở Q3; "Royal Canin Mini Adult 2kg" giá bán 420.000 ₫; thức ăn A1 lô `L1` HSD 31/12/2026 còn 6 gói (Cận date), lô `L2` HSD 30/06/2027 còn 24 gói; 1 phiếu nhập chờ điền giá, 1 phiếu hao hụt "chuột cắn 2 bịch" chờ duyệt.

**Lương (GĐ3):** Ngọc Anh ca sáng 180.000 ₫, chiều 180.000 ₫, tối 220.000 ₫; Lê Văn Tư ca tối 250.000 ₫.

**Admin:** Nguyễn Văn A (Super-admin), Lê Thị Hỗ Trợ (Support). Shop: Paw House (Pro, hết hạn 15/10/2027, Hoạt động), Meow Clinic (Starter, quá hạn 3 ngày), Happy Tails (Dùng thử, còn 5 ngày), Bé Cún Spa (Tạm khóa: Chưa thanh toán).

---

## 10. Quy tắc UX bắt buộc

1. **Tách biệt shop:** tên shop + cơ sở luôn ở topbar; đổi shop có màn chuyển tiếp; không trộn dữ liệu 2 shop trên một màn. Màn vận hành hiện badge cơ sở ở tiêu đề.
2. **Đặt vượt sức chứa:** Site chỉ hiện slot còn đủ chỗ cho mọi pet; server từ chối thì báo thân thiện + gợi ý giờ khác, không mất dữ liệu form. CMS cảnh báo nhưng cho lưu. Nút submit chặn bấm 2 lần.
3. **Minh bạch thời lượng, giá:** luôn cho biết vì sao ("áp dụng cho Chó cỡ L"); giá trên lịch luôn ghi "tham khảo".
4. **Múi giờ:** mọi giờ theo giờ shop; thiết bị khác múi giờ thì ghi chú.
5. **Phân quyền nhìn thấy được:** read-only có khóa + giải thích; dữ liệu không có quyền thì không có trong trang.
6. **An toàn của pet nổi bật:** dị ứng, bệnh nền, "cắn người", "sợ máy sấy" là badge ở mọi nơi có pet.
7. **Site không lộ dữ liệu:** URL dùng mã ngẫu nhiên; không gợi ý pet trước khi xác minh OTP; không bao giờ có ghi chú nội bộ.
8. **Tiền:** hóa đơn đã hoàn thành không có nút sửa với người không phải chủ; mọi hủy/sửa/hoàn tiền bắt buộc lý do.
9. **Accessibility:** điều hướng bàn phím trên lịch, focus ring rõ, `aria-live` cho toast và lỗi slot, touch target ≥ 44px trên mobile.

---

## 11. Ngoài phạm vi

Lưu chuồng; thành viên, tích điểm, hạng; khuyến mãi theo chiến dịch; gợi ý/phân công bác sĩ theo chuyên môn; app native; chat với khách; bán hàng online / giỏ hàng (sản phẩm chỉ trưng bày); thanh toán gói online; domain riêng cho shop; đa ngôn ngữ ngoài tiếng Việt; lương tháng, phụ cấp, thưởng/phạt.
