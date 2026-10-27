# Nghiệp vụ đã chốt (20/10/2026) và danh sách sửa tài liệu

Nguồn: đặc tả nghiệp vụ TranCN gửi 20/10/2026 + câu trả lời nhóm 1–14 trong thread "Bộ sơ đồ nghiệp vụ". Sơ đồ đầy đủ: `so-do-nghiep-vu.html` (cùng thư mục), Mermaid: `so-do-nghiep-vu.md`.

Trạng thái: **chưa sửa** db-schema.md, design.md, implementation-plan.md (vẫn là v5). Phần B là danh sách sửa đề xuất, nhãn **v6**, để thread "Rà mâu thuẫn" (đang giữ 3 file đó) áp dụng cùng danh sách 50 mục.

---

## A. Nghiệp vụ đã chốt

### A1. Shop và cơ sở
- Một shop nhiều cơ sở. Dùng chung toàn shop: khách, Pet, bệnh án, sổ tiêm, danh mục dịch vụ/sản phẩm, **giá bán** (12.1, 12.3).
- Riêng từng cơ sở: ca trực, lịch hẹn, Visit, hàng đợi, hóa đơn, ca thu ngân, tồn kho (lô, hạn dùng) (12.1).
- Chủ shop xem mọi cơ sở; nhân viên chỉ thao tác ở cơ sở được gán; chứng từ mang mã cơ sở.
- Nhân viên có cơ sở chính, chủ gán thêm cơ sở phụ (12.2). Mỗi ca trực gắn một cơ sở; không xếp một người 2 ca chồng giờ ở 2 cơ sở (12.4).
- Tra tồn cơ sở khác: chỉ xem số còn bán được + hạn gần nhất, không giá vốn; muốn bán thì chuyển kho hoặc khách qua cơ sở kia.

- Mọi cơ sở cùng múi giờ (mục 49).

### A2. Vai trò và quyền
- Có màn quản lý role; tạo sẵn 7 role mẫu: Chủ shop, Quản lý chi nhánh, Lễ tân, Thu ngân, Bác sĩ, Nhân viên spa, Nhân viên kho. Một người nhiều role (2.1).
- Ma trận quyền ở sơ đồ 9 được duyệt nguyên (14.1).
- Bệnh án: bác sĩ mọi cơ sở xem đầy đủ; lễ tân, spa chỉ thấy cảnh báo (dị ứng, bệnh nền) và ngày khám; thu ngân, kho không thấy (2.2).
- Khách trên Site xem: ngày khám, chẩn đoán, hướng dẫn chăm sóc, thuốc đã kê, sổ tiêm, lịch tái khám; **không** xem ghi chú nội bộ của bác sĩ (14.2).

### A3. Khách, Pet, bệnh án
- V1 không có membership: chỉ lưu hồ sơ chủ, Pet, bệnh án. Tích điểm, lên hạng, khuyến mãi để sau (4.1, 10.1).
- Bệnh án: mỗi Pet một hồ sơ có **số hồ sơ**; mỗi lần khám thêm **một dòng chi tiết**. Sửa chỉ trong dòng đó, **người lập** sửa, trong **24 giờ** (8.1).
- Xét nghiệm, chẩn đoán hình ảnh: là dịch vụ thêm vào Visit; kết quả ghi chữ + đính kèm ảnh/PDF; không có phòng xét nghiệm riêng (8.2).
- Spa: groomer chụp ảnh lúc nhận và lúc trả (tùy chọn), đính kèm Visit (9.1).

