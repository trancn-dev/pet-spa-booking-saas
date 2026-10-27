# Mâu thuẫn và bất cập trong tài liệu (rà soát 20/10/2026, bản v5)

Nguồn đã đọc: `project-brief.md`, `project-instructions.md`, `design/db-schema.md`, `design/design.md`, `plan/implementation-plan.md`, `design/system-architecture.html`, `analysis/doi-chieu-nghiep-vu-chatgpt.md`, file nghiệp vụ gốc (ChatGPT) và 2 ảnh prototype (S3, S6) trong uploads.

**Cách trả lời:** ghi theo STT, vd `1: A, 2: ok, 3: B (ghi chú…)`. `ok` = đồng ý phương án có ⭐. Mỗi phần chốt xong thì Claude sửa db-schema / design / plan (nhãn **v6**).

Viết tắt: **DB** = db-schema.md, **DS** = design.md, **PL** = implementation-plan.md, **PT** = ảnh prototype.

## Trạng thái sau v6 (20/10/2026)

Đã áp dụng vào db-schema / design / plan (nhãn **v6**) theo `so-do-nghiep-vu/nghiep-vu-da-chot.md`. Bản v5 lưu ở `archive/v5/`.

- **Đã chốt:** 1 (A), 2 (A), 3 (**B**: thêm role Quản lý chi nhánh, tổng 7 role mẫu), 4 (**B**: lễ tân, spa chỉ thấy cảnh báo + ngày khám), 5 (người lập sửa trong 24h), 7 (A), 8 (A), 13 (**B**: tích điểm sau V1), 14 (bỏ "Hoàn thành"), 15 (A: 2 lượt), 19 (**giá hiện tại lúc thêm vào lượt**, lịch chỉ ghi giá tham khảo), 21 (A), 22 (đã sửa prompt 3.1), 26 (A), 27 (Owner "Sửa" = hủy + bản thay thế; lễ tân/thu ngân hủy nháp có lý do), 28 (không giảm giá tay ở V1), 30 (A, dòng gõ tay trên hóa đơn), 35 (giá chung toàn shop), 36 (**xuất FIFO theo lô nhập**, thay FEFO; giá vốn = giá lô xuất, chỉ chủ xem; giá bán độc lập; phiếu nhập 2 bước: kho lập không thấy giá, cộng tồn ngay → chủ điền giá; phiếu hao hụt chờ duyệt), 37 (A).
- **Chốt thêm 20/10 tối:** 10 (khách tự hủy/dời trước 24h), **11 (nhắc khách qua Zalo ZNS + SMS; bỏ email, Telegram; Zalo trước, không gửi được mới SMS; OTP qua SMS)**, 20 (ưu tiên khách có hẹn ±15 phút), 49 (mọi cơ sở cùng múi giờ).
- **Áp dụng theo gợi ý, chờ bạn phản đối nếu khác:** 9 (chọn cơ sở ở bước 1), 39 (trả NCC ngoài V1), chuyển kho thêm "nhận thiếu", "trả về".
- **Còn mở:** 6, 12, 16, 17, 18, 23, 24, 25, 29, 31, 32, 33, 34, 38, 40–48, 50; (16.1 đã chốt: kho lập phiếu nhập, không thấy giá). Ngoài ra mục 18 đã ghi tạm "form cho thêm nhiều dòng, mỗi dòng một lịch" ở S2.

---

## A. Vai trò và phân quyền

**1. Tách quyền bác sĩ (vet) và groomer** *(bạn đã xác nhận 19/10, tài liệu chưa sửa)*
- Hiện: role spatie có sẵn owner/vet/groomer/receptionist (DB 1.1, PL 4.2) nhưng song song có `staff.position` (DB 1.2). Chưa nói cái nào quyết định quyền.
- A ⭐: **role quyết định quyền** (permission gán theo role); `position` chỉ dùng cho phủ ca, hiển thị, nhân viên không tài khoản. Ghi rõ ma trận quyền vet vs groomer (groomer: ghi dịch vụ spa, vật tư; không xem chẩn đoán).
- B: bỏ `position`, dùng role cho cả nhân viên không tài khoản.

