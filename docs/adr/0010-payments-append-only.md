# ADR-0010: Khoản thu append-only, sửa hóa đơn bằng bút toán chuyển

- **Trạng thái:** Chấp nhận
- **Ngày:** 2026-10-21
- **Giai đoạn:** MVP

## Bối cảnh
Hóa đơn đã hoàn thành có thể bị chủ shop sửa (ADR-0005: hủy + bản thay thế). Tiền khách đã trả cho bản cũ phải "đi theo" sang bản mới. Cách dễ nghĩ nhất là đổi `payments.invoice_id` sang bản mới, nhưng như vậy là sửa lịch sử tiền: báo cáo ngày cũ, tiền mặt ca cũ và nhật ký đều lệch (rà soát điểm 4).

## Quyết định
- `payments` **append-only**, không bao giờ đổi `invoice_id`. Model ném exception ở `updating`/`deleting`.
- `payments.kind`: `payment`, `refund`, `transfer_out`, `transfer_in`. `amount` luôn > 0.
- Sửa hóa đơn: ghi cặp `transfer_out` (bản cũ) / `transfer_in` (bản mới) bằng đúng số đã thu, theo từng phương thức, trỏ `transfer_payment_id` về khoản gốc. Chênh lệch thì thu thêm (`payment`) hoặc trả lại (`refund`; tiền mặt cần ca đang mở).
- Bút toán chuyển không gắn ca thu ngân, không tính vào "Thực thu", không vào tiền mặt dự kiến của ca.
- Đã thu của hóa đơn = Σ payment + Σ transfer_in − Σ refund − Σ transfer_out.
- Mọi khoản có `idempotency_key` (UNIQUE theo tenant): bấm thu 2 lần không ghi 2 lần.
- CHECK: khoản `payment`/`refund` tiền mặt phải có `cash_session_id`.

## Phương án đã cân nhắc
| Phương án | Ưu | Nhược | Lý do loại |
|---|---|---|---|
| Đổi `invoice_id` của khoản cũ sang bản mới | Đơn giản | Sửa lịch sử tiền; báo cáo, ca thu ngân ngày cũ đổi ngược | Phá bất biến |
| Hoàn tiền bản cũ + thu lại bản mới | Dùng 2 loại khoản có sẵn | Tiền mặt ca hiện tại bị cộng/trừ ảo; cần ca mở dù không có tiền thật | Sai dòng tiền thật |
| Không cho sửa, chỉ trả hàng/hoàn tiền | Đơn giản nhất | Không xử lý được nhập sai giá, sai dịch vụ | Nghiệp vụ cần |

## Hệ quả
- Lịch sử tiền không bao giờ bị viết lại; mọi con số cũ tái lập được.
- Báo cáo phải hiểu 4 loại khoản; "Doanh thu" và "Thực thu" là hai chỉ số khác nhau, ghi nhãn rõ.
- Hóa đơn đã có phiếu trả hàng (GĐ2) thì không sửa được, để không chồng hai cơ chế.

## Kiểm chứng
- Test: sửa hóa đơn đã thu 500k tiền mặt + 300k chuyển khoản → bản mới có 2 `transfer_in` đúng phương thức, ca thu ngân không đổi tiền mặt dự kiến.
- Test: update/delete `payments` qua model → exception.
- Test: cùng `idempotency_key` 2 lần → 1 khoản.

## Liên quan
`design/db-schema.md` mục 2.7, 5.6 · ADR-0005 · sơ đồ 8c, 8e.
