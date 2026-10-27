# ADR-0007: Tách "cần nhắc gì" (`reminders`) khỏi "đã gửi qua kênh nào" (`message_deliveries`)

- **Trạng thái:** Chấp nhận
- **Ngày:** 2026-10-21
- **Giai đoạn:** MVP

## Bối cảnh
Shop nhắc khách: xác nhận lịch, nhắc trước 24h/2h, tiêm phòng đến hạn, tái khám, báo đón sau spa. Kênh chính là Zalo ZNS (rẻ, khách quen dùng), SMS dự phòng khi Zalo lỗi hoặc số không dùng Zalo. OTP gửi SMS. Gửi tin tốn tiền, nên gửi trùng là mất tiền và làm phiền khách. Scheduler và worker có thể chạy trùng, job có thể retry.

## Quyết định
- `reminders`: một dòng cho mỗi việc cần nhắc (`purpose`, `send_at`, `status pending → processing → sent | failed | skipped`), `UNIQUE(tenant_id, dedupe_key)` (vd `vax:{pet_vaccination_id}:{offset}`). Lịch bị hủy/dời: nhắc cũ `skipped`, tạo nhắc mới.
- `message_deliveries`: mỗi lần gọi nhà cung cấp một dòng (`channel zalo|sms`, `status`, `provider_message_id`, `cost`, `error`).
- Thứ tự gửi: Zalo trước; lỗi hoặc không có Zalo thì SMS; không bao giờ gửi cả hai cho một nhắc.
- Scheduler mỗi phút claim nguyên tử (`UPDATE … SET status='processing' WHERE status='pending'` hoặc `FOR UPDATE SKIP LOCKED`) rồi dispatch job; job mang `tenant_id` (`TenantAware`).
- Gateway sau interface (`ZnsGateway`, `SmsGateway`) để fake trong test và đổi nhà cung cấp.
- Shop `suspended` không gửi nhắc khách.

## Phương án đã cân nhắc
| Phương án | Ưu | Nhược | Lý do loại |
|---|---|---|---|
| Một bảng `notifications_sent` | Ít bảng | Không phân biệt "đã quyết định nhắc" với "đã gửi thử 2 kênh"; khó dedupe | Mất khả năng đối soát chi phí và lý do lỗi |
| Laravel Notification + channel, không bảng riêng | Có sẵn | Không có trạng thái "đang xử lý", không dedupe xuyên lần chạy | Không chống gửi trùng |
| Gửi Zalo và SMS song song | Chắc khách nhận | Tốn gấp đôi, làm phiền | Trái nghiệp vụ |

## Hệ quả
- Gửi idempotent: chạy scheduler 2 lần, 2 worker cùng claim, job retry đều không gửi trùng.
- Đối soát được chi phí tin nhắn theo shop, theo kênh.
- Thêm một bước trạng thái `processing`: cần job dọn nhắc kẹt `processing` quá lâu (worker chết giữa chừng) đưa về `pending` hoặc `failed`.

## Kiểm chứng
- Test: chạy scheduler 2 lần không gửi 2 lần; 2 worker claim cùng lúc không trùng.
- Test: Zalo lỗi → đúng 1 SMS; Zalo thành công → không SMS; lịch 2 pet → 1 tin.
- `Http::fake` cho ZNS/SMS.

## Liên quan
`design/db-schema.md` mục 2.8 · plan task 6.1.
