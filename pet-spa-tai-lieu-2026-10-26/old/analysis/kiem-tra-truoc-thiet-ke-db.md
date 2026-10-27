# Kiểm tra nhất quán tài liệu v6 (20/10/2026)

Đã đọc: `analysis/so-do-nghiep-vu/nghiep-vu-da-chot.md`, `so-do-nghiep-vu.md` (+ bảng trong `.html`), `old/analysis/mau-thuan-tai-lieu.md`, `design/db-schema.md`, `design/design.md`, `plan/implementation-plan.md`. Các file không đổi trong lúc kiểm (sửa lần cuối 17:43–17:46 UTC, trước khi bắt đầu).

**Cách trả lời:** theo STT, vd `1–9: ok, 12: A, 15: B…`. `ok` = đồng ý đề xuất.

## Đã khớp ở cả 4 file (không cần làm gì)

FIFO theo lô nhập (bỏ lô hết hạn); phiếu nhập 2 bước (kho lập không thấy giá, cộng tồn ngay, Owner điền giá, API kho không có `unit_cost`); phiếu hao hụt chờ duyệt; chỉ Owner xem giá vốn (`inventory.view_cost`); giá bán độc lập giá vốn; hủy/dời trước 24h (`cancel_cutoff_hours`, `reschedule_cutoff_hours` mặc định 24); Zalo ZNS trước, lỗi mới SMS, bỏ email/Telegram; ưu tiên ±15 phút; cùng múi giờ (timezone ở tenant); hạn mức online `floor(người trực × tỉ lệ)` + khóa `booking_capacity_locks`; trừ thuốc/vật tư khi hoàn tất Visit, hàng bán khi hóa đơn hoàn thành.

## (a) Chỗ còn lệch / chưa cập nhật

1. **PL task 5.5** (prompt `BookAppointment`): chỉ thêm ghi chú v6 ở đầu, thân prompt vẫn giữ bước (4) `Staff::whereKey()->lockForUpdate()` và đoạn "Bất kỳ nhân viên: thử lần lượt từng nhân viên". Người/AI code theo prompt dễ làm sai. Đề xuất: viết lại prompt theo v6, bỏ hẳn 2 đoạn đó.
2. **PL task 5.4** (`AvailabilityService`): Input còn `staffId|null = bất kỳ nhân viên`, Output còn `staffIds khả dụng`. Đề xuất: input theo cơ sở + nhóm, output số chỗ online còn lại mỗi khung.
3. **PL task 7.1**: "giới hạn số lịch pending/SĐT" trong khi đã bỏ `pending`. Đề xuất: "số lịch `confirmed` sắp tới / SĐT".
4. **DS sitemap Site** (~dòng 205): vẫn "Chọn nhân viên & giờ", 4 bước. Đề xuất: 5 bước như S6 v6, không chọn nhân viên.
5. **DS S18** (~dòng 469): "Lễ tân xóa/sửa dòng khi khách đổi ý" trong khi dòng từ lượt khám đồng bộ một chiều từ `visit_items`. Chờ mục 14 bên dưới.
6. **DB 4.7 cảnh báo kho** (~dòng 570): gửi qua `reminders` (audience `branch_staff`, `in_app`) + email tóm tắt cho Owner; nhưng `reminders.remindable` chỉ là lịch hẹn/mũi tiêm và channel v6 đã bỏ `email`. Chờ mục 21.
7. **nghiep-vu-da-chot.md**: đầu file vẫn ghi "chưa sửa db-schema, design, plan (vẫn là v5)"; A4 mục 11 chỉ ghi "SMS và Zalo", thiếu "Zalo trước, lỗi mới SMS; OTP qua SMS"; mục B ghi "Không còn câu hỏi mở" trong khi file mâu thuẫn còn ~27 mục mở (dễ hiểu nhầm là đã xong hết).
8. **so-do-nghiep-vu.md** (tóm tắt đầu file): 15.2 "Chủ nhập form nhập hàng trên CMS" trái 16.1 (kho lập phiếu); 13.2 "giá vốn nhập trên phiếu nhập rồi phân cho từng cơ sở" ngụ ý phiếu nhập trung tâm, trái `purchase_receipts.branch_id` = cơ sở nhận; mục 11 thiếu thứ tự Zalo → SMS. Bảng trong `.html` đã ghi "Thay mô tả 15.2" nhưng `.md` chưa.
9. **old/analysis/mau-thuan-tai-lieu.md**: phương án ⭐ của mục 17 ("giảm 100%") và 25 ("chỉ giảm giá được") trái mục 28 (không giảm tay); mục 31, 40 còn "FEFO". Đề xuất mới ở (b).
10. **Mục 50 (dọn tài liệu) chưa làm**: ADR trùng số 0007; ERD png từ 17/10 (trước v6), mermaid 2.2/2.3 thiếu bảng; DS S26 "hệ số lương", S27 "Kiosk · Mọi nhân viên", S29 "Chỉ Owner"; brief chưa cập nhật; `docs/` trong repo chưa copy. ERD sẽ vẽ lại ở bước thiết kế DB nên gộp luôn.

