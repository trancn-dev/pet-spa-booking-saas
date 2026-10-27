# Nghiệp vụ đã chốt

Tài liệu chốt chính thức về nghiệp vụ. Sơ đồ: `so-do-nghiep-vu.html` (cùng thư mục), Mermaid: `so-do-nghiep-vu.md`. Dữ liệu: `design/db-schema.md`. Giao diện: `design/design.md`. Kế hoạch: `plan/implementation-plan.md`. Bản cũ và biên bản rà soát ở `old/analysis/`.

Giai đoạn: **MVP**, **GĐ2** (kho), **GĐ3** (ca, chấm công, lương), **Sau V1** (chỉ ghi lại, chưa làm).

Không còn câu hỏi mở.

---

## 1. Shop và cơ sở
- Nhà cung cấp (admin nền tảng) tạo shop, gán gói + hạn dùng, mời chủ shop qua email. Không có tự đăng ký. Gói giới hạn số cơ sở, số nhân viên có tài khoản (tính cả lời mời còn hạn), số lịch/tháng (mọi lịch online + lễ tân, theo tháng tạo lịch; hủy không trả lại suất).
- Hạn dùng: còn 7 ngày thì nhắc; quá hạn được dùng tiếp 7 ngày ân hạn (banner đỏ); hết ân hạn thì shop **tạm khóa**: nhân viên xem được nhưng không thao tác, Site ngừng nhận lịch, không gửi nhắc khách. Gia hạn xong mở lại ngay.
- Một shop nhiều cơ sở. Dùng chung toàn shop: khách, pet, bệnh án, sổ tiêm, danh mục dịch vụ/sản phẩm, **giá bán**.
- Riêng từng cơ sở: lịch trực, lịch hẹn, Visit, hàng đợi, hóa đơn, ca thu ngân, tồn kho (lô, hạn dùng).
- Chủ shop xem mọi cơ sở; nhân viên chỉ thao tác ở cơ sở được gán; chứng từ mang mã cơ sở.
- Nhân viên luôn thuộc shop: admin tạo shop → chủ shop → cơ sở → chủ mời nhân viên (một hoặc nhiều role, cơ sở chính/phụ). Tài khoản không còn là nhân viên đang hoạt động ở shop nào thì không đăng nhập được.
- Nhân viên có cơ sở chính, chủ gán thêm cơ sở phụ. Mỗi khung trực/ca gắn một cơ sở; không xếp một người 2 khung chồng giờ, kể cả ở 2 cơ sở.
- Tra tồn cơ sở khác: chỉ xem số còn bán được + hạn gần nhất, không giá vốn; muốn bán thì chuyển kho hoặc khách qua cơ sở kia.
- Mọi cơ sở cùng múi giờ của shop.

## 2. Vai trò và quyền
- Có màn quản lý role; tạo sẵn 7 role mẫu: Chủ shop, Quản lý chi nhánh, Lễ tân, Thu ngân, Bác sĩ, Nhân viên spa, Nhân viên kho. Chủ đổi tên role, sửa quyền, tạo role mới. Danh mục quyền do hệ thống định nghĩa, shop không tạo quyền mới. **Quyền nhạy cảm** (xem giá vốn, điền giá nhập, sửa hóa đơn đã hoàn thành, thao tác mọi cơ sở, xem lương) mặc định chỉ Chủ shop; chủ giao được cho role khác, có xác nhận và ghi nhật ký. Quyền phân quyền luôn chỉ Chủ shop. Một người nhiều role thì cộng quyền. Quyền lấy từ role, không từ chức danh.
- Ma trận quyền: `design/design.md` mục 8 (theo sơ đồ 9).
- Bệnh án: bác sĩ mọi cơ sở xem đầy đủ; lễ tân, spa, quản lý chi nhánh chỉ thấy cảnh báo (dị ứng, bệnh nền, lưu ý hành vi) và ngày khám; thu ngân, kho không thấy. Chủ shop xem được nhưng không tự nhận Visit khám, không tự ghi (kiêm bác sĩ thì gán thêm role Bác sĩ).
- Báo cáo doanh thu: chủ mọi cơ sở; quản lý chi nhánh cơ sở mình (không lãi gộp); lễ tân chỉ hôm nay cơ sở mình; thu ngân báo cáo ca; bác sĩ, spa không xem.
- Khách trên Site xem: ngày khám, chẩn đoán, hướng dẫn chăm sóc, thuốc đã kê, sổ tiêm, lịch tái khám, kết quả xét nghiệm bác sĩ chọn chia sẻ, hóa đơn đã hoàn thành của mình; **không** xem ghi chú nội bộ của bác sĩ.
- Nhân viên không tài khoản (bảo vệ, lao công): có hồ sơ, không tính vào giới hạn gói, chấm công ở kiosk (GĐ3).