### A4. Lịch hẹn
- Lịch chỉ để tham khảo, nhắc hẹn, phân bác sĩ trực; không thu cọc, không thu trước (1.1, 3.2).
- Một lịch có thể gồm nhiều pet của cùng một khách (vd "lát mang 2 bé đến khám"); mỗi pet + dịch vụ chiếm một chỗ trong hạn mức.
- Combo chỉ có combo spa (cắt tỉa + tắm gội); khám và spa là dịch vụ riêng, gom tính tiền chung một hóa đơn.
- Một nhân viên đi làm tính là một người trực, dù có nhiều role.
- Trạng thái: Đã xác nhận → Đã đến / Đã hủy / Không đến. Không có "Chờ xác nhận", "Hoàn thành", "In-service", "Rescheduled" (3.2, 6.2, 6.4).
- Đặt online: chưa đăng nhập thì xác minh OTP (lần đầu tạo hồ sơ), đã đăng nhập thì dùng luôn thông tin; lịch xác nhận ngay (6.4).
- Khách tự đổi/hủy lịch trên Site trước 24 giờ, chủ cài được (mục 10).
- Nhắc hẹn, OTP, báo khách qua **SMS và Zalo** (mục 11).
- Khách có hẹn đến trong ±15 phút được ưu tiên; đến muộn hơn xếp như khách vãng lai (mục 20).
- **Hạn mức đặt online** = số người trực của nhóm × tỉ lệ, làm tròn xuống; tỉ lệ riêng cho khám và spa, mỗi cơ sở một tỉ lệ, ca nào cần thì đặt riêng (6.1, 7.4). Số người trực tự đếm từ lịch làm việc theo role và cơ sở; người nghỉ thì giảm (7.1).
- Lịch online không gán bác sĩ cụ thể; người làm được phân khi khách đến; khách ghi chú được người mong muốn (7.2).
- Lịch lễ tân đặt không tính vào tỉ lệ online; tổng lịch vượt số người trực thì cảnh báo, vẫn lưu được (7.3).
- Không đến: cuối ngày hệ thống tự đánh dấu, không phạt; lễ tân đánh dấu sớm được (6.3).
- Giá trên lịch là **giá tham khảo**, ghi rõ "có thể thay đổi theo tình hình thực tế"; tính tiền theo giá lúc thêm vào Visit (10.4).

### A5. Tiếp nhận, Visit
- Visit = một lượt dùng dịch vụ (khám, spa, tiêm) của một Pet tại shop; **độc lập với hóa đơn** (1.2). Trạng thái: Đang chờ → Đang thực hiện → Hoàn tất (+ Đã hủy). Bỏ `invoiced`.
- Cấp cứu: Pet vào khám ngay, khách ở lại quầy làm hồ sơ; Visit cấp cứu chen đầu hàng đợi, các số đang chờ lùi một bậc (3.1).
- Dịch vụ phát sinh: báo giá miệng, khách đồng ý thì thêm dòng; không ghi xác nhận chi phí (3.3).

### A6. Hóa đơn, thanh toán, trả hàng
- Không đặt cọc, không trả một phần, không công nợ; hóa đơn trả đủ một lần (1.1).
- Hóa đơn gom các Visit + hàng mua thêm + dòng gõ tay (tên + giá) (1.2, 3.3). Trạng thái: Chưa hoàn thành → Hoàn thành (thu đủ) / Đã hủy (1.3).
- Hóa đơn chưa hoàn thành từ ngày trước không tự hủy; hệ thống cảnh báo đầu ngày để lễ tân/thu ngân dọn (hoàn thành hoặc hủy có lý do).
- Lễ tân/thu ngân hủy được hóa đơn chưa hoàn thành, bắt buộc lý do; chủ shop nhận thông báo, có mục "hóa đơn đã hủy" trong báo cáo ngày (1.3, 5.2).
- Hóa đơn đã hoàn thành chỉ chủ shop sửa: bấm "Sửa" = hủy bản cũ + lập bản thay thế (số mới, tham chiếu bản cũ); tiền chênh thu thêm hoặc trả lại (5.1).
- **Không giảm giá tay.** Khuyến mãi theo chiến dịch (danh sách sản phẩm, giá khuyến mãi từng món hoặc % cả danh sách) và giảm theo hạng thành viên: chỉ ghi lại, chưa làm (10.1, 11.1).
- Trả hàng (lễ tân): mở hóa đơn đã mua, chọn sản phẩm + số lượng, **kiểm tay**: nguyên → cộng tồn ngay; lỗi → danh sách chờ xử lý, chưa cộng tồn, chủ shop quyết; hết hạn → báo hủy. Hoàn tiền mặt hoặc chuyển khoản, trừ vào doanh thu ngày trả. Hạn trả mặc định 7 ngày, chủ cài; quá hạn chỉ chủ làm (10.2, 11.2, 11.3).
- Ca thu ngân theo **két**: tiền đầu ca, cuối ca hệ thống tính tiền mặt dự kiến, đếm két, ghi chênh lệch + lý do; nhiều người thu chung ca, mỗi khoản ghi tên người thu (10.3, 11.4).

