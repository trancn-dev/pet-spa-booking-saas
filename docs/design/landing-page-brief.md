# Brief landing page: PawBook

Brief để dán thẳng vào **Claude Design**, thiết kế trang giới thiệu sản phẩm PawBook tại `pawbook.vn/`. Mọi nội dung lấy từ tài liệu chốt: `project-brief.md`, `docs/analysis/nghiep-vu-da-chot.md`, `docs/design/design.md`, `docs/design/danh-muc-man-hinh.md`, `docs/plan/implementation-plan.md`. Tên "PawBook" là tên tạm.

Đính kèm khi gửi Claude Design: file này + `docs/design/design.md` + `design/skills/frontend-design/SKILL.md`. Prompt mẫu ở mục 9.

---

## 1. Tóm tắt

| Mục | Nội dung |
|---|---|
| Trang | Landing page giới thiệu, một trang dài, có neo cho từng section |
| URL | `pawbook.vn/` (route trung tâm, không thuộc shop nào) |
| Mã màn đề xuất | **S45 Landing page giới thiệu** (S44 đã cấp cho Phác đồ tiêm ngày 27/10). Chưa có trong `danh-muc-man-hinh.md`, cần chủ dự án xác nhận rồi mới ghi vào. Route name đề xuất `home`, trang Vue `pages/Site/Home.vue` |
| Mục tiêu chính | Chủ pet spa / phòng khám thú y hiểu sản phẩm trong 1 phút và **để lại thông tin để được tư vấn** hoặc **xem demo** |
| Mục tiêu phụ | Người xem CV / nhà tuyển dụng thấy được độ sâu nghiệp vụ và có link tới mã nguồn |
| Thiết bị | **Desktop 1440px** là khung chính, **mobile 390px** bắt buộc (thử 360px), tablet 1024px chỉ cần không vỡ |
| Ngôn ngữ | Tiếng Việt. Tiền `350.000 ₫`, giờ 24h |
| Theme | Light là khung chính, có bản dark |

**Ràng buộc nghiệp vụ ảnh hưởng tới trang:**
- **Không có tự đăng ký.** Nhà cung cấp tạo shop, gán gói, gửi email mời chủ shop. Vì vậy CTA chính là "Nhận tư vấn", không phải "Đăng ký miễn phí".
- **Chưa thu phí online.** Gói và hạn do PawBook gán tay, nên bảng giá không có nút thanh toán.
- Không có số liệu khách hàng thật, không có testimonial. **Không bịa** logo đối tác, số shop đang dùng, đánh giá sao hay lời khen của khách.

---

## 2. Định vị

**Một câu:** PawBook là phần mềm quản lý đặt lịch, tiếp nhận, bệnh án và thu tiền cho pet spa và phòng khám thú y, có trang đặt lịch riêng cho từng shop.

**Đối tượng:**
1. **Chủ shop** (người ra quyết định): pet spa, phòng khám thú y, hoặc vừa spa vừa khám; một hoặc nhiều cơ sở. Lo: quá tải giờ cao điểm, khách quên lịch, sổ tiêm giấy, thu tiền cuối ngày lệch két, không biết doanh thu từng cơ sở.
2. **Nhân viên** (người dùng hằng ngày): lễ tân dùng iPad, bác sĩ, groomer cầm điện thoại. Trang nhắc tới để chủ shop thấy nhân viên dùng được.
3. **Người xem CV**: một section nhỏ cuối trang (mục 4.11).

**Khác biệt cần nói rõ (lấy từ giá trị cốt lõi design.md mục 1):**
1. Đặt online không bao giờ vượt sức phục vụ: số lịch online mỗi khung giờ tự tính theo số người đang trực.
2. Thời lượng và giá tự tính theo loài, giống, cỡ (chó nhỏ 45 phút, chó lớn 90 phút).
3. Một khách mang nhiều bé: một lịch, một hóa đơn.
4. Tự nhắc hẹn, nhắc tiêm, tẩy giun, tái khám qua Zalo, SMS dự phòng.
5. Bệnh án dùng chung mọi cơ sở, phân quyền chặt.