**2. Một người nhiều vai trò (vd Owner kiêm bác sĩ)**
- DS 8 và PL 4.2 có "Owner + Bác sĩ", nhưng `invitations.role` một giá trị, `staff.position` một giá trị.
- A ⭐: cho nhiều role/staff (spatie hỗ trợ sẵn); lời mời mang 1 role chính, Owner thêm role sau ở S11.
- B: mỗi người một role.

**3. "Quản lý chi nhánh" được nhắc nhưng không có role**
- DB 1.4 (`write_off`), 4.7 (nhận cảnh báo kho), DS S22, PL 11.1 nói "Owner/quản lý chi nhánh"; danh sách role không có.
- A ⭐: không thêm role; dùng permission (`inventory.write_off`, `inventory.alerts`) để Owner gán cho ai cũng được.
- B: thêm role `branch_manager`.

**4. Lễ tân có được xem hồ sơ y tế không**
- Brief, DS mục 2, S4, F4: lễ tân **chỉ xem**. PT màn S3 (vai Lễ tân): "Bạn không có quyền xem hồ sơ y tế".
- A ⭐: theo tài liệu, lễ tân xem được (read-only); sửa prototype.
- B: lễ tân không xem chẩn đoán, chỉ thấy "đã khám ngày…".

**5. Quy tắc sửa bệnh án** (PL 6.2 để "tùy chọn")
- A ⭐: bác sĩ chỉ sửa bản ghi của chính mình trong 24h; sau đó chỉ thêm ghi chú bổ sung; ghi `activity_logs`.
- B: vet sửa mọi lúc.

**6. Gói giới hạn số nhân viên đếm ai** (PL 4.4 *[Mặc định]*)
- A ⭐: chỉ đếm nhân viên **có tài khoản** đang active (kể cả Owner); bảo vệ/lao công không tính.
- B: đếm mọi nhân viên.

---

## B. Khách hàng và Site

**7. Khách tự đăng ký trên Site** *(bạn đã xác nhận 19/10, tài liệu chưa sửa)*
- Hiện chỉ có đăng nhập OTP (DB `customer_otps`, DS S21); đặt lịch online tạo hồ sơ theo SĐT **không xác minh** (PL 7.1), người khác gõ SĐT của bạn là tạo/ghi vào hồ sơ đó.
- A ⭐: OTP lần đầu = tự đăng ký (nhập tên → tạo `owners`); lễ tân tạo tại quầy như cũ. Đặt lịch online bắt buộc qua OTP.
- B: cho đặt không cần OTP, lịch ở trạng thái `pending` chờ lễ tân gọi xác nhận.

**8. Luồng đặt lịch online S6: 4 bước hay 5 bước**
- DS S6: 4 bước, nhập SĐT tự do rồi gợi ý tên pet đã có (lộ tên pet cho người lạ). DS S21 nói dùng OTP. PT có 5 bước "Dịch vụ → Xác minh → Thú cưng → Giờ → Xác nhận". PL 7.1 vẫn theo bản 4 bước.
- A ⭐: 5 bước như prototype (đi cùng 7A), bỏ gợi ý pet theo SĐT chưa xác minh.

**9. Chọn chi nhánh khi đặt online**
- PL 7.1: "Portal chọn chi nhánh trước khi đặt lịch"; DS S6 không có bước chọn chi nhánh.
- A ⭐: chọn chi nhánh ở bước 1 (tự ẩn khi shop chỉ có 1 chi nhánh).

**10. Hạn khách tự hủy / dời lịch**
- DB: `reschedule_cutoff_hours` mặc định 24, `cancel_cutoff_hours` không có mặc định. PL 7.1 ví dụ "không hủy trong 2h"; PT "Quá hạn hủy/đổi 2h"; DS S21 "24 giờ".
- A ⭐: 2 cột riêng, mặc định dời 24h, hủy 24h; Owner sửa ở Cài đặt. Sửa ví dụ 2h trong plan/prototype.
- B: một ngưỡng chung.