## 3. Khách, pet, bệnh án
- Không có thành viên/tích điểm: chỉ lưu hồ sơ khách, pet, bệnh án. Tích điểm, hạng, khuyến mãi: sau V1.
- SĐT là khóa khách, dùng chung mọi cơ sở. Khách tự tạo hồ sơ khi đặt online lần đầu (xác minh OTP). Khách chỉ mua hàng không cần hồ sơ.
- Mỗi cơ sở có một hồ sơ hệ thống "Thú hoang / vô chủ".
- Pet có cảnh báo an toàn hiện ở mọi nơi: dị ứng, bệnh nền, lưu ý hành vi ("cắn người", "sợ máy sấy").
- Bệnh án: mỗi pet một hồ sơ có **số hồ sơ** (dùng chung mọi cơ sở); mỗi lần khám thêm **một dòng chi tiết**. Chỉ **người lập** sửa dòng của mình, trong **24 giờ**; sau đó chỉ thêm ghi chú bổ sung.
- Xét nghiệm, chẩn đoán hình ảnh: là dịch vụ thêm vào Visit; kết quả ghi chữ + đính kèm ảnh/PDF; không có phòng xét nghiệm riêng.
- Spa: nhân viên spa chụp ảnh lúc nhận và lúc trả (tùy chọn), đính kèm Visit.
- Tiêm: ghi mũi (vaccine, số lô, hạn lô), hệ thống tính mũi kế tiếp theo phác đồ; không đủ điều kiện thì hoãn + ngày hẹn mới.

## 4. Dịch vụ và lịch hẹn
- Dịch vụ thuộc một trong hai nhóm: **khám** (khám, tiêm, xét nghiệm, chẩn đoán hình ảnh) hoặc **spa**. Thời lượng, giá theo loài/giống/cỡ. Dịch vụ có thể chỉ có ở một số cơ sở.
- **Combo chỉ có combo spa** (vd cắt tỉa + tắm gội), có mã, giá riêng. Khám và spa là dịch vụ riêng, gom tính tiền chung một hóa đơn.
- Lịch chỉ để tham khảo, nhắc hẹn, phân người trực; không thu cọc, không thu trước. Tạo ra là **Đã xác nhận**.
- **Một lịch nhiều pet** của cùng một khách (vd "lát mang 2 bé đến khám"); mỗi pet + dịch vụ là một dòng, chiếm một chỗ trong hạn mức.
- Trạng thái: Đã xác nhận → Đã đến / Đã hủy / Không đến. Không có "Chờ xác nhận", "Hoàn thành". Một pet không đến thì hủy riêng dòng đó.
- **Hạn mức đặt online** = số người trực của nhóm × tỉ lệ, làm tròn xuống; tỉ lệ riêng cho khám và spa, mỗi cơ sở một tỉ lệ, ca nào cần thì đặt riêng. Số người trực tự đếm từ lịch trực theo nhóm và cơ sở; người nghỉ thì giảm.
- **Một nhân viên đi làm tính là một người trực**, dù có nhiều role (khung trực ghi rõ trực nhóm nào).
- Đặt online 2 bé cùng giờ cần đủ 2 chỗ; thiếu thì báo hết chỗ cả lịch, không lưu một nửa.
- Đặt online: chưa đăng nhập thì xác minh OTP (lần đầu nhập tên tạo hồ sơ), đã đăng nhập thì dùng luôn; lịch xác nhận ngay. Lịch online không gán người cụ thể; người làm được phân khi khách đến; khách ghi được người mong muốn.
- Lịch lễ tân đặt không tính vào hạn mức online; tổng lịch vượt số người trực thì cảnh báo, vẫn lưu được. Lễ tân chọn được "Không gửi nhắc" cho từng lịch.
- Check-in: mỗi pet một Visit cho mỗi nhóm (pet khám + tiêm = 1 Visit khám; khám + spa = 2 Visit).
- Khách tự đổi/hủy lịch trên Site trước 24 giờ (chủ cài được).
- Đổi giờ, hủy đều lưu lịch sử.
- Không đến: cuối ngày hệ thống tự đánh dấu, không phạt; lễ tân đánh dấu sớm được.
- Giá trên lịch là **giá tham khảo**, ghi rõ "có thể thay đổi theo tình hình thực tế"; tính tiền theo giá lúc thêm vào Visit.