**Giọng văn:** ấm áp, rõ ràng, nói bằng ngôn ngữ của chủ tiệm ("bé", "lịch", "quầy", "két"), không thuật ngữ kỹ thuật ở phần bán hàng. Câu ngắn, chủ động, không phóng đại ("số 1", "cách mạng").

---

## 3. Phong cách hình ảnh

Bám `docs/design/design.md` mục 3. Token của design.md **ưu tiên hơn skill**; skill frontend-design chỉ dùng để nâng chất lượng bố cục, nhịp chữ và chi tiết.

**Cảm giác:** ấm áp, sạch sẽ, đáng tin như phòng khám, thân thiện như tiệm spa. Không hoạt hình quá đà; icon dấu chân chỉ điểm xuyết (logo, một chỗ trang trí nhỏ).

**Màu (dùng đúng token):**

| Token | Light | Dark | Dùng trên landing |
|---|---|---|---|
| `--color-bg` | `#FAFAF9` | `#0F1514` | nền trang |
| `--color-surface` | `#FFFFFF` | `#161E1D` | card, mockup |
| `--color-surface-muted` | `#F5F5F4` | `#1D2826` | nền section xen kẽ |
| `--color-border` | `#E7E5E4` | `#2A3735` | viền |
| `--color-text` | `#1C1917` | `#ECF1F0` | chữ chính |
| `--color-text-muted` | `#57534E` | `#9DB0AD` | chữ phụ |
| `--color-primary` | `#0D9488` | `#2DD4BF` | link, nút phụ, mọi thứ thuộc "khám" |
| `--color-primary-soft` | `#CCFBF1` | `#123A36` | nền chip khám |
| `--color-accent` | `#F97362` | `#FB8A7B` | **CTA chính**, mọi thứ thuộc "spa" |
| `--color-accent-soft` | `#FFE4DF` | `#3B201C` | nền chip spa |
| `--color-success / warning / danger / info` | xem design.md 3.1 | | chỉ dùng trong mockup UI |

Quy ước màu xuyên suốt: **teal = khám, coral = spa** (giống CMS). Dùng nó làm ngôn ngữ hình ảnh của trang, ví dụ hai nhóm tính năng, hai cột trong mockup lịch.

**Chữ:** `Be Vietnam Pro` cho toàn trang (hỗ trợ dấu tiếng Việt tốt). `JetBrains Mono` chỉ cho mã thật trong mockup (mã lịch `PB-7K2Q`, số hồ sơ `HS-000101`), không dùng cho nhãn trang trí. Landing cần cỡ lớn hơn CMS, đây là phần mở rộng **chỉ cho landing**:

| Vai trò | Desktop | Mobile | Weight |
|---|---|---|---|
| Hero headline | 56 / 64 | 34 / 42 | 700 |
| Tiêu đề section | 36 / 44 | 26 / 34 | 600 |
| Lead (câu dưới tiêu đề) | 20 / 30 | 17 / 26 | 400 |
| Body | 16 / 26 | 16 / 24 | 400 |

Dòng chữ tối đa ~70 ký tự. Không viết hoa toàn bộ nhãn, không tô màu riêng một chữ trong tiêu đề.

**Spacing, radius, shadow:** spacing 4px base của design.md; khoảng cách section 96px desktop / 64px mobile. Radius `md 10px` cho nút, `lg 16px` cho mockup lớn, `full` cho chip. Nút CTA cao 48px desktop, 44px mobile. Dark mode thay shadow bằng viền.