**11. Kênh nhắc hẹn và OTP**
- DS S2, S6: Email/Telegram/Zalo. DS S20, S12: SMS/Zalo. PL 6.1: làm thật Mail + Telegram, SMS và Zalo chỉ có interface. Kiến trúc: Email/SMS. OTP cần SMS thật (tốn tiền).
- A ⭐: MVP = Email + Telegram + in-app; SMS chỉ dùng cho OTP (driver log + mã cố định ở môi trường demo); Zalo ZNS sau MVP. UI chỉ hiện kênh đang có.
- B: OTP qua Zalo/email thay SMS.

**12. Phiên đăng nhập khách giữ 30 ngày** (DS 10.5 *[Mặc định]*): ok?

**13. Thành viên / tích điểm** *[Cần nghiên cứu]* (DB `loyalty_transactions`, DS S34, GĐ2)
- A ⭐: giữ GĐ2; chính sách mặc định: 1 điểm / 10.000 ₫ trước thuế, 1 điểm = 100 ₫ khi đổi, không hết hạn; trả hàng trừ điểm. Owner sửa tỷ lệ.
- B: để ngoài V1 hẳn.

---

## C. Lịch hẹn, tiếp nhận, lượt khám

**14. Ai đánh dấu lịch hẹn "Hoàn thành"**
- DS S3 + F3 + PT: nút "Hoàn thành" trên lịch hẹn, groomer bấm trên điện thoại. PL 5.6, 5.9: lịch hẹn tự `completed` khi lượt khám `done`.
- A ⭐: bỏ nút Hoàn thành ở S3; chỉ "Hoàn tất lượt" ở S17 (groomer mở S17 trên điện thoại).

**15. Một pet vừa khám vừa spa trong cùng lần đến**
- `visits.kind` chỉ medical **hoặc** grooming; S15 chọn một loại.
- A ⭐: tạo 2 lượt cho cùng pet (2 số thứ tự, chung hóa đơn nháp). Ghi rõ vào DB 4.5 / S15.
- B: thêm kind `mixed`.

**16. Combo trộn dịch vụ khám và spa**: A ⭐: không cho (kiểm ở Action: dịch vụ con cùng `category`). B: cho, lượt lấy kind theo dịch vụ con đầu tiên.

**17. Thú hoang dồn chung một hóa đơn**
- Mọi thú hoang của chi nhánh dùng chung một hồ sơ hệ thống; PL 5.8 gắn lượt vào "nháp đang mở của khách hôm nay" → mọi thú hoang trong ngày vào **một** hóa đơn. Chưa nói ai trả.
- A ⭐: lượt thú hoang luôn mở hóa đơn nháp **riêng**; hóa đơn có ô "Người thanh toán" (tên, SĐT) tùy chọn; tiệm miễn phí thì giảm 100% có lý do.
- B: bắt người đưa đến tạo hồ sơ khách, pet gắn vào họ.

**18. Một lịch hẹn = 1 pet + 1 dịch vụ** (DB `appointments.service_id`)
- Khách đặt tắm cho 2 pet hoặc tắm + khám chưa có cách.
- A ⭐: giữ 1 lịch = 1 pet + 1 dịch vụ/combo; form đặt cho phép thêm nhiều dòng, submit tạo nhiều lịch (mỗi lịch qua cơ chế khóa riêng, lỗi một dòng thì báo dòng đó).
- B: thêm `appointment_items` (phức tạp, đổi cơ chế khóa).

**19. Giá áp dụng khi khách đến**
- Lịch hẹn snapshot giá lúc đặt (DB); lượt khám snapshot giá lúc ghi (PL 5.9). Giá đổi giữa hai thời điểm thì hóa đơn lệch giá khách đã thấy.
- A ⭐: dịch vụ đã đặt lấy giá của lịch hẹn; dịch vụ phát sinh thêm lấy giá hiện tại.

**20. Ưu tiên khách có hẹn trong ±15 phút** (DB 4.5 *[Mặc định]*): ok?

