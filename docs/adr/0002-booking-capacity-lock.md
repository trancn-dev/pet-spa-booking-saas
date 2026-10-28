# ADR-0002: Hạn mức đặt online bằng khóa một hàng đếm theo (cơ sở, nhóm, ngày)

- **Trạng thái:** Chấp nhận, kể cả (a), (b) (2026-10-28, task 5.5; `db-schema.md` mục 5.1 đã cập nhật).
- **Ngày:** 2026-10-21
- **Giai đoạn:** MVP

## Bối cảnh
Khách đặt lịch online không chọn người làm. Shop chỉ cho đặt online một phần năng lực: hạn mức mỗi khung 15 phút = `floor(số người trực của nhóm × tỉ lệ online)`, tính riêng theo cơ sở × nhóm (`medical`, `grooming`). Một lịch có thể nhiều pet, mỗi dòng `appointment_items` chiếm một chỗ; lịch khám + spa phải đủ chỗ ở cả hai nhóm, thiếu thì từ chối cả lịch.

Hai khách cùng thấy "còn 1 chỗ" rồi cùng bấm đặt là race condition kinh điển. MySQL không có exclusion constraint. Hạn mức lại thay đổi theo lịch trực, ngày nghỉ, tỉ lệ, nên không có con số cố định để đặt UNIQUE.

## Quyết định
Action `BookAppointment` (dùng chung Site và lễ tân; dời lịch, thêm dòng, khôi phục lịch đi cùng đường):

0. **Trước transaction, autocommit:** `INSERT IGNORE booking_capacity_locks(tenant_id, branch_id, service_group, date)` cho mọi (ngày, nhóm) liên quan. Bảng này không chứa dữ liệu nghiệp vụ, chỉ là hàng để khóa.
1. Trong transaction: `SELECT … FOR UPDATE` hàng `customers` của khách (tuần tự hóa lịch cùng khách).
2. Kiểm `idempotency_key`; kiểm pet thuộc khách, dịch vụ có ở cơ sở, giờ mở cửa, khung trực.
3. `SELECT … FOR UPDATE` các hàng `booking_capacity_locks` theo (ngày, nhóm) tăng dần, `medical` trước `grooming`.
4. Online: đếm dòng online đang chiếm chồng giờ + dòng mới > hạn mức ở bất kỳ khung nào → `SlotTakenException` (409 + 3 khung gần nhất còn chỗ). Lễ tân: không chặn, chỉ trả cờ cảnh báo khi vượt số người trực.
5. Kiểm từng pet không có dòng lịch khác chồng giờ.
6. Quota gói, khóa cuối: `UPDATE tenant_usage … WHERE appointments_count < ?`; affected = 0 → từ chối.
7. Ghi lịch, dòng, lịch sử; `AppointmentBooked` sau commit.

Deadlock hoặc lock wait timeout: retry tối đa 3 lần (`DB::transaction(..., attempts: 3)`).

**(a) Kết nối MySQL chạy `READ COMMITTED`** (`config/database.php` → `'isolation_level' => 'READ COMMITTED'`).
Ở REPEATABLE READ, InnoDB chụp snapshot ở câu đọc thường đầu tiên của transaction. Bước 2 có đọc thường *trước* khi lấy khóa ở bước 3, nên request chờ khóa vẫn đếm theo snapshot cũ, không thấy dòng request trước vừa commit → vượt hạn mức (sơ đồ 3 trong `design/so-do-dat-lich.md`). READ COMMITTED cho mỗi câu đọc một snapshot mới, nên câu đếm sau khi có khóa luôn thấy dữ liệu đã commit. Đặt ở mức kết nối vì `SET TRANSACTION ISOLATION LEVEL` chỉ áp cho một transaction kế tiếp: lần retry của `DB::transaction` sẽ quay về REPEATABLE READ. Đặt cho cả app cũng giảm gap lock ở chỗ khác (hóa đơn, kho). MySQL 8 mặc định `binlog_format = ROW` nên READ COMMITTED an toàn cho replication.