**Hình ảnh:**
- Hình chính là **ảnh chụp giao diện thật** của PawBook (mockup dựng từ component design.md mục 3.5, dữ liệu mẫu design.md mục 9: shop Paw House, bé Mochi, Bơ, Lu, Sushi, Tôm). Đây là thứ thuyết phục nhất, ưu tiên hơn ảnh minh họa.
- Ảnh thú cưng: 1 đến 2 ảnh chụp thật, tông ấm, ánh sáng tự nhiên (chó được tắm, mèo khám). Không ảnh stock có logo, không ảnh người nhìn thẳng ống kính cười giả.
- Icon: Lucide nét 1.5px.

**Chuyển động:** chỉ một khoảnh khắc ở hero (mục 4.2). Các section khác không fade-in khi cuộn. Tôn trọng `prefers-reduced-motion`.

**Tránh** (theo skill frontend-design): lưới card y hệt nhau có cùng shadow cho mọi section, gradient trang trí, nhãn viết hoa nhỏ trên mọi tiêu đề, số `01 / 02 / 03` cho nội dung không phải trình tự, mũi tên `→` gắn vào mọi nút.

---

## 4. Các section và nội dung chữ

Thứ tự từ trên xuống. Chữ trong ngoặc kép là copy dùng thẳng; Claude Design được sửa nhẹ cho vừa bố cục nhưng **không thêm tính năng ngoài danh sách này**.

### 4.1 Header (dính đầu khi cuộn)
- Trái: logo PawBook (chữ + một dấu chân nhỏ).
- Giữa: "Tính năng" · "Cách hoạt động" · "Bảng giá" · "Câu hỏi" (neo tới section).
- Phải: "Đăng nhập" (ghost, tới `/login`, dành cho nhân viên shop) · **"Nhận tư vấn"** (accent, cuộn tới form mục 4.12).
- Mobile: logo + "Nhận tư vấn" + nút menu mở sheet toàn màn.

### 4.2 Hero
- Headline: **"Lịch hẹn không bao giờ quá tải, khách không bao giờ quên hẹn"**
- Lead: "PawBook giúp pet spa và phòng khám thú y nhận lịch online theo đúng số người đang trực, tự nhắc khách qua Zalo, và chạy liền mạch từ quầy tiếp nhận tới hóa đơn."
- CTA chính: **"Nhận tư vấn"**. CTA phụ: **"Xem shop demo"** (mở `pawbook.vn/paw-house`, Site đặt lịch của shop mẫu).
- Dòng nhỏ dưới CTA: "Dùng trên trình duyệt, không cần cài app. Khách đặt lịch bằng số điện thoại."
- **Hình (điểm nhấn duy nhất của trang):** mockup đặt lịch của Site trên điện thoại (S6 bước 4) đặt cạnh lưới lịch CMS trên laptop. Khoảnh khắc chuyển động: khách chọn 2 bé Mochi + Bơ, lưới slot cập nhật, slot 14:00 hiện "Còn chỗ cho 2 bé", đồng thời trên lưới CMS ô 14:00 xuất hiện block mới và capacity bar đổi từ "Spa 0/2" thành "Spa 2/2", các slot còn lại của khung đó chuyển "Hết chỗ". Chạy một lần khi tải trang; reduced-motion thì hiện trạng thái cuối.

### 4.3 Dành cho ai
Ba lựa chọn nằm ngang (mobile xếp dọc), mỗi cái một câu, không phải card nặng:
- **Pet spa:** "Tắm, cắt tỉa, combo spa. Thời lượng tự tính theo cỡ bé, ảnh lúc nhận và lúc trả."
- **Phòng khám thú y:** "Khám, tiêm phòng, xét nghiệm, siêu âm. Bệnh án và sổ tiêm của từng bé, nhắc mũi kế tiếp tự động."
- **Vừa spa vừa khám:** "Một bé vừa khám vừa spa trong một lần ghé, gom chung một hóa đơn."
- Dòng dưới: "Một shop, nhiều cơ sở: khách, thú cưng, bệnh án và bảng giá dùng chung; lịch, hàng đợi, két tiền riêng từng cơ sở."