**21. `medical_records.appointment_id` còn sót** (DB, PL 6.2 "gắn appointment nếu mở từ lịch", F4 cũ) trong khi v2 ghi khám đi qua lượt khám.
- A ⭐: bỏ `appointment_id`, chỉ `visit_id` (NULL khi ghi bệnh sử ở nơi khác).

---

## D. Hóa đơn và thanh toán

**22. Plan task 3.1 còn theo hóa đơn cũ**: `invoices.active_visit_id` + UNIQUE, CHECK `visit_id`, test "2 hóa đơn cho cùng visit", trái v5 (DB đã bỏ). A ⭐: sửa prompt 3.1 theo v5.

**23. Chống mở 2 hóa đơn nháp cho cùng khách**
- PL 5.8 lock hàng owner; DB không có ràng buộc. 
- A ⭐: thêm cột sinh `open_draft_key` (owner + chi nhánh + ngày, NULL khi hết draft) + UNIQUE, giống `stock_alerts.open_key`; giữ lock owner.

**24. Hóa đơn nháp treo qua ngày** (khách bỏ về, chưa thu): chưa có quy tắc.
- A ⭐: không tự hủy; S18 có mục "Nháp từ ngày trước"; lượt mới hôm sau mở nháp mới; Owner/lễ tân hủy nháp có lý do.

**25. Lễ tân sửa/xóa dòng dịch vụ do bác sĩ ghi**
- DS S18: lễ tân xóa/sửa dòng khi khách đổi ý. DB 4.5: `invoice_lines` đồng bộ một chiều từ `visit_items`. Hai nơi sửa sẽ lệch nhau.
- A ⭐: dòng có `visit_item_id` ở thu ngân chỉ giảm giá được; bỏ dịch vụ thì sửa ở lượt khám (S17). Dòng "Mua thêm" lễ tân sửa tự do.
- B: lễ tân xóa ở thu ngân → xóa luôn `visit_item`, ghi log.

**26. Khách nợ / trả sau / đặt cọc** *[Cần nghiên cứu]*: `completed` = đã trả đủ, không có công nợ.
- A ⭐: V1 không công nợ, không đặt cọc; giữ `paid_amount` để mở sau.

**27. Hủy / hoàn tiền sau `completed`** *[Cần nghiên cứu]* (tạm chỉ Owner)
- A ⭐: chốt: permission `invoices.void` / `invoices.refund` (mặc định Owner, gán được), không phê duyệt 2 bước ở V1, lý do bắt buộc, `activity_logs`.
- B: lễ tân tạo yêu cầu, Owner duyệt.

**28. Giới hạn giảm giá theo role** (PL 5.10, chưa có số): A ⭐: lễ tân ≤ 10%/dòng hoặc đơn, Owner không giới hạn; Owner cấu hình.

**29. Hai định nghĩa doanh thu**
- PL 9.1 / S5: "doanh thu thực thu (từ payments)" (gồm thuế, theo ngày thu). DB 4.7 / S30: doanh thu **trước thuế** theo hóa đơn.
- A ⭐: báo cáo doanh thu = hóa đơn `completed` trước thuế theo `completed_at`; "Thực thu" là KPI dòng tiền riêng ở S5, ghi nhãn rõ.

---

## E. Dịch vụ, combo, thuốc, vật tư

**30. MVP phòng khám chưa kê được thuốc**: `visit_items` MVP chỉ có `service` (thuốc cần `products`, GĐ2) → hóa đơn khám MVP thiếu tiền thuốc.
- A ⭐: MVP thêm item_type `custom` (tên + giá nhập tay, không trừ kho); GĐ2 dùng sản phẩm thật.
- B: chấp nhận, MVP thu thuốc bằng dịch vụ "Thuốc" giá nhập tay.

**31. Vaccine (MVP) không nối với kho (GĐ2)**: `vaccines` và `products` tách rời → tiêm không trừ kho, số lô gõ tay.
- A ⭐: GĐ2 thêm `vaccines.product_id NULL`; ghi mũi tiêm thì trừ kho FEFO và tự điền số lô.

