# Sơ đồ đặt lịch đồng thời (chống vượt hạn mức online)

Minh họa cho `design/db-schema.md` mục 5.1 và `design/adr/0002-booking-capacity-lock.md`. Action `BookAppointment` dùng chung cho Site và lễ tân. Bản xem trực quan: `design/so-do-dat-lich.html`.

> **Lưu ý:** sơ đồ 1 và 4 vẽ theo **2 đề xuất trong ADR-0002 đang chờ xác nhận**: (a) transaction chạy `READ COMMITTED`, (b) kiểm `idempotency_key` sau khi khóa `customers`. Lý do ở sơ đồ 3.

---

## 1. Hai khách cùng đặt vào khung còn 1 chỗ

Khung 09:00 nhóm khám ở CS1 có 3 bác sĩ trực, tỉ lệ online 1/3, nên hạn mức online = floor(3 × 1/3) = 1 chỗ. Hiện chưa có lịch nào.

```mermaid
sequenceDiagram
  autonumber
  participant A as Request A (khách An)
  participant B as Request B (khách Bình)
  participant DB as MySQL
  Note over A,DB: Khung 09:00 · khám · CS1 · hạn mức online 1 · đang chiếm 0
  par Trước transaction (autocommit)
    A->>DB: INSERT IGNORE booking_capacity_locks (t1, CS1, medical, 05/11)
    DB-->>A: 1 row (tạo hàng)
  and
    B->>DB: INSERT IGNORE cùng hàng
    DB-->>B: 0 row (đã có, bỏ qua)
  end
  A->>DB: BEGIN (READ COMMITTED)
  B->>DB: BEGIN (READ COMMITTED)
  A->>DB: SELECT customers An FOR UPDATE
  B->>DB: SELECT customers Bình FOR UPDATE
  Note over A,B: Khác khách nên không chờ nhau
  A->>DB: idempotency_key đã có? Chưa
  B->>DB: idempotency_key đã có? Chưa
  A->>DB: Kiểm pet của khách, dịch vụ ở cơ sở, giờ mở cửa
  B->>DB: Kiểm pet của khách, dịch vụ ở cơ sở, giờ mở cửa
  A->>DB: SELECT booking_capacity_locks (CS1, medical, 05/11) FOR UPDATE
  DB-->>A: Cấp khóa X
  B->>DB: SELECT booking_capacity_locks (CS1, medical, 05/11) FOR UPDATE
  Note right of B: Chờ A nhả khóa
  A->>DB: Đếm dòng online chồng giờ nhóm khám
  DB-->>A: 0
  Note over A: 0 + 1 dòng mới ≤ 1 → còn chỗ
  A->>DB: Pet của An có dòng khác chồng giờ? Không
  A->>DB: UPDATE tenant_usage SET count = count + 1 WHERE count < quota
  A->>DB: INSERT appointments, appointment_items, appointment_changes
  A->>DB: COMMIT
  DB-->>B: Cấp khóa X
  B->>DB: Đếm dòng online chồng giờ nhóm khám
  DB-->>B: 1 (thấy dòng A vừa commit)
  Note over B: 1 + 1 > 1 → hết chỗ
  B->>DB: ROLLBACK
  B-->>B: SlotTakenException → 409 + 3 khung gần nhất còn chỗ
  A-->>A: Sau commit: AppointmentBooked → tạo reminders
```

Điểm cần thấy:
- Chỉ **một hàng** bị khóa cho cả ngày × nhóm × cơ sở. Không khóa dải `appointment_items`, nên không có gap lock.
- Hai khách khác nhau chỉ chờ nhau ở hàng khóa hạn mức. Cùng khách thì chờ nhau từ hàng `customers` (chặn 1 pet bị đặt 2 lịch chồng giờ ở 2 nhóm khác nhau).
- `tenant_usage` là hàng nóng của cả shop nên khóa **cuối cùng**, giữ ngắn nhất.
- Lễ tân đặt (`source=staff`) đi cùng đường nhưng không bị chặn ở bước đếm; vượt số người trực thì chỉ trả cờ cảnh báo.

---

## 2. Vì sao không `INSERT IGNORE` hàng khóa trong transaction

Hàng khóa đã tồn tại từ trước. Nếu `INSERT IGNORE` nằm trong transaction, bản ghi trùng làm InnoDB đặt khóa **S** lên hàng đó.

```mermaid
sequenceDiagram
  participant A as Transaction A
  participant B as Transaction B
  participant DB as MySQL
  A->>DB: BEGIN · INSERT IGNORE hàng khóa (trùng)
  DB-->>A: Bỏ qua, nhưng A giữ khóa S
  B->>DB: BEGIN · INSERT IGNORE hàng khóa (trùng)
  DB-->>B: Bỏ qua, B cũng giữ khóa S
  A->>DB: SELECT … FOR UPDATE (xin nâng lên X)
  Note right of A: Chờ B nhả S
  B->>DB: SELECT … FOR UPDATE (xin nâng lên X)
  Note right of B: Chờ A nhả S
  DB-->>B: Deadlock: hủy B
  Note over A,B: Với 20 tiến trình, gần như mọi lần chạy đều gặp
```