### 4.4 Vấn đề quen thuộc
Tiêu đề: "Những việc làm chủ tiệm mệt nhất". Bốn cặp **vấn đề → PawBook làm gì**, trình bày hai cột (trái là câu của chủ tiệm, phải là cách PawBook xử lý):

| Chủ tiệm hay gặp | PawBook |
|---|---|
| "Cuối tuần khách đặt dồn, 3 bé tới cùng giờ mà chỉ có 2 người làm." | Mỗi khung giờ chỉ nhận số lịch online bằng số người trực nhân với tỉ lệ bạn đặt. Người nghỉ thì tự giảm. |
| "Khách quên lịch, quên luôn mũi tiêm nhắc lại." | Nhắc trước 24 giờ và 2 giờ, nhắc tiêm, tẩy giun, tái khám qua Zalo. Không dùng Zalo thì gửi SMS. |
| "Sổ khám giấy, đổi cơ sở là mất lịch sử của bé." | Mỗi bé một hồ sơ, dùng chung mọi cơ sở. Cảnh báo dị ứng, "cắn người", "sợ máy sấy" hiện ở mọi màn. |
| "Cuối ngày đếm két lệch mà không biết lệch ở đâu." | Ca thu ngân theo két: tiền đầu ca, tiền mặt dự kiến, chênh lệch có lý do, ghi tên người thu từng khoản. |

### 4.5 Tính năng chính (đã có trong bản đầu)
Tiêu đề: "Mọi việc ở tiệm, trong một chỗ". Sáu nhóm, mỗi nhóm có tiêu đề, 2 đến 3 gạch đầu dòng và **một mảnh mockup thật** (không dùng icon to làm hình). Bố cục so le trái phải hoặc lưới không đều, tránh 6 card y hệt nhau.

1. **Đặt lịch online theo sức phục vụ**
   - Trang đặt lịch riêng của shop (`pawbook.vn/ten-shop`), khách xác minh bằng mã OTP qua SMS.
   - Một lịch nhiều bé; chỉ hiện giờ còn đủ chỗ cho mọi bé đã chọn.
   - Lịch xác nhận ngay, khách tự đổi hoặc hủy trước 24 giờ (chủ shop cài được).
   - Mockup: S6 bước 4, chip "Còn chỗ cho 2 bé".
2. **Lịch hẹn cho lễ tân**
   - Lưới lịch theo ngày, tuần, theo nhân viên; màu theo nhóm khám / spa.
   - Thanh sức chứa mỗi khung: "Khám 2/3 · Spa 1/2".
   - Giá và thời lượng tự tính theo loài, giống, cỡ, ghi rõ "giá tham khảo".
   - Mockup: S1 lưới lịch ngày 16/10/2026 của CN Quận 3.
3. **Tiếp nhận và hàng đợi**
   - Cấp số thứ tự theo cơ sở, theo ngày; khách có hẹn đến đúng giờ được xếp theo giờ hẹn.
   - Ca cấp cứu chen đầu hàng, có thể nhận bé trước khi làm hồ sơ.
   - Màn hình hàng đợi cho TV phòng chờ.
   - Mockup: S16 hàng đợi có 1 thẻ cấp cứu viền đỏ.
4. **Bệnh án và tiêm phòng**
   - Bệnh án của bé dùng chung mọi cơ sở; bác sĩ ghi chẩn đoán, kê thuốc, đính kèm kết quả xét nghiệm.
   - Sổ tiêm tự tính mũi kế tiếp theo phác đồ, tự nhắc khi đến hạn.
   - Ghi chú nội bộ của bác sĩ không bao giờ hiện cho khách.
   - Mockup: S4 hồ sơ bé Sushi, sổ tiêm "mũi 2/4 vaccine 7 bệnh".