**(b) Kiểm `idempotency_key` sau khi khóa `customers`** (đảo bước 1 và 2 hiện tại của `db-schema.md` 5.1).
Hai request trùng key luôn cùng khách, nên sau khóa khách chúng chạy tuần tự; request thứ hai thấy lịch của request đầu và trả lại lịch cũ, thay vì đâm vào `UNIQUE(tenant_id, idempotency_key)` rồi trả 500.

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
- Thứ tự khóa toàn cục: `customers` → `booking_capacity_locks` ((ngày, nhóm) tăng dần) → `tenant_usage`. Code mới phải theo đúng thứ tự này.
- `tenant_usage` là hàng nóng của cả shop, khóa cuối để giữ ngắn nhất.
- Bảng `booking_capacity_locks` tăng một hàng mỗi (cơ sở, nhóm, ngày có lịch). Không cần dọn.
- Theo (a): mọi transaction của app chạy READ COMMITTED; code không được dựa vào việc đọc lại cùng dữ liệu trong một transaction ra cùng kết quả (cần thì khóa hàng).

## Kiểm chứng
Test `->group('mysql')`, 20 tiến trình thật (Symfony Process, mỗi tiến trình một kết nối):
- 20 request cùng đặt online vào khung còn 1 chỗ → đúng 1 lịch, 19 `SlotTakenException`.
- Lịch 2 pet vào khung còn 1 chỗ → từ chối, không lưu dòng nào.
- Ngày chưa có hàng khóa, 20 request cùng lúc → không deadlock.
- Lịch khám + spa và lịch spa + khám song song → không deadlock.
- Cùng idempotency key 10 lần → 1 lịch, 9 lần trả lịch cũ (không lỗi 500).
- Quota còn 1 suất, 10 request → 1.
- Cùng khách, cùng pet, 2 lịch lễ tân khác nhóm chồng giờ → 1 lịch.
- Chạy lặp 10 lần liền trong CI đều xanh.

**Vì sao không gap lock:** bước 3 khóa theo **đẳng thức trên toàn bộ khóa chính** của một hàng **đã tồn tại** (bước 0 bảo đảm có hàng). Với index unique, tìm đúng một bản ghi có sẵn thì InnoDB chỉ đặt record lock, không khóa khoảng trống, ở cả REPEATABLE READ; ở READ COMMITTED thì phép tìm không bao giờ khóa gap. Câu đếm dòng đang chiếm là đọc thường (không khóa), nên không có next-key lock trên `appointment_items`. `tenant_usage` cũng vậy: upsert rồi `UPDATE` theo khóa chính.

Số liệu thật (task 5.5, 2026-10-28, Docker WSL2, MySQL 8, `CONCURRENCY_REPORT=1`): suite `tests/Concurrency` chạy **10 lần liền, 70/70 test xanh**, không lần nào có lỗi ngoài dự kiến (deadlock không vượt quá retry, không lock wait timeout). Thời gian mỗi lần đặt (từ lúc qua barrier tới khi xong, gồm chờ khóa):

| Kịch bản | Kết quả mỗi lần | TB | Chậm nhất |
|---|---|---|---|
| 20 lịch online, khung còn 1 chỗ | 1 lịch, 19 SlotTaken | 422 ms | 1 524 ms |
| 20 lịch online, ngày chưa có hàng khóa (khám + spa) | 1 lịch, 19 SlotTaken | (cùng dòng trên) | |
| 10 lịch 2 pet vào khung 1 chỗ | 10 SlotTaken, 0 dòng | 178 ms | 314 ms |
| Quota còn 1, 10 request | 1 lịch, 9 hết quota | 155 ms | 341 ms |
| Cùng idempotency key × 10 | 1 lịch, 9 trả lịch cũ | 79 ms | 111 ms |
| 10 khám+spa đua 10 spa+khám (lễ tân) | 20 lịch, không deadlock lọt ra | 545 ms | 1 500 ms |
| Cùng pet, 2 lịch lễ tân chồng giờ | 1 lịch, 1 từ chối | 75 ms | 120 ms |

Số deadlock InnoDB đã retry: không đọc được (user DB không có quyền `information_schema.INNODB_METRICS`); trên CI (user root) dòng `[race]` in số này. Thời gian là trên máy dev, 20 tiến trình cùng tranh CPU; đo p95 thật ở task 9.2.

## Liên quan
`design/db-schema.md` mục 2.5, 5.1 · `design/so-do-dat-lich.md` · `analysis/quyet-dinh-ra-soat.md` điểm 1, 2, 3, 8.
