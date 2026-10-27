# Quyết định rà soát chéo tài liệu (21/10/2026)

Rà chéo nghiệp vụ, sơ đồ, db-schema, design, danh mục màn, plan. Mỗi dòng: vấn đề → phương án chọn → file đã sửa. Tài liệu chốt đã cập nhật tại chỗ.

Viết tắt file: **NV** `analysis/so-do-nghiep-vu/nghiep-vu-da-chot.md` · **SĐ** `so-do-nghiep-vu.html` · **DB** `design/db-schema.md` · **DS** `design/design.md` · **DM** `design/danh-muc-man-hinh.md` · **PL** `plan/implementation-plan.md`.

## Race condition, deadlock, tiền

| # | Vấn đề | Phương án chọn | File |
|---|---|---|---|
| 1 | `INSERT IGNORE` hàng `booking_capacity_locks` **trong** transaction: 2 request trúng khóa trùng cùng giữ khóa S rồi cùng xin X → deadlock ngay ở test 20 tiến trình | Tạo hàng khóa trước transaction (autocommit), trong transaction chỉ `SELECT … FOR UPDATE`; retry 3 lần | DB 2.5, 5.1 · PL 5.5 |
| 2 | Kiểm "pet không có lịch chồng giờ" không có khóa: 2 lịch lễ tân khác nhóm cho cùng pet lọt cả hai | Khóa hàng `customers` đầu transaction đặt/dời lịch | DB 5.1 · PL 5.5 |
| 3 | Chưa có thứ tự khóa chung | Chốt: `customers` → `booking_capacity_locks` (ngày, nhóm tăng dần) → `tenant_usage`; `invoices` → `visits` → `stock_items` | DB 1 · PL 1.3 (CLAUDE.md) |
| 4 | Sửa hóa đơn "chuyển payments sang bản mới" phá quy tắc payments append-only | Thêm `payments.kind` `transfer_out`/`transfer_in`: ghi bút toán chuyển, không đổi `invoice_id`; không tính vào Thực thu và tiền mặt ca | DB 2.7 · PL 5.10, 5.11, 9.1 · DS S18 |
| 5 | Doanh thu khi sửa hóa đơn khác ngày không rõ (sửa ngược báo cáo cũ hay tính đôi) | Ngày gốc giữ nguyên; ngày sửa ghi −bản cũ + bản thay thế. Hóa đơn đã có phiếu trả hàng thì không sửa | DB 2.7 · NV 7 · PL 5.10, 9.1 |
| 6 | `open_draft_key` đụng nhau: bán lẻ có khách cùng ngày với hóa đơn dịch vụ; 2 hóa đơn thú hoang cùng ngày | Khóa chỉ áp cho `draft` + `kind=service` + không có `payer_name` | DB 2.7 · PL 3.1 |
| 7 | Thêm dòng Visit có thể lọt sau khi hóa đơn đã chốt | Mọi thao tác đổi tiền khóa `invoices` trước `visits`; Visit hủy thì gỡ dòng khỏi nháp | DB 2.7 · PL 5.8, 5.9 |
| 8 | Quota lịch/tháng chưa rõ đếm gì | Đếm mọi lịch (online + lễ tân), theo tháng tạo (giờ shop), theo lịch; hủy không trả suất; là khóa cuối | DB 2.8, 5.1 · NV 1 · PL 5.5 |
| 9 | Giới hạn nhân viên chỉ kiểm khi mời/nhận | Kiểm thêm khi kích hoạt lại nhân viên, tính cả lời mời còn hạn, khóa hàng `tenants` | DB 2.1 · PL 4.4 |

## Mâu thuẫn mô hình dữ liệu

| # | Vấn đề | Phương án chọn | File |
|---|---|---|---|
| 10 | `visits.appointment_item_id` UNIQUE = 1 dòng → 1 Visit, nhưng nghiệp vụ "khám + tiêm cùng pet = 1 Visit" | Đảo liên kết: `appointment_items.visit_id`; check-in gom dòng theo pet + nhóm, idempotent | DB 2.5, 2.6, ERD, 7 · PL 3.1, 5.6, 5.8 · DS S3 · NV 4 |
| 11 | `appointment_items` chép `customer_id`, `branch_id` có thể lệch khỏi lịch | FK ghép `(tenant_id, appointment_id, customer_id, branch_id)` → `appointments` | DB 2.5 · PL 3.1 |
| 12 | Lời mời có 1 `role_name`, không có cơ sở, trong khi S11/plan mời nhiều role + cơ sở chính/phụ | `invitations.role_names JSON`, `primary_branch_id`, `branch_ids JSON`; role owner chỉ admin mời | DB 2.2 · PL 4.4 · DS S11 |
| 13 | A6 "thêm admin = gửi lời mời" nhưng không có bảng | Thêm `platform_admin_invitations` + màn A7 (đặt mật khẩu → bắt buộc 2FA) | DB 2.1 · DM 5 · DS A6 · PL 4.1 |
| 14 | `pickup_ready` (báo đón sau spa) không có đối tượng nhắc | `reminders.remindable_type` thêm `visit` | DB 2.8 · PL 5.9 |
| 15 | S2 có ô "Không gửi nhắc" nhưng DB không lưu | `appointments.send_reminders`; nhắc có `send_at` đã qua thì không tạo | DB 2.5, 2.8 · PL 6.1 · DS S3 |
| 16 | Badge "cắn người", "sợ máy sấy" không có cột | `pets.behavior_notes`, là cảnh báo ai cũng thấy | DB 2.3 · NV 3 · DS 3.5 · PL 5.1 |
| 17 | Site hiện "kết quả xét nghiệm được chia sẻ" nhưng không có cờ chia sẻ | `attachments.is_shared_with_customer` (mặc định tắt) | DB 2.6 · NV 2 · DS S17, S21 · PL 6.2, 7.1 |
| 18 | Cài đặt Kênh nhắn tin, Site (S12.4, S12.5) không có chỗ lưu | `tenant_settings`: logo, ảnh bìa, giới thiệu, bật sản phẩm, `zalo_config`/`sms_config` mã hóa | DB 2.1 · PL 6.1 |
| 19 | Hoàn tác hủy lịch không có action trong lịch sử | `appointment_changes.action` thêm `restored` | DB 2.5 · PL 5.6 |
| 20 | Bán lẻ có ô SĐT nhưng không rõ có tạo hồ sơ | Khớp khách có sẵn thì gắn, không thì chỉ ghi SĐT | DB 2.7 · NV 7 · DS S19 · PL 5.10 |