**32. `products.kind` một giá trị nhưng hàng dùng hai mục đích** (DB 4.7: thuốc vừa bán vừa dùng; dầu gội vừa bán lẻ vừa làm vật tư).
- A ⭐: `kind` chỉ để phân nhóm/báo cáo; bán lẻ được hay không theo `requires_prescription` + cờ mới `is_sellable`; dùng trong lượt không giới hạn theo kind.

**33. Kết hợp định mức với thực tế** *[Cần nghiên cứu]* (DB `service_consumables`)
- A ⭐: chốt cơ chế hiện có (điền sẵn theo cỡ → nhân viên sửa → trừ theo thực tế) là đủ cho V1; báo cáo chênh lệch định mức/thực tế để sau.

**34. Vật tư thay thế trong combo** *[Cần nghiên cứu]*: A ⭐: như dịch vụ thường: điền theo từng dịch vụ con, đổi từng dòng.

**35. Giá dịch vụ chung cả chuỗi, chưa có giá riêng chi nhánh** (DB 4.4 *[Mặc định]*): ok?

---

## F. Kho và giá vốn (GĐ2)

**36. Phương pháp giá vốn** *[Cần nghiên cứu]*, phải chốt trước task 11.1
- Ghi chú: DB 4.7 bước 4 điều chuyển đã viết theo bình quân ("avg_cost CN1 cập nhật").
- A ⭐: **bình quân gia quyền theo chi nhánh**; điều chuyển mang giá vốn bình quân của chi nhánh gửi; kiểm kê chênh lệch tính theo giá bình quân hiện tại; trả hàng theo `unit_cost` của movement gốc. Lý do: một đường tính, điều chuyển/kiểm kê đơn giản, đủ cho tiệm.
- B: FIFO/FEFO theo lô (lãi gộp sát lô thật, nhưng phức tạp khi điều chuyển/kiểm kê).

**37. Thời điểm trừ kho chưa thống nhất**
- Thuốc kê: trừ **ngay khi bác sĩ lưu**, hủy dòng trả kho (DB 4.7, PL 11.2). Vật tư: trừ **khi hoàn tất lượt** (DB 4.7 v5). Sản phẩm "mua thêm" trên hóa đơn lượt khám: chưa nói; PL 11.2 chỉ nói hóa đơn retail trừ khi completed. Hàng trong nháp không được giữ, lúc thu có thể đã hết.
- A ⭐: thuốc + vật tư trừ khi **Hoàn tất lượt**; mọi dòng sản phẩm trên hóa đơn (retail hay lượt khám) trừ khi **completed**; kiểm tồn khả dụng lúc thêm dòng và lúc trừ, không giữ hàng.
- B: thuốc trừ ngay khi kê (giữ như cũ).

**38. Cảnh báo kho gửi qua bảng `reminders`** (DB 4.7) nhưng `reminders.remindable` chỉ là lịch hẹn / mũi tiêm; người nhận ghi khác nhau (dòng "Owner/quản lý" vs dòng "Owner + quản lý + lễ tân").
- A ⭐: cảnh báo kho dùng Laravel `notifications` (database) trực tiếp; người nhận = Owner + người có permission `inventory.alerts` của chi nhánh.

**39. Trả hàng cho nhà cung cấp** *[Cần nghiên cứu]*: A ⭐: ngoài V1.

**40. Các mặc định kho chờ xác nhận**: HSD tháng/năm lưu ngày cuối tháng; luôn FEFO, chọn lô tay cần quyền; ngưỡng cận date mặc định 30 ngày (thức ăn 60-90 đặt theo nhóm); không dùng Zalo/SMS cho cảnh báo kho. ok?

---

## G. Nhân sự, ca, lương (GĐ3)

**41. Kỳ lương tuần và tháng chồng nhau**: `payroll_periods.kind` week/month, UNIQUE chỉ theo (starts_on, ends_on) → một ca có thể nằm trong cả kỳ tuần lẫn kỳ tháng, tính 2 lần.
- A ⭐: mỗi shop chọn **một** kiểu kỳ ở Cài đặt; cấm tạo kỳ chồng ngày với kỳ khác.