## (b) Nghiệp vụ còn mở

### Chặn thiết kế DB (nên chốt trước)

11. **Mục 18 – một lịch = 1 pet + 1 dịch vụ?** Quyết định cấu trúc `appointments` và cách khóa. Đề xuất **A**: giữ 1 lịch = 1 pet + 1 dịch vụ/combo, form cho nhiều dòng, mỗi dòng một lịch (đã ghi tạm ở S2).
12. **Mục 23 – chống 2 hóa đơn nháp cùng khách.** Đề xuất **A**: cột sinh `open_draft_key` (owner + cơ sở + ngày, NULL khi hết nháp) + UNIQUE, giữ lock owner.
13. **Mục 24 – nháp treo qua ngày.** Ảnh hưởng khóa ở mục 12 (có "ngày" trong key hay không). Đề xuất **A**: không tự hủy; S18 có mục "Nháp từ ngày trước"; hôm sau mở nháp mới; hủy có lý do.
14. **Mục 25 – lễ tân sửa dòng do bác sĩ ghi** (phương án cũ dựa vào giảm giá, nay không dùng được). Đề xuất mới: dòng có `visit_item_id` ở thu ngân **chỉ đọc**; bỏ dịch vụ thì sửa ở lượt khám (S17) khi hóa đơn còn nháp; dòng "mua thêm"/gõ tay lễ tân sửa tự do.
15. **Mục 17 – thú hoang dồn một hóa đơn** (phương án cũ "giảm 100%" không còn hợp lệ). Đề xuất mới: vì Visit đã độc lập hóa đơn, lượt thú hoang **không tự mở hóa đơn**; có người trả thì lễ tân tạo hóa đơn riêng, ghi "người thanh toán" (tên, SĐT); kho vẫn trừ khi hoàn tất Visit.
16. **Mới – người có 2 role (vd bác sĩ kiêm spa) được đếm vào hạn mức cả 2 nhóm?** Hiện `staff_working_hours` không có cột nhóm, đếm theo role nên sẽ đếm đôi. Đề xuất: thêm `service_group` vào khung làm việc (mỗi khung trực một nhóm); người một role thì tự điền.
17. **Mục 47 – gói và loại hình shop.** Đề xuất **A**: thêm `plans.max_branches`; bỏ "hồ sơ y tế nâng cao"; loại hình chỉ để gợi ý dịch vụ mẫu, không lưu.
18. **Mục 16 – combo trộn khám + spa.** Vì `appointments.service_group` lấy từ dịch vụ, combo phải cùng một nhóm. Đề xuất **A**: không cho trộn.

### Để sau được (GĐ2/GĐ3 hoặc không đổi bảng)