## 5. Tin nhắn cho khách
- Kênh: **Zalo ZNS trước, lỗi hoặc SĐT không dùng Zalo thì SMS**; không gửi cả hai. OTP gửi SMS. Không email/Telegram cho khách.
- Nội dung: xác nhận lịch, nhắc trước 24 giờ và 2 giờ, nhắc tiêm/tẩy giun đến hạn, nhắc tái khám, báo đón sau spa. Lịch nhiều pet gửi một tin.
- Nhân viên nhận thông báo trong CMS (chuông): cần gọi xác nhận khách, hóa đơn bị hủy, chênh lệch ca thu ngân, cảnh báo kho, phiếu chờ duyệt.

## 6. Tiếp nhận, hàng đợi, Visit
- Visit = một lượt dùng dịch vụ (khám hoặc spa) của một pet tại shop; **độc lập với hóa đơn**. Trạng thái: Đang chờ → Đang thực hiện → Hoàn tất (+ Đã hủy).
- Một pet vừa khám vừa spa = 2 Visit (2 số thứ tự).
- Số thứ tự cấp theo cơ sở, theo ngày, reset mỗi ngày. Khách có hẹn đến trong ±15 phút quanh giờ hẹn được xếp theo giờ hẹn; đến muộn hơn xếp như khách vãng lai.
- Cấp cứu: pet vào khám ngay, khách ở lại quầy làm hồ sơ; Visit cấp cứu chen đầu hàng đợi, các số đang chờ lùi một bậc, số trên phiếu giữ nguyên. Chưa gắn hồ sơ khách + pet thì không hoàn tất được Visit và hóa đơn.
- Thú hoang: dùng hồ sơ hệ thống của cơ sở; không tự mở hóa đơn; có người trả thì lập hóa đơn ghi tên, SĐT người trả.
- Nhận khách: hai người cùng bấm thì chỉ một người nhận được.
- Dịch vụ phát sinh: báo giá miệng, khách đồng ý thì thêm dòng; không lưu xác nhận chi phí.
- Nhân viên spa thấy dấu hiệu bệnh → báo lễ tân → lễ tân tạo thêm Visit khám, cùng hóa đơn.
- Vật tư trong dịch vụ: hệ thống điền sẵn theo định mức, nhân viên sửa theo số dùng thực tế.

## 7. Hóa đơn, thanh toán, trả hàng
- Không đặt cọc, không trả một phần, không công nợ; hóa đơn trả đủ một lần (có thể nhiều phương thức: tiền mặt, chuyển khoản, QR, thẻ).
- Mở hóa đơn khi tiếp nhận: mỗi khách mỗi cơ sở mỗi ngày một hóa đơn chưa hoàn thành; nhiều pet → một hóa đơn.
- Hóa đơn gom các Visit + hàng mua thêm + dòng gõ tay (tên + giá). Dòng dịch vụ của Visit chỉ đọc ở quầy (sửa ở Visit). Trạng thái: Chưa hoàn thành → Hoàn thành (thu đủ) / Đã hủy.
- In tạm tính được trước khi thu.
- Hóa đơn chưa hoàn thành từ ngày trước **không tự hủy**; đầu ngày hệ thống cảnh báo lễ tân/thu ngân/chủ để dọn (hoàn thành hoặc hủy có lý do).
- Lễ tân/thu ngân/quản lý chi nhánh hủy được hóa đơn chưa hoàn thành, bắt buộc lý do; chủ shop nhận thông báo; có mục "hóa đơn đã hủy" trong báo cáo ngày.
- Hóa đơn đã hoàn thành chỉ chủ shop sửa: bấm "Sửa" = hủy bản cũ + lập bản thay thế (số mới, tham chiếu bản cũ), tiền đã thu chuyển sang bản mới; tiền chênh thu thêm hoặc trả lại. Báo cáo không sửa ngược ngày cũ: ngày sửa ghi trừ bản cũ, cộng bản thay thế. Hóa đơn đã có phiếu trả hàng thì không sửa. Quyền sửa là quyền nhạy cảm, chủ giao được.
- **Không giảm giá tay.** Khuyến mãi theo chiến dịch và giảm theo hạng thành viên: sau V1.
- Hóa đơn chỉ mua hàng: không cần hồ sơ khách (SĐT khớp khách có sẵn thì gắn hồ sơ, không thì chỉ ghi SĐT); không bán thuốc kê đơn, không có dòng dịch vụ.
- Doanh thu = tổng hóa đơn hoàn thành trước thuế theo ngày hoàn thành; trả hàng trừ vào ngày trả. "Thực thu" (tiền nhận theo ngày) là chỉ số dòng tiền riêng.
- Ca thu ngân theo **két**: mỗi két một ca mở; tiền đầu ca, cuối ca hệ thống tính tiền mặt dự kiến, đếm két, ghi chênh lệch + lý do (báo chủ); nhiều người thu chung ca, mỗi khoản ghi tên người thu. Thu tiền mặt khi chưa mở ca thì không được.
- Trả hàng (GĐ2, lễ tân/thu ngân): chỉ trả **sản phẩm** (dịch vụ sai → chủ sửa hóa đơn). Mở hóa đơn đã mua, chọn sản phẩm + số lượng, **kiểm tay**: nguyên → cộng tồn đúng lô; lỗi → danh sách chờ chủ xử lý, chưa cộng tồn; hết hạn → hủy. Hoàn tiền mặt hoặc chuyển khoản. Hạn trả mặc định 7 ngày, chủ cài; quá hạn chỉ chủ làm.

