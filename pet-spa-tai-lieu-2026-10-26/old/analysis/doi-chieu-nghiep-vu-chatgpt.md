# Đối chiếu: file nghiệp vụ mới (ChatGPT) với thiết kế hiện tại

Nguồn so: `tong-hop-nghiep-vu-pet-shop.md` với `design/db-schema.md` (v4), `design/design.md`, `plan/implementation-plan.md` (19/10/2026).
> **Đã áp dụng (19/10/2026):** user chọn **bản mới cho cả 22 điểm** (bản mới đã được trao đổi lại). Đã ghi vào db-schema.md, design.md, implementation-plan.md với nhãn **(v5)**. Ở các điểm bản mới để mở (#5, #7, #15, #17…) tài liệu ghi **[Cần nghiên cứu]**, không chốt thay. Phần "Gợi ý" bên dưới chỉ còn giá trị lịch sử.

Mỗi mục ghi **Mới** (file ChatGPT), **Hiện có** (thiết kế), **Gợi ý** của Claude. Trả lời theo dạng `1: hiện có, 2: mới…`.

**Đã khớp, không cần chọn:** admin nền tảng tạo shop, Owner mời nhân viên, role/permission, gói và khóa shop; nhiều chi nhánh, khách/pet dùng chung cả shop, tồn kho riêng chi nhánh, nhân viên theo `staff_branches`; lịch hẹn tách lượt khám; lễ tân ghi lý do đến, chỉ bác sĩ ghi chẩn đoán; bệnh án gắn pet (chi nhánh suy ra qua lượt khám); cảnh báo dị ứng; giá nhập theo lô, lô + HSD + FEFO, cận date, tồn thấp, nhà cung cấp, kiểm kê; tra tồn liên chi nhánh + điều chuyển có trạng thái; chống đặt trùng ở backend; Site công khai tách CMS; lưu chuồng và gợi ý bác sĩ theo chuyên môn để sau.

---

## A. Hóa đơn

**1. Trạng thái hóa đơn**
- Mới: chỉ `draft` → `completed` (hoàn tất khi đã thanh toán xong).
- Hiện có: `draft` → `issued` (khóa) → `partially_paid` / `paid`, hủy bằng `void`.
- Gợi ý: **hiện có**. Cho trả nhiều lần (tiền mặt + QR) và khóa nội dung trước khi thu tiền; "completed" của file mới tương đương `paid`.

**2. Thời điểm mở hóa đơn nháp**
- Mới: lễ tân mở hóa đơn nháp ngay khi khách đến; trong lúc pet đang khám/spa thì thêm thức ăn, đồ chơi vào; dịch vụ bác sĩ ghi tự đổ vào cùng hóa đơn.
- Hiện có: hóa đơn nháp chỉ sinh khi lượt khám đã `done` (`CreateVisitInvoice`); sản phẩm bán kèm thêm ở bước thu ngân.
- Gợi ý: **mới**. Đúng thực tế quầy hơn. Điều kiện: chặn chuyển `issued` khi còn lượt khám chưa `done`.

**3. Khách mang nhiều thú cưng, một hóa đơn**
- Mới: một lần đến có thể nhiều pet, mỗi khoản gắn đúng pet, lễ tân tổng hợp vào hóa đơn nháp (ngụ ý một hóa đơn).
- Hiện có: một hóa đơn chỉ gắn một lượt khám (`invoices.visit_id` + unique `active_visit_id`) → 2 pet = 2 hóa đơn.
- Gợi ý: **mới**. Đổi thành hóa đơn gắn khách, nhiều lượt khám trỏ về (`visits.invoice_id`), dòng hóa đơn mang `pet_id`. Sửa sớm (MVP, task 5.10) rẻ hơn sửa sau.

**4. In hóa đơn nháp**
- Mới: in được bản nháp, ghi rõ "Nháp / Tạm tính".
- Hiện có: chưa nói tới in nháp (chỉ in sau thanh toán).
- Gợi ý: **mới**. Chỉ thêm watermark trên mẫu in, không đổi DB.

**5. Sửa / hủy hóa đơn đã hoàn tất**
- Mới: để mở (cần nghiên cứu); lễ tân không được sửa trực tiếp.
- Hiện có: không sửa; `void` có lý do + lập hóa đơn mới; hoàn tiền là dòng `refund`; void cho Owner hoặc lễ tân trong ngày *[mặc định, cần xác nhận]*.
- Gợi ý: **hiện có**, nhưng thu hẹp void về **chỉ Owner** cho khớp ý "lễ tân không được sửa".

**6. Bán thuốc không qua lượt khám**
- Mới: khách chỉ mua "thức ăn, đồ chơi, **thuốc**…" không cần lượt khám.
- Hiện có: hóa đơn bán lẻ cấm mọi dòng thuốc ("thuốc phải kê qua lượt khám").
- Gợi ý: **dung hòa**: chỉ cấm sản phẩm `requires_prescription = true`; thuốc không kê đơn (nhỏ gáy, vitamin) bán lẻ được. Cờ này đã có trong `products`.

## B. Giá vốn, vật tư, combo, báo cáo

**7. Phương pháp giá vốn**
- Mới: chưa chốt (FIFO, bình quân hoặc khác).
- Hiện có: đã chốt **bình quân gia quyền theo chi nhánh** (ADR 6); lô vẫn lưu giá nhập riêng, xuất theo FEFO.
- Gợi ý: **hiện có**. Ví dụ vaccine 180k/190k vẫn ra lãi gộp đúng theo bình quân; FIFO theo lô làm giá vốn phụ thuộc lô nào bị xuất, phức tạp hơn khi điều chuyển/kiểm kê mà lợi ích nhỏ với tiệm.

**8. Vật tư tiêu hao: định mức hay thực tế**
- Mới: định mức chỉ để tham khảo (có thể theo cỡ pet); nhân viên ghi/sửa lượng thực tế, đổi vật tư khi pet dị ứng.
- Hiện có: `service_consumables` định mức cố định theo dịch vụ; `CompleteVisit` tự trừ kho theo định mức.
- Gợi ý: **mới**. Định mức điền sẵn thành dòng `consumable` trong lượt khám, nhân viên sửa số lượng/đổi sản phẩm trước khi hoàn tất; thêm `size` (NULL = mọi cỡ) vào `service_consumables`.

**9. Combo dịch vụ + mã dịch vụ**
- Mới: combo có mã và giá riêng, gồm nhiều dịch vụ con; bán như một dòng. Dịch vụ có mã.
- Hiện có: không có combo, `services` không có cột mã.
- Gợi ý: **mới**, bản gọn: thêm `services.code`, `is_combo`, bảng `service_combo_items`. V1 một nhân viên làm cả combo (một lịch hẹn, giữ nguyên cơ chế khóa nhân viên); vật tư combo lấy theo định mức dịch vụ con.

**10. Công thức lãi gộp và nguồn giá vốn**
- Mới: Lãi gộp = Doanh thu − Giá vốn hàng bán − Giá vốn **vật tư đã dùng**; tách doanh thu bán lẻ / dịch vụ; theo tuần, tháng, khoảng tùy chọn, từng chi nhánh hoặc cả shop.
- Hiện có: lợi nhuận = doanh thu − giá vốn − thuế, giá vốn lấy từ `invoice_lines.unit_cost`. Vật tư tiêu hao không thành dòng hóa đơn nên **bị thiếu trong giá vốn**.
- Gợi ý: **mới**. Giá vốn lấy từ sổ kho (`stock_movements` loại `sale` + `visit_usage`); doanh thu tính trước thuế thay vì "trừ thuế".

## C. Kho bổ sung

**11. Hàng trả lại**
- Mới: có nghiệp vụ trả hàng (cập nhật tồn và giá vốn).
- Hiện có: chỉ có loại movement `return` và dòng `refund`, chưa có quy trình.
- Gợi ý: **mới, bản nhỏ**: khách trả hàng = refund + movement `return` gắn dòng hóa đơn gốc, hoàn đúng lô; trả nhà cung cấp để sau.

**12. Audit log nghiệp vụ trong shop**
- Mới: ghi người tạo, sửa, xác nhận, hủy cho nghiệp vụ quan trọng.
- Hiện có: chỉ `platform_audit_logs` (admin), `sensitive_access_logs` (lương) và vài cột `created_by` / `voided_by` rải rác.
- Gợi ý: **mới**: một bảng `activity_logs` theo tenant cho void/refund, điều chỉnh kho, sửa công, đổi giá.

## D. Nhân sự, ca, lương

**13. Hồ sơ chuyên môn bác sĩ**
- Mới: lưu bằng cấp, chứng chỉ hành nghề cho bác sĩ.
- Hiện có: `staff` chỉ có `position`, không có cột chuyên môn.
- Gợi ý: **mới**, nhẹ: thêm `license_no`, `qualifications TEXT` (NULL) vào `staff`.

**14. Nhân viên tự đăng ký ca**
- Mới: nhân viên đăng ký ngày/ca có thể làm → Owner xem, xếp, xác nhận.
- Hiện có: chỉ Owner kéo-thả xếp ca (S26), không có bước đăng ký.
- Gợi ý: **mới** (vẫn GĐ3): bảng `shift_availabilities`, S26 hiện ai đã đăng ký từng ô.

**15. Ước tính nhu cầu nhân sự**
- Mới: "thuật toán ước tính" từ dịch vụ, số ca, số người tối thiểu, vai trò (cần nghiên cứu).
- Hiện có: `coverage_requirements` (tối thiểu theo vị trí × ca × thứ) + cảnh báo thiếu người.
- Gợi ý: **hiện có**. Đã đáp ứng đúng ví dụ trong file mới; dự báo theo lượng lịch hẹn để sau.

**16. Cách chấm công**
- Mới: nhân viên đăng nhập CMS, bấm chấm công.
- Hiện có: kiosk tablet ở quầy, mã NV + PIN, kiểm IP chi nhánh; bảo vệ/lao công không cần tài khoản.
- Gợi ý: **hiện có**. Bấm trên CMS thì chấm hộ từ nhà được, và người không có tài khoản không chấm được.

**17. Cách tính lương**
- Mới: đơn giá **theo từng nhân viên × từng ca** (ca tối giá khác), cộng số ca theo tuần/tháng ra số tiền dự kiến; lương tháng, phụ cấp, hoa hồng, thưởng/phạt chưa chốt.
- Hiện có: `salary_profiles` (tháng/ngày/giờ) × hệ số ca, tăng ca, giờ đêm, ngày lễ theo luật, thưởng/phạt/tạm ứng, chốt kỳ tháng.
- Gợi ý: **mới cho V1**: bảng đơn giá `staff_shift_rates(staff, shift_template, rate)`, giữ `payroll_periods` + chốt kỳ; tăng ca/đêm/lễ theo luật để giai đoạn sau. Đúng cách tiệm trả thật, công thức ít sai.

**18. Ai được xem lương**
- Mới: chủ shop **hoặc người được ủy quyền**; xác thực lần hai bằng mật khẩu hoặc tương đương.
- Hiện có: chỉ Owner; nhập lại mật khẩu đăng nhập, mở 10 phút, có log.
- Gợi ý: **mới**, đổi nhỏ: kiểm theo permission `payroll.view` (Owner gán được cho người khác) thay vì "chỉ Owner"; giữ cơ chế mở khóa 10 phút.

## E. Đặt lịch, khách hàng, website

**19. Mô hình "năng lực phục vụ" khi đặt lịch**
- Mới: kiểm "năng lực còn lại" theo chi nhánh + dịch vụ + khung giờ (kiểu còn N chỗ).
- Hiện có: kiểm theo **nhân viên cụ thể** (khóa hàng `staff`, "nhân viên bất kỳ" thử lần lượt); phòng/bàn grooming để sau.
- Gợi ý: **hiện có**. Năng lực = số nhân viên rảnh, đã chống trùng chắc chắn; thêm tài nguyên phòng/bàn khi cần.

**20. Thông tin khách: địa chỉ và tích điểm**
- Mới: lưu địa chỉ; có chương trình thành viên/tích điểm theo chính sách shop.
- Hiện có: `owners` không có địa chỉ; không có tích điểm.
- Gợi ý: **mới cho địa chỉ** (thêm `address` NULL); **tích điểm để sau V1** (đã giữ `owner_id` tùy chọn trên hóa đơn bán lẻ nên thêm sau không phải đổi DB cũ).

**21. Lưu chuồng trong dữ liệu**
- Mới: ngoài V1.
- Hiện có: `services.category` có `boarding`, dữ liệu mẫu "Lưu chuồng qua đêm 200.000 ₫/đêm", màu tím trong design.
- Gợi ý: **mới**: bỏ khỏi dữ liệu mẫu và UI V1; giữ giá trị enum cũng được, vô hại.

**22. Trang Điều khoản sử dụng**
- Mới: Site có trang Điều khoản, Owner tự soạn; không bắt khách tích đồng ý tại quầy.
- Hiện có: Site có trang chủ, dịch vụ, sản phẩm, đặt lịch; chỉ có checkbox "đồng ý chính sách hủy" khi đặt online; không có trang điều khoản.
- Gợi ý: **mới**: trang `/{slug}/dieu-khoan`, nội dung soạn ở Cài đặt › Site (một cột text trên tenant), link ở footer Site.

---

Sau khi bạn chọn, Claude cập nhật db-schema.md, design.md và plan theo các mục "mới" (đánh dấu v5).