### A7. Kho (giai đoạn 2)
- Thuốc, vật tư trừ kho khi **hoàn tất Visit**; hàng bán trừ khi hóa đơn hoàn thành; lúc kê chỉ kiểm còn đủ, không giữ hàng (13.1).
- Giá vốn nhập theo phiếu nhập; phiếu sau có thể khác giá; chủ tự quyết có đổi giá bán hay không (13.2).
- Giá bán là một giá hiện hành cho mọi hàng còn tồn, chủ tự chỉnh, không theo lô hay giá vốn. Giá vốn chỉ để chủ xem lợi nhuận, khách không thấy (15.1).
- Xuất kho: lô nhập trước bán hết mới lấy lô sau, dù cùng giá bán (15.1 bổ sung; khác v5 vốn xuất lô hạn gần trước). Giá vốn trong báo cáo lợi nhuận là giá của lô đang xuất.
- Nhập hàng: chủ làm việc với nhà cung cấp. Nhân viên kho tại cơ sở nhận hàng lập phiếu nhập: sản phẩm, số lượng, lô, hạn; không thấy giá. Chủ mở phiếu trên hệ thống của chủ, điền giá nhập để quản lý chi tiêu, lợi nhuận (15.2, 16.1).
- Nhân viên kho: sắp xếp, lấy hàng, kiểm kê, báo hao hụt (chuột cắn, vỡ…) có lý do, chờ duyệt (16.1).
- Hàng lỗi: chủ xử lý; có thể tạo mã sản phẩm riêng để bán giảm giá (13.3).

### A8. Nhân sự (giai đoạn 3)
- Chấm công tự lấy cơ sở của ca đang trực (kiểm IP nếu cơ sở có cài); không có ca thì nhân viên chọn cơ sở, ghi "ngoài lịch", chủ duyệt mới tính (12.4).

---

## B. Còn mở

Không còn câu hỏi mở. Mục 10, 11, 20, 49 đã chốt (xem A4, A1).

Giả định đang dùng trong sơ đồ (bạn chưa phản đối): tồn cộng ngay khi kho lưu phiếu nhập, giá nhập để trống đến khi chủ điền (báo cáo lợi nhuận đánh dấu món thiếu giá); lô hết hạn bỏ qua khi xuất, lô sau hạn gần hơn chỉ cảnh báo; khách chỉ mua hàng không cần hồ sơ; chỉ trả được sản phẩm (dịch vụ lỗi → chủ sửa hóa đơn); khi chủ sửa hóa đơn, tiền đã thu chuyển sang bản thay thế, doanh thu bản thay thế tính ngày sửa; số trên phiếu hàng đợi giữ nguyên khi cấp cứu chen; groomer thấy bệnh → lễ tân tạo thêm Visit khám; chuyển kho thêm trạng thái "Nhận thiếu", "Trả về".

---

## C. Danh sách sửa tài liệu (v6, đề xuất)

DB = design/db-schema.md, DS = design/design.md, PL = plan/implementation-plan.md. Số mục 50 = old/analysis/mau-thuan-tai-lieu.md.

### C1. Vai trò, quyền
- DB 1.1/1.2, PL 4.2, DS mục 2: role mẫu thành 7 (`owner, branch_manager, receptionist, cashier, vet, groomer, inventory`); `staff.position` chỉ để hiển thị/nhân viên không tài khoản. Giải quyết mục 1 (A), 2 (A), 3 (**B**).
- Permission bệnh án tách: xem đầy đủ (vet) / chỉ cảnh báo (receptionist, groomer). Mục 4 → **B**. Sửa prototype S3 theo đó.
- DS: thêm ma trận quyền (copy sơ đồ 9).

### C2. Lịch hẹn và đặt online
- DB `appointments`: `status ENUM(confirmed, checked_in, cancelled, no_show)` (bỏ `pending`, `completed`); `staff_id` → NULL được (lịch online không gán người), thêm `preferred_staff_id NULL`; `price` chỉ là giá tham khảo; thêm `service_group` (medical/grooming, tiêm tính medical). Mục 14 → bỏ "Hoàn thành". Mục 19 → giá hiện tại (khác A).
- DB mới: tỉ lệ đặt online theo cơ sở × nhóm (+ ghi đè theo ca); đếm người trực từ `staff_working_hours` (đã có `branch_id`) theo role. Ràng buộc: một người không có 2 khung chồng giờ ở 2 cơ sở.
- DB 4.11, 5.1 + ADR double-booking: khóa đổi từ hàng `staff` sang một hàng đếm theo (cơ sở, nhóm, ngày) rồi đếm lịch online chồng giờ < floor(người trực × tỉ lệ). Vẫn là test concurrency điểm nhấn CV.
- Job cuối ngày đánh dấu `no_show`.
- PL 5.4, 5.5, 5.6, 7.1; DS S1–S3, S6, F1, F2: theo các điểm trên. Mục 7 → A (OTP; đã đăng nhập dùng luôn), 8 → A. Mục 10 → 24 giờ, chủ cài. Mục 11 → kênh khách là SMS + Zalo (OTP, nhắc hẹn, báo đón); khác đề xuất email/Telegram. Mục 20, 49 → giữ.

