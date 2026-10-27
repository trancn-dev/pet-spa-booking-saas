# Sơ đồ đặt lịch đồng thời (chống vượt hạn mức online)

Minh họa cho `docs/database/db-schema.md` mục 5.1 và `docs/adr/0002-booking-capacity-lock.md`. Action `BookAppointment` dùng chung cho Site và lễ tân. Bản xem trực quan: `docs/design/so-do-dat-lich.html`.

> **Đã chốt (27/10/2026):** kết nối MySQL chạy `READ COMMITTED` (ADR-0002 a); kiểm `idempotency_key` sau khi khóa `customers` và khóa hạn mức (ADR-0002 b); chỗ online còn = `min(hạn mức online − dòng online đang chiếm, người trực − tổng dòng đang chiếm)`; pet vừa khám vừa spa trong một lịch chạy nối tiếp (mặc định khám trước). Nguyên tắc: **khóa trước, đọc sau**.

---

## 1. Hai khách cùng đặt vào khung còn 1 chỗ

Khung 09:00 nhóm khám ở CS1 có 3 bác sĩ trực, tỉ lệ online 1/3, nên hạn mức online = floor(3 × 1/3) = 1 chỗ. Hiện chưa có lịch nào.

```mermaid
sequenceDiagram
  autonumber
  participant A as Request A (khách An)
  participant B as Request B (khách Bình)
  participant DB as MySQL (READ COMMITTED)
  Note over A,DB: Khung 09:00 · khám · CS1 · hạn mức online 1 · người trực 3 · đang chiếm 0
  par Bước 0, ngoài transaction (autocommit)
    A->>DB: INSERT booking_capacity_locks (t1, CS1, medical, 05/11) ON DUPLICATE KEY UPDATE tenant_id = tenant_id
    DB-->>A: tạo hàng
  and
    B->>DB: INSERT … ON DUPLICATE KEY UPDATE (cùng hàng)
    DB-->>B: đã có, không đổi gì
  end
  A->>DB: BEGIN
  B->>DB: BEGIN
  A->>DB: SELECT customers An FOR UPDATE
  B->>DB: SELECT customers Bình FOR UPDATE
  Note over A,B: Khác khách nên không chờ nhau
  A->>DB: SELECT booking_capacity_locks (CS1, medical, 05/11) FOR UPDATE
  DB-->>A: Cấp khóa X (đếm: 1 hàng = đủ)
  B->>DB: SELECT booking_capacity_locks (CS1, medical, 05/11) FOR UPDATE
  Note right of B: Chờ A nhả khóa
  A->>DB: idempotency_key đã có? Chưa
  A->>DB: Kiểm pet của khách, dịch vụ ở cơ sở, giờ mở cửa, giới hạn đặt online
  A->>DB: Đếm dòng chồng khung: online 0, tất cả 0
  Note over A: min(1 − 0, 3 − 0) = 1 ≥ 1 dòng mới → còn chỗ
  A->>DB: Pet của An có dòng khác chồng giờ? Không
  A->>DB: UPDATE tenant_usage SET count = count + 1 WHERE count < quota
  A->>DB: INSERT appointments, appointment_items (source=online), appointment_changes
  A->>DB: COMMIT
  DB-->>B: Cấp khóa X
  B->>DB: idempotency_key đã có? Chưa
  B->>DB: Đếm dòng chồng khung (snapshot mới, thấy dòng A)
  DB-->>B: online 1, tất cả 1
  Note over B: min(1 − 1, 3 − 1) = 0 < 1 → hết chỗ
  B->>DB: ROLLBACK
  B-->>B: SlotTakenException → 409 + 3 khung gần nhất còn chỗ
  A-->>A: Sau commit: AppointmentBooked → tạo reminders
```