## Lộ dữ liệu, index

| # | Vấn đề | Phương án chọn | File |
|---|---|---|---|
| 21 | `notifications` gắn `users` (toàn cục): người làm 2 shop thấy chéo | notifiable = `staff` | DB 2.8, 5.2 · PL 5.9, 6.1 |
| 22 | Thao tác khi admin đăng nhập thay không phân biệt trong nhật ký shop | `activity_logs.impersonation_session_id` | DB 2.8 · PL 4.6 |
| 23 | Tìm "4 số cuối SĐT" bằng `LIKE '%…'` quét cả bảng; tìm theo tên pet không index; index tiêm đến hạn thiếu `tenant_id`; PK bảng nối không bắt đầu bằng `tenant_id` | `customers.phone_suffix` generated + index; IDX pets(tenant_id, name); IDX(tenant_id, superseded_at, next_due_on); PK bảng nối có `tenant_id`; IDX hóa đơn nháp cũ thêm `business_date` | DB 1, 2.2–2.7 · PL 5.1, 6.3 |
| 24 | OTP chỉ ghi "rate limit" chung, dễ bị spam SMS tốn tiền | Gửi lại sau 60s, 5 lần/giờ/SĐT, 20 lần/giờ/IP, key theo shop | DB 2.3 · DS S21 · PL 7.1 |

## Quyền, phạm vi, tài liệu

| # | Vấn đề | Phương án chọn | File |
|---|---|---|---|
| 25 | Ma trận cho chủ shop "Ghi" Visit khám, trái quy tắc "chủ không tự ghi bệnh án" | Chủ shop không nhận Visit khám nếu không gán role Bác sĩ (Visit spa vẫn làm được) | DS 8 · SĐ 9 · DB 2.6 · PL 4.2, 5.9 |
| 26 | Lễ tân xem báo cáo: ma trận "hôm nay", S5 "không doanh thu", sơ đồ "—" | Lễ tân thấy doanh thu hôm nay cơ sở mình; bác sĩ, spa không | DS S5 · SĐ 9 · NV 2 · DB 3.2 · PL 9.1 |
| 27 | Mặc định quyền lệch ma trận: QL hủy nháp, thu ngân/QL trả hàng | `invoices.void_draft` + `returns.create` thêm branch_manager (và cashier cho trả hàng) | DB 3.2 · NV 7 · PL 11.5 |
| 28 | Ma trận và sơ đồ 9 chưa ghi quyền nhạy cảm "giao được", "Phân quyền chỉ chủ" | Ghi rõ theo quyết định 21/10 | DS 8 · SĐ 9 |
| 29 | Vòng đời hết hạn gói chỉ có ở design/plan, nghiệp vụ không có | Ân hạn 7 ngày → tạm khóa (xem được, không ghi, Site ngừng nhận lịch, không nhắc) | NV 1 · DB 2.1 |
| 30 | Nghiệp vụ chưa ghi "nhân viên luôn thuộc shop" (lý do bỏ S38) | Ghi luồng admin → chủ shop → cơ sở → nhân viên; không còn shop thì chặn đăng nhập | NV 1 · DM 5 |
| 31 | design.md mục 5 có bảng màn riêng (thiếu S36–S43, A7) song song với danh mục | Mục 5 trỏ sang danh-muc-man-hinh.md (nguồn duy nhất) | DS 5 |
| 32 | Danh mục màn còn 6 điểm "chờ quyết" | Chốt cả 6 theo đề xuất; kiosk `/{slug}/kiosk` + token thiết bị | DM 5 · PL 12.2 |
| 33 | Plan 1.3 không copy danh mục màn vào repo | Thêm `danh-muc-man-hinh.md` vào `docs/design/` và CLAUDE.md | PL 1.3 |

## Chưa đổi

- Sơ đồ Mermaid (`so-do-nghiep-vu.md`) giữ nguyên: lời nghiệp vụ "chuyển khoản đã thu sang bản mới" vẫn đúng, cách ghi sổ nằm ở DB. Artifact sơ đồ trên claude.ai chưa đăng lại bản HTML mới (ma trận sơ đồ 9).