### C3. Visit, cấp cứu
- DB `visits.status`: bỏ `invoiced` (Visit độc lập hóa đơn). Thêm cờ cấp cứu / thứ tự ưu tiên; `queue_no` giữ nguyên.
- DB 4.5, 4.6 (`CompleteInvoice` không đổi visits sang `invoiced`); PL 5.8, 5.9; DS S15–S17.

### C4. Bệnh án, xét nghiệm, ảnh
- DB: hồ sơ bệnh án có số theo Pet; `medical_records` thành dòng chi tiết theo Visit (bỏ `appointment_id`, mục 21 A), `created_by` + chỉ người lập sửa trong 24 giờ, sau đó ghi chú bổ sung; tách trường ghi chú nội bộ (không hiện Site). Mục 5 → theo 8.1.
- DB: đính kèm file (kết quả xét nghiệm trên dòng bệnh án; ảnh trước/sau trên Visit spa).
- PL 6.2, 5.9; DS S4, S17, S21.

### C5. Hóa đơn, thu tiền, trả hàng, ca thu ngân
- DB `invoices`: trạng thái draft/completed/void giữ như v5; lễ tân void bản draft (lý do + thông báo chủ); chủ "sửa" completed = void + `replaces_invoice_id`, **chuyển payments sang bản thay thế**, chênh lệch thu thêm/`refund`. Mục 27 → theo 1.3/5.1.
- DB `invoice_lines`: thêm loại dòng gõ tay (tên + giá, không trừ kho). Mục 30 → A (đặt ở hóa đơn).
- Bỏ giảm giá tay: mục 28 → không có ở V1; khuyến mãi chiến dịch + giảm theo hạng ghi "sau V1".
- Mục 26 → A (không cọc, không nợ, không trả một phần).
- DB mới (MVP): két theo cơ sở + phiên ca thu ngân (tiền đầu ca, tiền dự kiến, tiền đếm, chênh lệch, lý do); `payments.cash_session_id`. PL thêm task sau 5.10; DS thêm màn mở/đóng ca.
- DB `sales_returns`: hạn trả `return_window_days` (cài đặt, mặc định 7, quá hạn chỉ chủ); `sales_return_lines.condition ENUM(intact, defective, expired)` thay `restock BOOL`: intact → movement `return`; defective → danh sách chờ xử lý (không vào `on_hand`); expired → `write_off`. PL 11.5; DS S31.

### C6. Kho
- DB 4.7, PL 11.2: thuốc + vật tư trừ khi hoàn tất Visit (mục 37 → A).
- DB 4.7, PL 11.1 (mục 36): giá bán không phụ thuộc giá vốn; giá vốn theo lô, chỉ chủ xem. Thứ tự xuất lô: FIFO theo ngày nhập thay FEFO của v4/v5. Phiếu nhập 2 bước: kho lập (số lượng, lô, hạn) → chủ điền `unit_cost`; quyền xem giá vốn tách riêng, chỉ owner. Thêm loại phiếu hao hụt (kho lập, cần duyệt). Sửa ma trận DS theo sơ đồ 9 bản mới.
- Hàng lỗi: chủ hủy / trả nhà cung cấp / chuyển sang mã sản phẩm riêng để bán giá thấp (13.3).
- Chuyển kho: thêm trạng thái nhận thiếu, trả về (đề xuất).

### C7. Thành viên, khuyến mãi
- DB `owners.is_member`, `loyalty_transactions`, DS S34, PL 11.5: chuyển "sau V1" cùng khuyến mãi theo chiến dịch (mục 13 → B).

### C8. Nhân sự (GĐ3)
- DB `attendance_records`: lấy `branch_id` từ ca đang trực; thêm trạng thái chờ duyệt cho chấm công ngoài lịch. PL 12.2; DS S27.