5. **Hóa đơn và thu ngân**
   - Một khách nhiều bé, khám và spa, gom một hóa đơn; trả bằng tiền mặt, chuyển khoản, QR, thẻ.
   - Hóa đơn đã hoàn thành không ai sửa ngầm: chỉ chủ shop sửa, và hệ thống lưu cả bản cũ lẫn bản thay thế.
   - Ca thu ngân theo két, báo chủ khi lệch.
   - Mockup: S18 hóa đơn của chị Lan với Mochi + Bơ.
6. **Nhiều cơ sở, phân quyền rõ ràng**
   - 7 vai trò mẫu: Chủ shop, Quản lý chi nhánh, Lễ tân, Thu ngân, Bác sĩ, Nhân viên spa, Nhân viên kho; chủ shop đổi tên, bật tắt quyền.
   - Việc nhạy cảm (sửa hóa đơn đã thu, xem mọi cơ sở) mặc định chỉ chủ shop, giao cho người khác thì có ghi nhật ký.
   - Báo cáo doanh thu theo cơ sở, theo ngày.
   - Mockup: chọn cơ sở "Paw House › CN Quận 3 ▾" và màn S5 Tổng quan.

### 4.6 Một lượt khách đi qua PawBook
Tiêu đề: "Từ lúc khách bấm đặt tới lúc nhắc mũi tiêm kế tiếp". Đây **là trình tự thật**, nên được dùng bước có số. Dùng câu chuyện dữ liệu mẫu: chị Lan đặt lịch cho Mochi và Bơ.

1. "Chị Lan đặt lịch 14:00 cho Mochi và Bơ trên trang của Paw House, xác minh bằng mã OTP."
2. "Chị nhận tin Zalo xác nhận, rồi tin nhắc trước 24 giờ và 2 giờ."
3. "Tới quầy, lễ tân bấm tiếp nhận: mỗi bé một số thứ tự, mở sẵn một hóa đơn."
4. "Groomer nhận Mochi trên điện thoại, chụp ảnh lúc nhận và lúc trả. Thấy bé có dấu hiệu bệnh thì báo lễ tân thêm lượt khám."
5. "Thu ngân thu một lần cho cả hai bé."
6. "Đến hạn mũi tiêm nhắc lại, PawBook tự gửi tin cho chị Lan."

Desktop: dải ngang 6 bước; mobile: timeline dọc. Màu bước theo nhóm (spa coral, khám teal).

### 4.7 Trang đặt lịch cho khách của bạn
Tiêu đề: "Khách đặt lịch trên điện thoại, không cần cài app". Mockup điện thoại lớn (Site, 390px) kèm 4 ý ngắn:
- "Mỗi shop một địa chỉ riêng: pawbook.vn/ten-shop."
- "Khách xem lịch hẹn, lịch sử khám, sổ tiêm và hóa đơn của bé."
- "Gọi shop, chỉ đường Google Maps, thêm lịch vào điện thoại bằng một chạm."
- "Không thu cọc, không thanh toán trước: khách trả tại shop."

### 4.8 An tâm về dữ liệu
Tiêu đề: "Dữ liệu của tiệm là của tiệm". Ba ý, chữ là chính, ít trang trí:
- "Mỗi shop tách biệt hoàn toàn, không shop nào thấy dữ liệu của shop khác."
- "Ai thấy gì do vai trò quyết định: thu ngân không xem bệnh án, lễ tân chỉ thấy cảnh báo an toàn của bé."
- "Khi đội hỗ trợ PawBook cần vào xem giúp, phiên tối đa 30 phút, có lý do, mọi thao tác được ghi lại và chủ shop xem được."

### 4.9 Sắp có
Tiêu đề: "Đang phát triển tiếp". Hai cột, có badge **"Sắp có"** (màu warning-soft hoặc neutral, không dùng accent để khỏi nhầm với CTA). Không ghi ngày ra mắt.
- **Kho và bán hàng:** "Quản lý tồn theo lô và hạn dùng, xuất trước nhập trước; phiếu nhập, kiểm kê, hao hụt, chuyển kho giữa cơ sở; bán lẻ, trả hàng; báo cáo lãi gộp; trưng bày sản phẩm trên trang của shop."
- **Ca, chấm công, lương:** "Nhân viên đăng ký ca, quản lý xếp ca; chấm công trên điện thoại hoặc máy kiosk; lương theo ca để chủ kiểm rồi chốt kỳ."