Điểm cần thấy:
- Chỉ **một hàng** bị khóa cho cả ngày × nhóm × cơ sở. Không khóa dải `appointment_items`, nên không có gap lock.
- Mọi câu đọc dùng để quyết định (idempotency, đếm chỗ, chồng giờ pet) chạy **sau** khi đã giữ khóa.
- Hai khách khác nhau chỉ chờ nhau ở hàng khóa hạn mức. Cùng khách thì chờ nhau từ hàng `customers` (chặn 1 pet bị đặt 2 lịch chồng giờ ở 2 nhóm khác nhau, và chặn 2 request trùng `idempotency_key`).
- `tenant_usage` là hàng nóng của cả shop nên khóa **cuối cùng**, giữ ngắn nhất.
- Lễ tân đặt (`source=staff`) đi cùng đường nhưng không bị chặn ở bước đếm; vượt số người trực thì chỉ trả cờ cảnh báo. Dòng lễ tân vẫn làm giảm chỗ online qua vế `người trực − tổng dòng`.

---

## 2. Vì sao tạo hàng khóa ngoài transaction, và không dùng `INSERT IGNORE`

Nếu câu tạo hàng nằm **trong** transaction, bản ghi trùng làm InnoDB đặt khóa **S** lên hàng đó.

```mermaid
sequenceDiagram
  participant A as Transaction A
  participant B as Transaction B
  participant DB as MySQL
  A->>DB: BEGIN · INSERT hàng khóa (trùng)
  DB-->>A: Bỏ qua, nhưng A giữ khóa S
  B->>DB: BEGIN · INSERT hàng khóa (trùng)
  DB-->>B: Bỏ qua, B cũng giữ khóa S
  A->>DB: SELECT … FOR UPDATE (xin nâng lên X)
  Note right of A: Chờ B nhả S
  B->>DB: SELECT … FOR UPDATE (xin nâng lên X)
  Note right of B: Chờ A nhả S
  DB-->>B: Deadlock: hủy B
  Note over A,B: Với 20 tiến trình, gần như mọi lần chạy đều gặp
```

Cách chặn:
- Bước 0 chạy **autocommit trước** `DB::transaction`; code assert `DB::transactionLevel() === 0` để không ai gọi lồng trong transaction khác. Khóa của câu INSERT nhả ngay khi câu lệnh xong. Trong transaction chỉ còn `SELECT … FOR UPDATE`.
- Dùng `INSERT … ON DUPLICATE KEY UPDATE tenant_id = tenant_id`, không dùng `INSERT IGNORE`: `INSERT IGNORE` biến cả lỗi FK (branch của shop khác) thành cảnh báo, câu lệnh "thành công" mà không có hàng.
- Sau `SELECT … FOR UPDATE`, **đếm số hàng khóa được**; thiếu (hàng bị xóa, sai tham số) thì ném exception thay vì chạy tiếp mà không có khóa.

---

## 3. Lỗ hổng nếu giữ REPEATABLE READ (lý do chọn READ COMMITTED)

InnoDB ở REPEATABLE READ (mặc định) chụp snapshot ở **câu đọc thường đầu tiên** trong transaction, không phải lúc lấy khóa. `SELECT … FOR UPDATE` đọc bản mới nhất, nhưng câu `COUNT` đếm chỗ là đọc thường, nên vẫn đọc theo snapshot cũ.

