# Project brief: Pet Spa & Vet Booking SaaS

## Mục tiêu
Dự án cá nhân để làm đẹp CV ở mức senior Laravel. Chứng minh khả năng thiết kế hệ thống (multi-tenancy, concurrency, queue, billing), không chỉ viết CRUD.

## Đã chốt
- Loại dự án: **SaaS multi-tenant hệ thống đặt lịch hẹn**.
- Mảng: **pet spa / phòng khám thú y**.
- Tenant = một tiệm pet / phòng khám. Owner tạo workspace và mời nhân viên.

## Đề xuất (chưa chốt, có thể đổi)
- Stack: Laravel + Inertia.js + Vue 3, Redis + Horizon, MySQL/PostgreSQL.
- Multi-tenancy: **một DB + `tenant_id` + global scope** (đơn giản nhất). Nâng cấp lên DB riêng từng tenant (`stancl/tenancy`) nếu muốn.
- Phân quyền: `spatie/laravel-permission` (owner / bác sĩ / groomer / lễ tân).
- Billing: Laravel Cashier (Stripe), giới hạn theo gói.
- Chất lượng: Pest, Larastan (level cao), GitHub Actions, Docker, Sentry, deploy có link demo.

## Tính năng MVP
1. Đăng ký tenant, mời nhân viên, phân quyền.
2. Hồ sơ chủ nuôi và thú cưng (loài, giống, cân nặng, tiền sử, lịch sử dịch vụ).
3. Dịch vụ với **thời lượng động** theo giống/cỡ (vd: chó nhỏ 45 phút, chó lớn 90 phút).
4. Đặt lịch: chọn dịch vụ, chọn nhân viên hoặc "bất kỳ", lịch làm việc và ngày nghỉ từng nhân viên, **chống double-booking** (transaction + lock).
5. Nhắc hẹn qua queue (email, Telegram, có thể thêm Zalo).

## Điểm nhấn cho CV
- **Nhắc tiêm phòng / tẩy giun định kỳ**: scheduler tự tính ngày kế tiếp theo phác đồ và nhắc chủ nuôi.
- Hồ sơ y tế có phân quyền chi tiết (chỉ bác sĩ sửa, lễ tân chỉ xem).
- Cổng tự đặt lịch cho chủ nuôi (link công khai riêng mỗi tenant, slug hoặc subdomain).
- Gói thuê bao giới hạn số nhân viên / số lịch hẹn.
- Xử lý timezone, job idempotent, retry, webhook thanh toán idempotent.
- README có sơ đồ kiến trúc, ADR, số liệu (coverage, throughput) và demo link.

## Lộ trình gợi ý (chưa chi tiết hóa)
1. Khởi tạo project (starter kit Vue/Inertia), CI, Docker, Larastan, Pest.
2. Tenant + auth + RBAC.
3. Pet, chủ nuôi, dịch vụ.
4. Đặt lịch + chống trùng lịch + test concurrency.
5. Queue / scheduler: nhắc hẹn, nhắc tiêm phòng.
6. Cổng đặt lịch công khai.
7. Billing + giới hạn gói.
8. Hoàn thiện: monitoring, tối ưu truy vấn, README, deploy demo.

## Việc tiếp theo
- Vẽ lược đồ DB (tenants, users, pets, owners, services, appointments, vaccinations, schedules).
- Chi tiết hóa lộ trình theo tuần.
