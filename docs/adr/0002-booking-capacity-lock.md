# ADR-0002: Hạn mức đặt online bằng khóa một hàng đếm theo (cơ sở, nhóm, ngày)

- **Trạng thái:** Chấp nhận (cả (a), (b) và công thức chỗ online, chốt 2026-10-27).
- **Ngày:** 2026-10-21 · cập nhật 2026-10-27
- **Giai đoạn:** MVP

## Bối cảnh
Khách đặt lịch online không chọn người làm. Shop chỉ cho đặt online một phần năng lực: hạn mức mỗi khung 15 phút = `floor(số người trực của nhóm × tỉ lệ online)`, tính riêng theo cơ sở × nhóm (`medical`, `grooming`). Một lịch có thể nhiều pet, mỗi dòng `appointment_items` chiếm một chỗ; lịch khám + spa phải đủ chỗ ở cả hai nhóm, thiếu thì từ chối cả lịch.

Hai khách cùng thấy "còn 1 chỗ" rồi cùng bấm đặt là race condition kinh điển. MySQL không có exclusion constraint. Hạn mức lại thay đổi theo lịch trực, ngày nghỉ, tỉ lệ, nên không có con số cố định để đặt UNIQUE.

## Quyết định
Action `BookAppointment` (dùng chung Site và lễ tân; dời lịch, thêm dòng, khôi phục lịch đi cùng đường). Nguyên tắc: **khóa trước, đọc sau**: mọi câu đọc dùng để quyết định chạy sau khi đã giữ khóa.

0. **Ngoài transaction, autocommit:** assert `DB::transactionLevel() === 0`. Tạo hàng khóa cho mọi (ngày, nhóm) liên quan bằng `INSERT INTO booking_capacity_locks (tenant_id, branch_id, service_group, date) VALUES … ON DUPLICATE KEY UPDATE tenant_id = tenant_id`. Không dùng `INSERT IGNORE` (nuốt cả lỗi FK). Bảng này không chứa dữ liệu nghiệp vụ, chỉ là hàng để khóa.
1. Trong `DB::transaction(fn, 3)`: `SELECT … FOR UPDATE` hàng `customers` của khách (tuần tự hóa lịch cùng khách).
2. `SELECT … FOR UPDATE` các hàng `booking_capacity_locks` theo (ngày, nhóm) tăng dần, `medical` trước `grooming`. **Đếm số hàng khóa được**; thiếu → exception.
3. Idempotency: lịch theo `idempotency_key` có và đúng khách đang khóa → trả lịch cũ; có nhưng của khách khác → 422.
4. Kiểm pet thuộc khách, dịch vụ có ở cơ sở, giờ mở cửa, khung trực, giới hạn đặt online. Pet vừa khám vừa spa: các dòng xếp nối tiếp (mặc định khám trước), mỗi dòng `starts_at` riêng.
5. Đếm trên `appointment_items` (có cột `source` chép từ lịch, không JOIN): **chỗ online còn = min(hạn mức online − dòng online đang chiếm, số người trực − tổng dòng đang chiếm của mọi nguồn)**, hạn mức online = `floor(người trực × ratio)`. Online: dòng mới của nhóm > chỗ online còn ở bất kỳ khung nào → `SlotTakenException` (409 + 3 khung gần nhất còn chỗ). Lễ tân: không chặn, chỉ trả cờ cảnh báo khi vượt số người trực.
6. Kiểm từng pet không có dòng lịch khác chồng giờ.
7. Quota gói, khóa cuối: `UPDATE tenant_usage … WHERE appointments_count < ?`; affected = 0 → từ chối.
8. Ghi lịch, dòng (chép `source`), lịch sử; `AppointmentBooked` sau commit.

Lưới an toàn: INSERT lịch đụng `UNIQUE(tenant_id, idempotency_key)` → bắt `UniqueConstraintViolationException`, đọc lại lịch theo key, áp quy tắc bước 3; không để lỗi 500.

