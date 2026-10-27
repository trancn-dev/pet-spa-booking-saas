# ADR-0005: Lịch hẹn tách Visit, Visit tách hóa đơn; hóa đơn bất biến sau hoàn thành

- **Trạng thái:** Chấp nhận
- **Ngày:** 2026-10-21
- **Giai đoạn:** MVP

## Bối cảnh
Ba thứ hay bị gộp thành một bảng "booking":
- **Lịch hẹn** là dự định: có thể không đến, đến một phần pet, dời giờ.
- **Visit** là việc thật đã làm cho một pet ở một nhóm (khám hoặc spa): có số thứ tự hàng đợi, người làm, bệnh án, thuốc. Có Visit không có lịch (khách vãng lai, cấp cứu).
- **Hóa đơn** là tiền: một khách mang 2 pet, khám + spa → 3 Visit, khách muốn trả **một** lần.

Hóa đơn đã thu tiền phải giữ nguyên để đối soát két, báo cáo, thuế.

## Quyết định
- `appointments` (một khách) → `appointment_items` (mỗi pet + dịch vụ một dòng). Check-in gom các dòng cùng pet + cùng nhóm vào **một** Visit qua `appointment_items.visit_id` (khám + tiêm cùng pet = 1 Visit; khám + spa = 2 Visit). Check-in 2 lần idempotent.
- `visits` độc lập hóa đơn: trạng thái cuối là `done`; đã trả tiền hay chưa xem ở hóa đơn. Cấp cứu cho phép `customer_id`, `pet_id` NULL tới khi bổ sung hồ sơ.
- Tạo Visit thì gắn vào hóa đơn `draft` của khách ở cơ sở hôm nay (`open_draft_key` unique), chưa có thì tạo.
- Hóa đơn `draft` → `completed` | `void`. `completed` **bất biến**: Action từ chối, model guard ném exception ở `updating`. Sửa = hủy bản cũ (giữ số, lý do) + bản thay thế (`replaces_invoice_id`), chỉ chủ shop (`invoices.amend`).
- Thứ tự khóa: `invoices` → `visits` (→ `stock_items` ở GĐ2). Mọi thao tác đổi tiền khóa hóa đơn trước.

## Phương án đã cân nhắc
| Phương án | Ưu | Nhược | Lý do loại |
|---|---|---|---|
| Một bảng booking có cột trạng thái tới "đã thanh toán" | Ít bảng | Không biểu diễn được vãng lai, cấp cứu, 1 lịch nhiều pet, 1 hóa đơn nhiều Visit | Sai mô hình |
| Visit có `appointment_item_id` UNIQUE | Liên kết 1-1 rõ | Khám + tiêm cùng pet thành 2 Visit, 2 số thứ tự | Trái nghiệp vụ (rà soát điểm 10) |
| Cho sửa hóa đơn đã hoàn thành tại chỗ + log | Đơn giản cho người dùng | Báo cáo ngày cũ đổi ngược, két lệch, mất dấu vết | Tiền phải append-only |

## Hệ quả
- Mô hình đúng mọi luồng: vãng lai, cấp cứu, nhiều pet, khám + spa, đến thiếu pet.
- Nhiều bảng hơn, nhiều trạng thái phải đồng bộ (trạng thái lịch suy ra từ dòng; dòng `visit_item` đồng bộ một chiều vào hóa đơn nháp).
- Báo cáo doanh thu theo ngày hoàn thành; ngày sửa ghi −bản cũ + bản thay thế, không sửa ngược ngày gốc.

## Kiểm chứng
- Test: check-in 2 lần không tạo 2 Visit; pet khám + tiêm = 1 Visit.
- Test: update hóa đơn `completed` qua model → exception.
- Test concurrency: thêm dòng Visit đồng thời với hoàn thành hóa đơn → dòng không lọt vào hóa đơn đã chốt.

## Liên quan
`design/db-schema.md` mục 2.5–2.7, 5.6 · ADR-0010 · sơ đồ 3a, 8a–8c trong `analysis/so-do-nghiep-vu/so-do-nghiep-vu.md`.