**Không nhắc** các mục sau V1 (thành viên, tích điểm, khuyến mãi, lưu chuồng, app native, chat, bán online, thu phí online, domain riêng, đa ngôn ngữ).

### 4.10 Bảng giá
Tiêu đề: "Chọn gói theo quy mô tiệm". Lead: "Mọi gói đều có đủ tính năng hiện có. Gói chỉ khác nhau ở số cơ sở, số nhân viên và số lịch mỗi tháng."

| | **Starter** | **Pro** (nổi bật, nhãn "Phổ biến") | **Clinic** |
|---|---|---|---|
| Hợp với | Tiệm nhỏ, một cơ sở | Tiệm đang mở rộng | Chuỗi, phòng khám lớn |
| Cơ sở | 1 | 3 | Không giới hạn |
| Nhân viên có tài khoản | 3 | 10 | Không giới hạn |
| Lịch hẹn mỗi tháng | 150 | 500 | Không giới hạn |
| Giá | "Liên hệ" | "Liên hệ" | "Liên hệ" |
| Nút | "Nhận báo giá" | "Nhận báo giá" (accent) | "Nhận báo giá" |

Chú thích dưới bảng:
- "Lịch hẹn tính cả lịch khách đặt online và lịch lễ tân tạo. Nhân viên không cần đăng nhập (bảo vệ, lao công) không tính vào giới hạn."
- "Có dùng thử miễn phí. Gia hạn theo tháng; trước khi hết hạn 7 ngày, PawBook nhắc bạn. Quá hạn vẫn dùng tiếp được 7 ngày."

Ghi chú cho thiết kế: **chưa có giá chốt**, hiện "Liên hệ" là đúng nghiệp vụ (gói bán tay). Chừa chỗ để sau này thay bằng số (vd `499.000 ₫/tháng`) mà không vỡ bố cục. Mobile: ba gói xếp dọc, Pro đứng đầu.

### 4.11 Câu hỏi thường gặp
Accordion, mở một câu một lúc.
- **"Làm sao để bắt đầu dùng?"** "Để lại thông tin, PawBook tạo tài khoản cho tiệm và gửi email mời chủ shop. Bạn vào là có sẵn dịch vụ mẫu theo loại hình tiệm, rồi mời nhân viên."
- **"Khách của tôi có phải cài app không?"** "Không. Khách đặt lịch trên trình duyệt điện thoại, xác minh bằng mã OTP gửi qua SMS."
- **"Có thu tiền cọc khi khách đặt online không?"** "Không. Lịch chỉ để giữ chỗ và nhắc hẹn, khách thanh toán tại tiệm."
- **"Tin nhắc gửi qua kênh nào?"** "Zalo trước. Nếu số điện thoại không dùng Zalo hoặc gửi lỗi thì gửi SMS, không gửi trùng cả hai."
- **"Tôi có nhiều cơ sở thì sao?"** "Một tài khoản shop quản lý nhiều cơ sở. Khách, bé, bệnh án và bảng giá dùng chung; lịch, hàng đợi, két tiền riêng. Chủ shop xem được mọi cơ sở."
- **"Hết hạn gói thì dữ liệu có mất không?"** "Không. Sau 7 ngày ân hạn, tiệm tạm khóa: nhân viên vẫn xem được dữ liệu nhưng không tạo mới, trang đặt lịch ngừng nhận lịch. Gia hạn xong mở lại ngay."
- **"Nhân viên dùng trên thiết bị gì?"** "Máy tính, iPad ở quầy, điện thoại cho groomer và bác sĩ. Tất cả chạy trên trình duyệt."