```mermaid
sequenceDiagram
  participant A as Request A
  participant B as Request B
  participant DB as MySQL (REPEATABLE READ)
  A->>DB: BEGIN · đọc services (đọc thường)
  B->>DB: BEGIN · đọc services (đọc thường)
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

Chỉ cần một câu đọc thường bất kỳ chạy trước khi lấy khóa (model binding, kiểm quyền, middleware, hay chính code của Action) là lỗi xảy ra. Sắp xếp "khóa trước, đọc sau" giảm rủi ro nhưng không đủ, vì không kiểm soát được mọi câu đọc trước đó trong cùng transaction.

Cách chặn: `isolation_level = READ COMMITTED` ở kết nối MySQL của app (`config/database.php`). Ở READ COMMITTED mỗi câu đọc thường lấy snapshot mới, nên câu đếm chạy sau khi có khóa luôn thấy dữ liệu đã commit. Đặt ở mức kết nối, không dùng `SET TRANSACTION` trước `DB::transaction()`, vì `SET TRANSACTION` chỉ áp cho **một** transaction kế tiếp, lần retry do deadlock sẽ quay lại REPEATABLE READ. Server cần `binlog_format=ROW` (mặc định MySQL 8.4).

Test giữ lâu dài: chạy đúng kịch bản trên bằng 2 kết nối, một kết nối RR (test chứng minh vượt hạn mức) và một kết nối RC (đúng hạn mức).

---

## 4. Thứ tự các bước và chỗ khóa

```mermaid
flowchart TD
  S([Request đặt / dời / thêm dòng]) --> A0{"DB::transactionLevel() === 0?"}
  A0 -- không --> E0([LogicException: không gọi lồng])
  A0 -- có --> P0["Bước 0: INSERT … ON DUPLICATE KEY UPDATE<br/>hàng booking_capacity_locks mọi (ngày, nhóm) · autocommit"]
  P0 --> T{{"BEGIN · READ COMMITTED"}}
  T --> L1["Khóa 1: customers FOR UPDATE"]
  L1 --> L2["Khóa 2: booking_capacity_locks FOR UPDATE<br/>(ngày, nhóm) tăng dần · medical trước grooming"]
  L2 --> N{"Số hàng khóa được<br/>= số (ngày, nhóm)?"}
  N -- thiếu --> E4([Exception: thiếu hàng khóa])
  N -- đủ --> I{"idempotency_key<br/>đã có?"}
  I -- "có, đúng khách" --> R1([Trả lịch cũ])
  I -- "có, khách khác" --> R2([422])
  I -- chưa --> V["Kiểm: pet của khách · dịch vụ có ở cơ sở<br/>cơ sở mở cửa · giờ trong khung trực<br/>giới hạn đặt online · xếp dòng nối tiếp"]
  V --> C{"Nguồn online:<br/>dòng mới > min(hạn mức online − online chiếm,<br/>người trực − tổng chiếm) ở khung nào?"}
  C -- vượt --> E1([SlotTakenException · 409 + gợi ý giờ])
  C -- đủ chỗ / lễ tân --> PET{"Pet có dòng khác<br/>chồng giờ?"}
  PET -- có --> E2([Lỗi trùng lịch pet])
  PET -- không --> L3["Khóa 3: tenant_usage<br/>UPDATE … WHERE count < quota<br/>(bỏ qua khi dời lịch)"]
  L3 --> Q{"affected = 0?"}
  Q -- có --> E3([QuotaExceededException])
  Q -- không --> W["INSERT appointments, appointment_items (chép source),<br/>appointment_changes"]
  W --> CM{{COMMIT}}
  W -. "duplicate idempotency_key (lưới an toàn)" .-> I
  CM --> EV([AppointmentBooked afterCommit → reminders])
  CM -. deadlock / lock wait timeout .-> RT["Retry tối đa 3 lần<br/>(DB::transaction(fn, 3))"]
  RT -.-> T
```

Dời lịch sang ngày hoặc nhóm khác: bước 0 tạo hàng cho cả (ngày, nhóm) cũ và mới, bước khóa 2 khóa tất cả theo (ngày, nhóm) tăng dần, nên 2 lần dời chéo ngày đồng thời không deadlock.

Pet vừa khám vừa spa trong một lịch: hai dòng xếp nối tiếp (mặc định khám trước, spa bắt đầu khi khám kết thúc), mỗi dòng có `starts_at` riêng và được đếm ở khung của chính nó. Nếu hai dòng rơi vào 2 ngày khác nhau (khám sát nửa đêm) thì bước 0 và khóa 2 gồm cả hai ngày.