Deadlock hoặc lock wait timeout: retry tối đa 3 lần (`DB::transaction(fn, 3)`); bước 0 nằm ngoài vòng retry.

**(a) Chấp nhận: kết nối MySQL chạy `READ COMMITTED`** (`config/database.php` → `'isolation_level' => 'READ COMMITTED'`).
Ở REPEATABLE READ, InnoDB chụp snapshot ở câu đọc thường đầu tiên của transaction. Chỉ cần một câu đọc thường trước khi lấy khóa (kiểm dịch vụ, model binding, middleware), request chờ khóa vẫn đếm theo snapshot cũ, không thấy dòng request trước vừa commit → vượt hạn mức (sơ đồ 3 trong `docs/design/so-do-dat-lich.md`). READ COMMITTED cho mỗi câu đọc một snapshot mới, nên câu đếm sau khi có khóa luôn thấy dữ liệu đã commit. Vẫn giữ thứ tự "khóa trước, đọc sau" như lớp phòng thứ hai. Đặt ở mức kết nối vì `SET TRANSACTION ISOLATION LEVEL` chỉ áp cho một transaction kế tiếp: lần retry của `DB::transaction` sẽ quay về REPEATABLE READ. Đặt cho cả app cũng giảm gap lock ở chỗ khác (hóa đơn, kho).
Điều kiện deploy: server MySQL chạy `binlog_format=ROW` (mặc định MySQL 8.4); READ COMMITTED không an toàn với binlog STATEMENT.

**(b) Chấp nhận: kiểm `idempotency_key` sau khi khóa `customers`** (và sau khóa hạn mức, bước 3).
Hai request trùng key luôn cùng khách, nên sau khóa khách chúng chạy tuần tự; request thứ hai thấy lịch của request đầu và trả lại lịch cũ, thay vì đâm vào `UNIQUE(tenant_id, idempotency_key)` rồi trả 500. Lịch tìm theo key phải đúng khách đang khóa: key trùng của khách khác (client sinh trùng hoặc cố dò) → 422, không trả lịch của người khác. Bắt duplicate key khi INSERT là lưới an toàn.

**(c) Chấp nhận: chỗ online tính cả dòng lễ tân** (công thức ở bước 5). Nếu chỉ so dòng online với hạn mức online, lễ tân đặt kín người trực rồi khách online vẫn đặt được, tổng vượt số người trực. Chép `source` xuống `appointment_items` để câu đếm chạy trên một bảng, dùng index `(tenant_id, branch_id, service_group, status, starts_at, ends_at)`.

**(d) Chấp nhận: pet vừa khám vừa spa trong một lịch chạy nối tiếp** (mặc định khám trước). Một pet không thể ở hai nơi cùng lúc; xếp nối tiếp làm bước 6 (kiểm chồng giờ pet) đúng cả trong cùng lịch, và mỗi nhóm chỉ bị chiếm chỗ đúng khung của dòng nhóm đó.

## Phương án đã cân nhắc
| Phương án | Ưu | Nhược | Lý do loại |
|---|---|---|---|
| Khóa hàng `staff` | Tự nhiên khi đặt theo người | Đặt online không gán người; hạn mức theo nhóm, không theo người | Không có hàng nào để khóa |
| Bảng slot sinh sẵn + UNIQUE (một hàng mỗi khung × chỗ) | DB tự chặn | Hạn mức đổi theo lịch trực, nghỉ, tỉ lệ → phải sinh lại slot; lịch dài nhiều khung, nhiều pet → nhiều hàng | Đồng bộ slot với lịch trực phức tạp hơn bài toán gốc |
| Khóa dải `appointment_items` (`SELECT … FOR UPDATE` theo khoảng giờ) | Không cần bảng phụ | REPEATABLE READ sinh next-key/gap lock trên index, deadlock giữa các insert chồng dải | Deadlock dày khi tải cao, khó suy luận |
| `SERIALIZABLE` | Đúng về lý thuyết | Biến mọi đọc thành khóa S, gap lock khắp nơi | Deadlock nhiều nhất trong các phương án |
| `GET_LOCK()` (named lock MySQL) | Không cần bảng | Không gắn với transaction (nhả trước/sau commit tùy code), mất khi rớt kết nối, không qua được proxy/pool | Dễ sai, khó test |
| Redis lock | Nhanh | Không cùng transaction với DB; lock hết hạn giữa chừng là vượt | Thêm một hệ thống phải đúng |
| Optimistic (cột version + retry) | Không chờ khóa | Cần hàng đếm cho từng khung; tải cao thì retry dồn dập | Hàng đếm theo ngày + khóa bi quan đơn giản hơn |