## 8. Kho (GĐ2)
- Thuốc, vật tư trừ kho khi **hoàn tất Visit**; hàng bán trừ khi hóa đơn hoàn thành; lúc kê/thêm dòng chỉ kiểm còn đủ, không giữ hàng.
- Giá bán là một giá hiện hành cho mọi hàng còn tồn, chủ tự chỉnh, không theo lô hay giá vốn. Giá vốn mặc định chỉ chủ xem (chủ giao được quyền này cho role khác); nhân viên kho mặc định không thấy giá.
- Xuất kho **FIFO theo lô nhập**: lô nhập trước bán hết mới lấy lô sau; lô hết hạn bỏ qua; lô sau hạn gần hơn chỉ cảnh báo. Giá vốn trong báo cáo lợi nhuận là giá của lô thực xuất.
- Nhập hàng 2 bước: nhân viên kho tại cơ sở nhận lập phiếu (sản phẩm, số lượng, lô, hạn; không thấy giá), lưu là **cộng tồn ngay**; chủ mở phiếu điền giá nhập. Lô chưa có giá thì báo cáo lợi nhuận đánh dấu "thiếu giá nhập".
- Nhân viên kho: sắp xếp, lấy hàng, kiểm kê, báo hao hụt (chuột cắn, vỡ…) có lý do; hao hụt và chênh lệch kiểm kê chờ chủ hoặc quản lý chi nhánh duyệt mới trừ tồn.
- Hàng lỗi: chủ chọn hủy, trả nhà cung cấp, hoặc tạo mã sản phẩm riêng để bán giảm giá.
- Chuyển kho giữa cơ sở: yêu cầu → duyệt → giao → nhận (có "nhận thiếu", "trả về"); chuyển cho khách thì cơ sở nhận giữ hàng 48 giờ. Lô chuyển giữ số lô, hạn, giá vốn.
- Cảnh báo sắp hết, hết hàng, cận date, quá hạn qua thông báo trong CMS.
- Sản phẩm trưng bày trên Site (chỉ "Còn hàng"/"Hết hàng"), không bán online.

## 9. Nhân sự (GĐ3)
- Nhân viên đăng ký ca; chủ/quản lý chi nhánh nhận hoặc từ chối, xếp ca. Đổi ca: chủ sửa tay.
- Ngày có xếp ca thì dùng ca được xếp để đếm người trực cho hạn mức đặt online.
- Chấm công trên CMS; tự lấy cơ sở của ca đang trực (kiểm IP nếu cơ sở có cài); không có ca thì nhân viên chọn cơ sở, ghi "ngoài lịch", chủ duyệt mới tính. Người không tài khoản chấm ở kiosk bằng mã + PIN.
- Lương = đơn giá ca × số ca chấm công hợp lệ, theo tuần hoặc tháng; là số dự kiến để chủ kiểm tra rồi chốt kỳ. Trang lương mở khóa 10 phút, ghi log; chủ ủy quyền xem được. Lương tháng, phụ cấp, thưởng/phạt: sau V1.

## 10. Ngoài phạm vi (sau V1)
Lưu chuồng; thành viên, tích điểm, hạng; khuyến mãi theo chiến dịch; phân công bác sĩ theo chuyên môn; app native; chat với khách; bán hàng online / giỏ hàng; thu phí gói online; domain riêng cho shop; đa ngôn ngữ; lương tháng, phụ cấp, thưởng/phạt; chứng từ trả nhà cung cấp; tài nguyên phòng/bàn grooming.