Cách chặn: `INSERT IGNORE` chạy **autocommit trước** transaction. Khóa S nhả ngay khi câu lệnh xong. Trong transaction chỉ còn `SELECT … FOR UPDATE`.

---

## 3. Lỗ hổng nếu giữ REPEATABLE READ (lý do của đề xuất a)

InnoDB ở REPEATABLE READ (mặc định) chụp snapshot ở **câu đọc thường đầu tiên** trong transaction, không phải lúc lấy khóa. `SELECT … FOR UPDATE` đọc bản mới nhất, nhưng câu `COUNT` đếm chỗ là đọc thường, nên vẫn đọc theo snapshot cũ.

```mermaid
sequenceDiagram
  participant A as Request A
  participant B as Request B
  participant DB as MySQL (REPEATABLE READ)
  A->>DB: BEGIN · đọc services, idempotency (đọc thường)
  B->>DB: BEGIN · đọc services, idempotency (đọc thường)
  Note right of B: Snapshot của B chụp ở đây (T0)
  A->>DB: SELECT hàng khóa FOR UPDATE · cấp X
  B->>DB: SELECT hàng khóa FOR UPDATE
  Note right of B: Chờ A
  A->>DB: COUNT = 0 · INSERT dòng · COMMIT
  DB-->>B: Cấp X
  B->>DB: COUNT dòng chồng giờ (đọc thường, theo snapshot T0)
  DB-->>B: 0 (không thấy dòng A)
  Note over B: 0 + 1 ≤ 1 → lưu
  B->>DB: INSERT · COMMIT
  Note over A,B: 2 lịch trong khung chỉ có 1 chỗ: vượt hạn mức
```

Mục 5.1 hiện tại có bước 1 (kiểm idempotency) và bước 2 (kiểm dịch vụ, giờ mở cửa) đều là đọc thường, nằm **trước** bước khóa 3. Nên lỗi này xảy ra thật. Test 20 tiến trình có thể bắt được, nhưng chập chờn tùy thời điểm.

Cách chặn (đề xuất a trong ADR-0002): đặt `isolation_level = READ COMMITTED` cho kết nối MySQL của app (Laravel `config/database.php`, khóa `isolation_level`). Ở READ COMMITTED mỗi câu đọc thường lấy snapshot mới, nên câu đếm chạy sau khi có khóa luôn thấy dữ liệu đã commit. Đặt ở mức kết nối, không dùng `SET TRANSACTION` trước `DB::transaction()`, vì `SET TRANSACTION` chỉ áp cho **một** transaction kế tiếp, lần retry do deadlock sẽ quay lại REPEATABLE READ.

---

## 4. Thứ tự các bước và chỗ khóa

```mermaid
flowchart TD
  S([Request đặt / dời / thêm dòng]) --> P0["Bước 0: INSERT IGNORE hàng booking_capacity_locks<br/>mọi (ngày, nhóm) liên quan · autocommit"]
  P0 --> T{{"BEGIN · READ COMMITTED"}}
  T --> L1["Khóa 1: customers FOR UPDATE"]
  L1 --> I{"idempotency_key<br/>đã có?"}
  I -- có --> R1([Trả lịch cũ])
  I -- chưa --> V["Kiểm: pet của khách · dịch vụ có ở cơ sở<br/>cơ sở mở cửa · giờ trong khung trực"]
  V --> L2["Khóa 2: booking_capacity_locks FOR UPDATE<br/>(ngày, nhóm) tăng dần · medical trước grooming"]
  L2 --> C{"Nguồn online:<br/>chiếm + dòng mới > hạn mức<br/>ở khung nào?"}
  C -- vượt --> E1([SlotTakenException · 409 + gợi ý giờ])
  C -- đủ chỗ / lễ tân --> PET{"Pet có dòng khác<br/>chồng giờ?"}
  PET -- có --> E2([Lỗi trùng lịch pet])
  PET -- không --> L3["Khóa 3: tenant_usage<br/>UPDATE … WHERE count < quota<br/>(bỏ qua khi dời lịch)"]
  L3 --> Q{"affected = 0?"}
  Q -- có --> E3([QuotaExceededException])
  Q -- không --> W["INSERT appointments, appointment_items,<br/>appointment_changes"]
  W --> CM{{COMMIT}}
  CM --> EV([AppointmentBooked afterCommit → reminders])
  CM -. deadlock / lock wait timeout .-> RT["Retry tối đa 3 lần<br/>(DB::transaction attempts: 3)"]
  RT -.-> T
```

Dời lịch sang ngày hoặc nhóm khác: bước 0 tạo hàng cho cả (ngày, nhóm) cũ và mới, bước khóa 2 khóa tất cả theo (ngày, nhóm) tăng dần, nên 2 lần dời chéo ngày đồng thời không deadlock.