## Hệ quả
- Chỉ khóa **một hàng** mỗi (cơ sở, nhóm, ngày): không gap lock, dễ suy luận, dễ giải thích.
- Mọi lần đặt cùng cơ sở, cùng nhóm, cùng ngày chạy tuần tự. Với pet spa (vài chục tới vài trăm lịch/ngày/cơ sở) và transaction vài mili giây, đây không phải nút cổ chai. Cần đo p95 ở task 9.2.
- Thứ tự khóa toàn cục (đầy đủ ở `CLAUDE.md` và `docs/database/db-schema.md` mục 1): `tenants` → `staff` → `customers` → `booking_capacity_locks` ((ngày, nhóm) tăng dần) → `appointments` → `appointment_items` → … → hàng đếm nóng (`tenant_usage`, `branch_daily_counters`, `tenant_counters`) ở cuối. Code mới phải theo đúng thứ tự này.
- `tenant_usage` là hàng nóng của cả shop, khóa cuối để giữ ngắn nhất.
- Bảng `booking_capacity_locks` tăng một hàng mỗi (cơ sở, nhóm, ngày có lịch). Không cần dọn.
- Mọi transaction của app chạy READ COMMITTED; code không được dựa vào việc đọc lại cùng dữ liệu trong một transaction ra cùng kết quả (cần thì khóa hàng).
- `innodb_lock_wait_timeout` khoảng 5 giây cho request web: chờ khóa lâu thì lỗi sớm, retry, không treo worker.

## Kiểm chứng
Test MySQL 8.4, `->group('mysql')`, trait `DatabaseTruncation` (không `RefreshDatabase`), 20 tiến trình thật (Symfony Process, mỗi tiến trình một kết nối):
- 20 request cùng đặt online vào khung còn 1 chỗ → đúng 1 lịch, 19 `SlotTakenException`.
- Lịch 2 pet vào khung còn 1 chỗ → từ chối, không lưu dòng nào.
- Ngày chưa có hàng khóa, 20 request cùng lúc → không deadlock.
- Lịch khám + spa và lịch spa + khám song song → không deadlock.
- Cùng idempotency key 10 lần → 1 lịch, 9 lần trả lịch cũ (không lỗi 500); key đã dùng bởi khách khác → 422.
- Lễ tân đặt kín người trực → online bị từ chối dù hạn mức online còn.
- **Isolation, 2 kết nối:** kịch bản "đọc thường trước khi khóa" trên kết nối REPEATABLE READ → vượt hạn mức (test đỏ có chủ đích, chứng minh vấn đề); cùng kịch bản trên READ COMMITTED → đúng hạn mức (xanh).
- Quota còn 1 suất, 10 request → 1.
- Cùng khách, cùng pet, 2 lịch lễ tân khác nhóm chồng giờ → 1 lịch.
- Chạy lặp 10 lần liền trong CI đều xanh.

Số liệu thật (điền khi xong task 5.5): số lần chạy, số deadlock được retry, thời gian trung bình mỗi request.

## Liên quan
`docs/database/db-schema.md` mục 1, 2.5, 5.1 · `docs/design/so-do-dat-lich.md` · `docs/analysis/quyet-dinh-ra-soat.md` điểm 1, 2, 3, 8 · `docs/architecture.md` mục Luồng đặt lịch.
