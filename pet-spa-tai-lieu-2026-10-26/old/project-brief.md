# Project brief: Pet Spa & Vet Booking SaaS

## Mục tiêu
Dự án cá nhân để làm đẹp CV ở mức senior Laravel. Chứng minh khả năng thiết kế hệ thống (multi-tenancy, concurrency, queue, billing), không chỉ viết CRUD.

## Đã chốt
- Loại dự án: **SaaS multi-tenant hệ thống đặt lịch hẹn**.
- Mảng: **pet spa / phòng khám thú y**.
- Tenant = một tiệm pet / phòng khám. ~~Owner tạo workspace và mời nhân viên.~~ (v3, 17/10/2026) **Nhà cung cấp (admin nền tảng) tạo shop và mời Owner**, Owner nhận lời mời rồi mời nhân viên. Không có tự đăng ký ở MVP.

## Kỹ thuật đã chốt (theo code nhánh `dev` và db-schema.md)
- Stack: Laravel 12 + Inertia 2 + Vue 3 `<script setup>` + TypeScript, MySQL 8, Redis + Horizon, Docker.
- Multi-tenancy: **một DB + `tenant_id` + global scope fail closed + FK ghép `(tenant_id, x_id)`**. `users` là danh tính toàn cục, thành viên tiệm qua `staff`; CMS ở `/app/{slug}`.
- Phân quyền: `spatie/laravel-permission` teams mode (owner / bác sĩ / groomer / lễ tân). Admin nền tảng dùng bảng + guard riêng.
- Billing: MVP admin gán gói + hạn dùng tay; Laravel Cashier (Stripe) để sau MVP.
- Chất lượng: Pest, Larastan, GitHub Actions, Sentry, deploy có link demo.

## Phạm vi theo giai đoạn (chi tiết: plan/implementation-plan.md)
- **MVP:** admin nền tảng (tạo shop, gói/hạn, tạm khóa, impersonate) · nhân viên + phân quyền · chủ nuôi, thú cưng · dịch vụ thời lượng động · lịch làm việc · đặt lịch chống double-booking · tiếp nhận, hàng đợi, lượt khám · hóa đơn, thanh toán · nhắc hẹn, nhắc tiêm phòng · Site của tiệm (đặt lịch, OTP, lịch sử) · đa chi nhánh mức schema + switcher.
- **Giai đoạn 2:** kho, thuốc theo lô, bán hàng, điều chuyển giữa chi nhánh, trưng bày sản phẩm trên Site.
- **Giai đoạn 3:** xếp ca, chấm công, lương.
- Ý mới bổ sung giữ đúng giai đoạn đã hoạch định, chỉ ghi vào tài liệu.

## Điểm nhấn cho CV
- **Nhắc tiêm phòng / tẩy giun định kỳ**: scheduler tự tính ngày kế tiếp theo phác đồ và nhắc chủ nuôi.
- Hồ sơ y tế có phân quyền chi tiết (chỉ bác sĩ sửa, lễ tân chỉ xem).
- Site riêng mỗi tiệm cho chủ nuôi tự đặt lịch, xem lịch sử (v3: `pawbook.vn/{slug}`; CMS nhân viên ở `/app/{slug}`).
- Gói thuê bao giới hạn số nhân viên / số lịch hẹn.
- Xử lý timezone, job idempotent, retry, webhook thanh toán idempotent.
- README có sơ đồ kiến trúc, ADR, số liệu (coverage, throughput) và demo link.

## Lộ trình
Xem plan/implementation-plan.md (phase 1-10 là MVP, 11 là GĐ2, 12 là GĐ3). Phase 1-2 đã xong trên nhánh `dev`.