**42. Quên chấm công, xác minh đúng ca, làm thêm giờ** *[Cần nghiên cứu]*
- A ⭐: giữ tạm thời hiện có (quên chấm ra → `auto_closed`, Owner duyệt); chỉ ca có vào + ra hợp lệ mới tính tiền; đi trễ/về sớm chỉ hiển thị, không trừ tiền ở V1.

**43. Đổi ca giữa nhân viên** *[Cần nghiên cứu]*: A ⭐: V1 không có; Owner sửa xếp ca tay (ghi log).

**44. Ước tính nhu cầu nhân sự nâng cao** *[Cần nghiên cứu]*: A ⭐: ngoài V1, giữ tổng `min_count`.

**45. Lương tháng, phụ cấp, hoa hồng, thưởng/phạt, số ca tối thiểu** *[Cần nghiên cứu]*: A ⭐: ngoài V1, chỉ đơn giá ca; giữ bảng `salary_profiles`, `payroll_adjustments` cho sau.

**46. Mở khóa trang lương bằng mật khẩu đăng nhập** (DB 4.8 *[Mặc định]*): A ⭐: dùng lại mật khẩu đăng nhập. B: mật khẩu cấp 2 riêng.

---

## H. Nền tảng, gói

**47. Gói chưa giới hạn số chi nhánh**; gói Clinic có "hồ sơ y tế nâng cao" không định nghĩa; A3 có "loại hình (spa/thú y/cả hai)" nhưng `tenants` không có cột.
- A ⭐: thêm `plans.max_branches`; bỏ "hồ sơ y tế nâng cao"; loại hình chỉ dùng để gợi ý dịch vụ mẫu lúc tạo shop, không lưu.

**48. Các mặc định v3 chờ xác nhận**: gói/hạn gán tay (Cashier sau MVP); ân hạn 7 ngày; impersonate tối đa 30 phút, chặn đổi mật khẩu/lương/hủy hóa đơn/xóa/mời-khóa nhân viên; 2FA admin bắt buộc; shop tạm khóa vẫn xem + xuất dữ liệu. ok?

**49. Mọi chi nhánh cùng múi giờ** (timezone ở tenant, DB 1.1 *[Mặc định]*): ok?

---

## I. Dọn tài liệu (không cần quyết định, trả lời `50: ok` để Claude sửa)

**50.** Các chỗ lỗi thời / lệch nhau:
- ADR đánh số trùng: PL dùng **0007** cho cả super-admin (4.1) và hóa đơn (5.10); danh sách ADR ở DB và PL 10.2 khác nhau. → đánh lại một danh sách chung.
- ERD ảnh chưa vẽ lại từ v4; mermaid 2.3 còn `pay_multiplier`, `payroll_periods.period`, thiếu `staff_shift_rates`, `shift_availabilities`; 2.2 thiếu `product_units`, `stock_alerts`, trả hàng, tích điểm.
- DS: đầu file chỉ ghi v2, v3; S29, sidebar 3.4 và bảng mục 10 ghi "chỉ Owner" (v5 đã cho ủy quyền); S26 "hệ số lương" (v5 dùng đơn giá ca); S27 tiêu đề "Kiosk · Mọi nhân viên" (v5: chấm trên CMS); S28 chỉ "bảng tháng"; F3/F4 còn luồng ghi khám từ lịch hẹn.
- PL: task 11.1 còn câu "cảnh báo lô ≤ 30 ngày" cũ; ghi chú v4 đặt trước v3; tiến độ ghi ngày 17/10.
- Brief: chưa nhắc combo, hóa đơn nhiều pet, chấm công CMS, lương theo ca, tích điểm (v5).
- Prototype: S3 lễ tân không xem y tế (mục 4), S6 5 bước (mục 8), hạn 2h (mục 10).
- `docs/` trong repo nhánh `dev` cần copy lại bộ tài liệu sau khi chốt.
