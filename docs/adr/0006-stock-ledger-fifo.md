# ADR-0006: Sổ kho append-only, xuất FIFO theo lô nhập, phiếu nhập 2 bước

- **Trạng thái:** Chấp nhận
- **Ngày:** 2026-10-21
- **Giai đoạn:** GĐ2

## Bối cảnh
Shop bán thuốc, vật tư, hàng bán lẻ ở nhiều cơ sở. Thuốc có lô và hạn dùng. Chủ shop cần lãi gộp đúng (giá vốn thật), nhưng không muốn nhân viên kho thấy giá nhập. Tồn bị trừ đồng thời từ nhiều nguồn: hoàn tất Visit, hoàn thành hóa đơn, chuyển kho.

## Quyết định
- `stock_movements` là **sổ kho append-only**, mỗi lô một dòng, là nguồn duy nhất cho giá vốn và đối soát. Sai thì ghi bút toán mới, không sửa dòng cũ.
- `stock_batches` giữ số lượng từng lô; tổng các lô = `stock_items.on_hand`. Hàng không theo lô có một lô ngầm.
- Xuất **FIFO theo lô nhập** (`ORDER BY received_at, id`), bỏ lô quá hạn. Giá vốn = `unit_cost` của lô đã xuất. Chọn lô tay cần quyền `inventory.override_batch`.
- Mọi thay đổi tồn đi qua một service `StockLedger`: khóa `stock_items` theo `product_id` tăng dần → kiểm khả dụng (`on_hand − reserved`) → chọn lô → trừ lô + `on_hand` → ghi movement.
- Phiếu nhập 2 bước: nhân viên kho lưu phiếu (`posted`, cộng tồn ngay, không thấy giá) → chủ điền giá (`costed`, cập nhật `unit_cost` của lô). API của nhân viên kho không bao giờ có khóa `unit_cost`.
- Đổi trạng thái phiếu bằng `UPDATE … WHERE status = :expected`.

## Phương án đã cân nhắc
| Phương án | Ưu | Nhược | Lý do loại |
|---|---|---|---|
| Bình quân gia quyền | Đơn giản, một giá vốn mỗi mặt hàng | Mất thông tin lô, hạn dùng; lô nhập chưa có giá làm giá bình quân sai | Thuốc cần truy lô |
| FEFO (hết hạn trước xuất trước) | Giảm hàng hết hạn | Lệch với thứ tự giá vốn; nhân viên lấy theo kệ thường là FIFO | Chọn FIFO, có cảnh báo cận hạn và chọn lô tay khi cần |
| Chỉ lưu `on_hand`, không sổ | Ít bảng | Không đối soát được, không có giá vốn theo thời điểm | Không đáp ứng báo cáo lãi gộp |

## Hệ quả
- Lãi gộp đúng theo lô; truy được lô đã bán cho ai.
- Lô nhập chưa điền giá có `unit_cost` NULL: báo cáo lãi gộp phải đánh dấu "chưa đủ giá vốn".
- Sổ và kệ có thể lệch lô (nhân viên lấy nhầm lô): màn hình hiện lô sẽ xuất, kiểm kê theo lô.
- Thứ tự khóa `invoices` → `visits` → `stock_items` phải giữ ở mọi Action có trừ kho.

## Kiểm chứng
- Test concurrency: 10 request bán món còn 1 → đúng 1 thành công.
- Test: nhận phiếu chuyển kho 2 lần không cộng đôi.
- Test: response API cho role kho không chứa khóa `unit_cost`.

## Liên quan
`docs/database/db-schema.md` mục 2.9, 5.5 · sơ đồ 11a, 11b, 12a.
