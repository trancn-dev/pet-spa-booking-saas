# Architecture Decision Records

Mỗi ADR ghi một quyết định kiến trúc đã chốt: bối cảnh, quyết định, phương án đã loại, hệ quả và cách kiểm chứng. **Thư mục này là bản chính**: `docs/database/db-schema.md`, `docs/plan/implementation-plan.md` và `CLAUDE.md` chỉ tóm tắt và trỏ về đây; lệch nhau thì ADR thắng. Đủ 10 ADR (0001–0010); task trong plan không viết ADR mới, chỉ điền mục **Kiểm chứng**. Nguồn dữ liệu: `docs/database/db-schema.md`, `docs/analysis/nghiep-vu-da-chot.md`, `docs/analysis/quyet-dinh-ra-soat.md`.

Trạng thái: **Chấp nhận** (đã chốt, code làm theo) · **Đề xuất** (chờ xác nhận) · **Thay thế bởi** (đã bị ADR khác thay).

| # | Quyết định | Trạng thái | Giai đoạn | Task triển khai |
|---|---|---|---|---|
| [0001](0001-single-db-tenant-id.md) | Một DB + `tenant_id` + FK ghép, global scope fail closed | Chấp nhận | MVP | 2.1, 2.2, 3.1 |
| [0002](0002-booking-capacity-lock.md) | Hạn mức đặt online bằng khóa một hàng đếm (cơ sở, nhóm, ngày); kết nối READ COMMITTED; idempotency sau khóa; chỗ online tính cả dòng lễ tân | Chấp nhận (cập nhật 27/10) | MVP | 1.1/Bước 1, 5.4, 5.5, 5.6 |
| [0003](0003-utc-datetime-local-time.md) | Thời điểm lưu UTC `DATETIME`, giờ ca `TIME` local, ngày nghiệp vụ `DATE` local | Chấp nhận | MVP | 3.1, 5.3, 5.4 |
| [0004](0004-branch-not-isolation-boundary.md) | Cơ sở là `branch_id` + FK ghép, không global scope | Chấp nhận | MVP | 3.1, 4.2 |
| [0005](0005-appointment-visit-invoice.md) | Lịch hẹn tách Visit, Visit tách hóa đơn; hóa đơn bất biến sau hoàn thành | Chấp nhận | MVP | 5.6, 5.8–5.10 |
| [0006](0006-stock-ledger-fifo.md) | Sổ kho append-only, xuất FIFO theo lô, phiếu nhập 2 bước | Chấp nhận | GĐ2 | 11.1, 11.2 |
| [0007](0007-reminders-message-deliveries.md) | Tách `reminders` khỏi `message_deliveries`, Zalo trước SMS sau | Chấp nhận | MVP | 6.1 |
| [0008](0008-platform-admin.md) | Admin nền tảng cùng app, guard riêng, đăng nhập thay bằng token 1 lần | Chấp nhận | MVP | 4.1, 4.5, 4.6 |
| [0009](0009-permissions-catalog.md) | Phân quyền spatie teams mode, danh mục quyền cố định trong code | Chấp nhận | MVP | 4.2 |
| [0010](0010-payments-append-only.md) | Khoản thu append-only, sửa hóa đơn bằng bút toán chuyển | Chấp nhận | MVP | 5.10, 5.11 |

Mẫu cho ADR mới: [0000-template.md](0000-template.md). Sơ đồ đi kèm ADR-0002: `docs/design/so-do-dat-lich.md`.

Khi code xong task tương ứng: điền mục **Kiểm chứng** bằng số liệu thật (số tiến trình, số lần chạy, p95) và link tới test.