### 4.12 Kêu gọi cuối + form tư vấn
- Tiêu đề: "Để PawBook lo phần lịch hẹn, bạn lo phần các bé".
- Form ngắn (mobile một cột): Họ tên · Số điện thoại (`+84`, `inputmode="tel"`) · Tên tiệm · Loại hình (chip: Pet spa / Thú y / Cả hai) · Số cơ sở (chip: 1 / 2–3 / Hơn 3) · Ghi chú (không bắt buộc).
- Nút: **"Gửi thông tin"**. Dưới nút: "PawBook sẽ gọi lại cho bạn trong giờ làm việc."
- Trạng thái cần vẽ: mặc định, lỗi từng ô (đỏ kèm icon, nói rõ cách sửa: "Số điện thoại cần 10 số, bắt đầu bằng 0"), đang gửi (nút giữ width), gửi thành công ("Đã nhận thông tin của Paw House. PawBook sẽ gọi lại cho bạn."), lỗi mạng (giữ dữ liệu form).
- Bên cạnh form (desktop) hoặc dưới (mobile): "Muốn xem trước?" + link "Xem shop demo".

### 4.13 Dưới nắp (cho người xem kỹ thuật)
Một dải nhỏ, nền `surface-muted`, đặt ngay trên footer, không lấn phần bán hàng:
- Tiêu đề: "PawBook được xây như thế nào".
- 4 ý ngắn: "Laravel 12, Vue 3, MySQL 8, Redis" · "Mỗi shop tách biệt ở tầng dữ liệu, khóa ở cả câu truy vấn lẫn khóa ngoại" · "Chống đặt vượt chỗ khi nhiều khách bấm cùng lúc, có test 20 tiến trình song song" · "Nhắc tin idempotent: gửi lại không bao giờ nhắn trùng".
- Link: "Xem mã nguồn trên GitHub" (`github.com/trancn-dev/pet-spa-booking-saas`) · "Đọc quyết định kiến trúc".

### 4.14 Footer
Logo + một câu mô tả · cột "Sản phẩm" (Tính năng, Bảng giá, Shop demo) · cột "Tài khoản" (Đăng nhập cho nhân viên) · cột "Liên hệ" (email, số điện thoại để placeholder `{email}`, `{sđt}`) · dòng cuối "© 2026 PawBook". Không có link "Đăng ký".

---

## 5. Mockup cần dựng trong trang

Dựng bằng component design.md mục 3.5, dữ liệu design.md mục 9. Chỉ là ảnh tĩnh trong landing, không cần tương tác (trừ chuyển động hero).

| Mockup | Lấy từ | Dùng ở |
|---|---|---|
| Site đặt lịch bước 4, 2 bé, "Còn chỗ cho 2 bé" | S6 | Hero, 4.5 (1), 4.7 |
| Lưới lịch ngày + capacity bar | S1 | Hero, 4.5 (2) |
| Hàng đợi có thẻ cấp cứu | S16 | 4.5 (3) |
| Hồ sơ pet + sổ tiêm | S4 | 4.5 (4) |
| Hóa đơn 2 pet | S18 | 4.5 (5) |
| Tổng quan + chọn cơ sở | S5, K1 | 4.5 (6) |
| Tin nhắn Zalo xác nhận lịch | nghiep-vu-da-chot.md mục 5 | 4.6 |

Mockup trên landing được làm gọn: bỏ sidebar thừa, phóng to phần đang nói tới, giữ đúng màu và nhãn tiếng Việt.

---

## 6. Responsive

