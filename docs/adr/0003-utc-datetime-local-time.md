# ADR-0003: Lưu thời điểm UTC `DATETIME`, giờ ca `TIME` local, ngày nghiệp vụ `DATE` local

- **Trạng thái:** Chấp nhận
- **Ngày:** 2026-10-21
- **Giai đoạn:** MVP

## Bối cảnh
Có ba loại "thời gian" khác nhau trong nghiệp vụ:
1. **Thời điểm** xảy ra một việc: lịch hẹn bắt đầu, hóa đơn hoàn thành, gửi tin nhắc.
2. **Giờ đồng hồ treo tường** lặp lại: ca trực 08:00–17:00 thứ Hai, giờ mở cửa.
3. **Ngày nghiệp vụ**: ngày của hàng đợi, ngày mở hóa đơn, ngày tiêm.

Mỗi shop một múi giờ (`tenants.timezone`, IANA), mọi cơ sở dùng chung. Việt Nam không có DST, nhưng SaaS phải đúng với múi giờ có DST nếu mở rộng; viết đúng từ đầu rẻ hơn sửa sau.

## Quyết định
- App và kết nối MySQL chạy UTC.
- Thời điểm: `DATETIME` UTC (không dùng `TIMESTAMP`).
- Giờ ca, giờ mở cửa: `TIME` local. Đổi sang UTC theo **từng ngày cụ thể** khi tính, không cộng offset cố định.
- Ngày nghiệp vụ: `DATE` local (`visits.visit_date`, `invoices.business_date`, `pet_vaccinations.next_due_on`).
- Chuyển UTC ↔ giờ shop **chỉ ở biên**: form, hiển thị, tính khung trống, giờ gửi nhắc. Một service `ShopClock` lo việc này.
- "Hôm nay" của shop query theo khoảng UTC `[đầu ngày local, đầu ngày kế tiếp)`, không dùng `DATE(starts_at)` (vừa sai múi giờ vừa không dùng được index).
- Việc định kỳ theo giờ shop (nhắc tiêm 08:00, đánh dấu không đến cuối ngày) tính mốc theo giờ local của từng shop, không theo giờ server (cách làm gợi ý: scheduler chạy mỗi giờ, chọn các shop vừa tới mốc).

## Phương án đã cân nhắc
| Phương án | Ưu | Nhược | Lý do loại |
|---|---|---|---|
| `TIMESTAMP` | MySQL tự đổi theo session time zone | Giới hạn năm 2038; đổi ngầm theo session, dễ lệch khi session tz khác | Ngầm định là nguồn bug |
| Lưu giờ local cho mọi thứ | Dễ đọc trong DB | Giờ lặp lại lúc lùi DST là mơ hồ; so sánh, báo cáo chéo shop sai | Không xác định duy nhất một thời điểm |
| Lưu ca bằng UTC hoặc offset cố định | Một kiểu dữ liệu | Ca 08:00 thành 07:00 sau khi đổi DST | Sai nghiệp vụ |

## Hệ quả
- So sánh, sắp xếp thời điểm luôn đúng và dùng được index.
- Mọi chỗ hiển thị phải qua `ShopClock`; quên đổi là hiện giờ UTC. Chặn bằng cast/accessor thống nhất và test.
- Tính khung trống phải dựng interval theo từng ngày (ca gãy, ngày đổi DST).

## Kiểm chứng
- Test với `America/New_York` vào ngày đổi DST: khung trống, ca trực, "hôm nay", giờ gửi nhắc đều đúng.
- Test "hôm nay" lúc 23:30 và 00:30 giờ shop (UTC đã sang ngày khác).

## Liên quan
`docs/database/db-schema.md` mục 1, 5.4 · ADR-0002.