19. **Mục 31 (GĐ2) – vaccine nối kho.** Đề xuất: `vaccines.product_id NULL`; ghi mũi tiêm thì trừ kho **FIFO** khi hoàn tất Visit, tự điền số lô.
20. **Mục 32 (GĐ2) – hàng hai mục đích.** Đề xuất **A**: `kind` chỉ để phân nhóm; bán lẻ theo `requires_prescription` + cờ `is_sellable`.
21. **Mục 38 (GĐ2) – kênh cảnh báo kho.** Đề xuất **A**: dùng Laravel `notifications` (database), người nhận = Owner + người có `inventory.alerts`; email tóm tắt cho Owner giữ (thông báo nội bộ, không phải kênh khách).
22. **Mục 40 (GĐ2) – mặc định kho** (viết lại theo FIFO): HSD tháng/năm lưu ngày cuối tháng; FIFO, chọn lô tay cần quyền; cận date mặc định 30 ngày; không Zalo/SMS cho cảnh báo kho. ok?
23. **Mục 33, 34 (GĐ2) – định mức vật tư, vật tư trong combo.** Đề xuất **A** cả hai (giữ cơ chế hiện có, sửa từng dòng).
24. **Mục 29 – định nghĩa doanh thu.** Đề xuất **A**: doanh thu = hóa đơn hoàn thành trước thuế theo ngày hoàn thành (bản thay thế tính ngày sửa, trả hàng trừ ngày trả); "thực thu" là KPI dòng tiền riêng.
25. **Mục 6 – gói đếm nhân viên nào.** Đề xuất **A**: chỉ người có tài khoản đang active (kể cả Owner).
26. **Mục 12 – phiên khách 30 ngày.** Đề xuất ok.
27. **Mục 48 – mặc định v3** (gán gói tay, ân hạn 7 ngày, impersonate 30 phút, 2FA admin, shop khóa vẫn xem/xuất). Đề xuất ok.
28. **Mục 41–46 (GĐ3)**: 41 **A** (mỗi shop một kiểu kỳ lương, cấm kỳ chồng ngày); 42 **A**; 43 **A** (V1 không đổi ca); 44, 45 **A** (ngoài V1); 46 **A** (dùng lại mật khẩu đăng nhập).
29. **Mục 9, 39 và chuyển kho "nhận thiếu / trả về"**: đã áp theo gợi ý, cần bạn xác nhận ok.

## (c) Kết luận

Nghiệp vụ lõi đã đủ và khớp giữa các file. **Có thể sang thiết kế DB** sau khi: chốt 11–18 (8 mục, đều có đề xuất, trả `11–18: ok` là xong) và sửa nhanh 1–9. Các mục 19–29 không chặn MVP, chốt trước GĐ2/GĐ3. Mục 10 gộp vào bước vẽ lại ERD.

## Đã chốt (20/10/2026, TranCN)

- 11: **Một lịch có thể nhiều pet** (khách gọi "lát mang 2 bé đến khám" thì đặt một lịch). DB: `appointments` (đầu lịch) + `appointment_items` (mỗi pet + dịch vụ); mỗi dòng chiếm một chỗ hạn mức của nhóm nó.
- 12, 14, 15, 17: ok / A theo đề xuất.
- 13: ok, thêm cảnh báo dọn hóa đơn chưa hoàn thành từ ngày trước (thông báo đầu ngày + thẻ trên màn thu ngân).
- 16: một người đi làm tính là một người trực; người vừa là bác sĩ vừa là groomer chỉ tính vào nhóm ghi ở khung trực, không đếm hai lần.
- 18: chỉ có combo spa (cắt tỉa + tắm gội); khám và spa là dịch vụ riêng, gom tính tiền chung một hóa đơn.

- 19–29: ok theo đề xuất.

Đã áp vào `design/db-schema.md`. Không còn mục mở.