| Phần | Desktop 1440px | Mobile 390px |
|---|---|---|
| Khung | Nội dung max-width 1200px, lề 32px | Lề 16px, một cột, không cuộn ngang |
| Header | Nav ngang | Logo + CTA + menu sheet |
| Hero | Chữ trái, mockup laptop + điện thoại phải | Chữ trên, mockup điện thoại dưới (bỏ laptop), CTA full width |
| Tính năng | So le trái phải | Xếp dọc, mockup dưới chữ |
| Hành trình | Dải ngang 6 bước | Timeline dọc |
| Bảng giá | 3 cột | Xếp dọc, Pro đầu |
| Form | 2 cột (form + "Xem demo") | Một cột |

Touch target ≥ 44px, body 16px trên mobile (tránh iOS zoom ô nhập).

---

## 7. Ràng buộc kỹ thuật (để code sau này khớp)

- Trang sẽ code bằng **Laravel + Inertia + Vue 3 + Tailwind**, dùng chung token với CMS. Claude Design xuất HTML/Tailwind tham khảo được.
- Landing là trang công khai, không đăng nhập. **Nhẹ**: JS trang đầu < 200KB như Site; ảnh WebP/AVIF, lazy-load dưới màn đầu; font Be Vietnam Pro chỉ tải weight 400, 600, 700.
- SEO: `<title>` "PawBook: phần mềm đặt lịch cho pet spa và phòng khám thú y", meta description, Open Graph image (hero), `lang="vi"`.
- Accessibility: WCAG AA, focus ring `--focus-ring`, accordion và menu dùng được bằng bàn phím, `aria-live` cho kết quả gửi form.
- Form tư vấn: **chưa có trong tài liệu chốt**. Đề xuất đơn giản nhất: không thêm bảng, gửi email cho admin nền tảng qua queue, có rate limit và honeypot chống spam. Cần chủ dự án quyết trước khi ghi vào plan.
- Link "Xem shop demo" trỏ tới `pawbook.vn/paw-house` (shop mẫu ở design.md mục 9, dùng cho link demo trong README).

---

## 8. Không làm

- Không nút "Đăng ký", "Dùng thử ngay" tự phục vụ, không trang thanh toán.
- Không testimonial, logo khách, con số "1.000+ shop" hay sao đánh giá.
- Không nhắc tính năng sau V1 (mục 4.9).
- Không đa ngôn ngữ, không chat bubble.
- Không ảnh hoạt hình thú cưng kiểu mascot.

---

## 9. Prompt gửi Claude Design

Đính kèm: `docs/design/landing-page-brief.md` (file này), `docs/design/design.md`, `design/skills/frontend-design/SKILL.md`. Rồi gửi:

```
Đây là brief landing page PawBook (landing-page-brief.md), tài liệu thiết kế hệ thống (design.md) và skill frontend-design.

Quy tắc:
- Nội dung, section, copy theo landing-page-brief.md. Không thêm tính năng, số liệu hay testimonial ngoài brief.
- Token màu, font, icon, component theo design.md mục 3 và landing-page-brief.md mục 3. Token ưu tiên hơn skill; skill dùng để nâng bố cục, nhịp chữ và chi tiết, và để tránh các mẫu "trông như AI làm".
- Hình chính là mockup giao diện PawBook dựng từ component design.md mục 3.5 với dữ liệu mẫu mục 9.
- Đặt tên frame: "S45 Landing page / {section hoặc toàn trang} / {trạng thái}".

Vẽ S45 Landing page giới thiệu:
1. Toàn trang desktop 1440px, light.
2. Toàn trang mobile 390px, light.
3. Bản dark cho desktop.
4. Hero ở trạng thái đầu và cuối của chuyển động (mục 4.2).
5. Form tư vấn đủ trạng thái: default, lỗi ô, đang gửi, thành công, lỗi mạng.

Trước khi vẽ, viết ngắn kế hoạch thiết kế (màu, chữ, bố cục ASCII của hero và phần tính năng), tự rà xem chỗ nào đang là mặc định chung chung thì sửa, rồi mới vẽ.
```

Vòng sửa sau: đưa từng section, ví dụ `Sửa S45 Landing page / Bảng giá: Pro nổi bật hơn, mobile Pro lên đầu.`
