# Project brief: Pet Spa & Vet Booking SaaS (PawBook)

## Mục tiêu
Dự án cá nhân để làm đẹp CV ở mức senior Laravel. Chứng minh khả năng thiết kế hệ thống (multi-tenancy, concurrency, queue, nghiệp vụ tiền/kho), không chỉ viết CRUD.

## Sản phẩm
- **SaaS multi-tenant** cho **pet spa / phòng khám thú y**. Tenant = một shop, có thể nhiều cơ sở.
- Nhà cung cấp (admin nền tảng) tạo shop, gán gói + hạn dùng, mời chủ shop. Chủ shop mời nhân viên. Không có tự đăng ký.
- Ba khu: **CMS** cho nhân viên (`/app/{slug}`), **Site** cho khách (`/{slug}`, đặt lịch, OTP, lịch sử), **Admin** cho nhà cung cấp (`/admin`).

## Tài liệu chốt
- Nghiệp vụ: `docs/analysis/nghiep-vu-da-chot.md` (+ sơ đồ cùng thư mục).
- Dữ liệu: `docs/database/db-schema.md`. Giao diện: `docs/design/design.md`. Mã màn: `docs/design/danh-muc-man-hinh.md`. Kế hoạch: `docs/plan/implementation-plan.md`.
- Bản cũ ở `old/` (cùng cấu trúc thư mục).

## Kỹ thuật
- Stack: Laravel 12 + Inertia 2 + Vue 3 `<script setup>` + TypeScript, MySQL 8, Redis + Horizon, Docker.
- Multi-tenancy: **một DB + `tenant_id` + global scope fail closed + FK ghép `(tenant_id, x_id)`**. `users` là danh tính toàn cục, thành viên shop qua `staff`.
- Phân quyền: `spatie/laravel-permission` teams mode, 7 role mẫu (chủ shop, quản lý chi nhánh, lễ tân, thu ngân, bác sĩ, nhân viên spa, nhân viên kho), chủ shop sửa được. Admin nền tảng dùng bảng + guard riêng.
- Gói: admin gán gói + hạn dùng tay; thu phí online để sau V1.
- Tin nhắn khách: Zalo ZNS, SMS dự phòng; OTP qua SMS.
- Chất lượng: Pest (test trên MySQL thật), Larastan, GitHub Actions, Sentry, deploy có link demo.

## Phạm vi theo giai đoạn
- **MVP:** admin nền tảng (tạo shop, gói/hạn, tạm khóa, đăng nhập thay) · nhân viên, role · khách, thú cưng · dịch vụ, combo spa, giá/thời lượng theo pet · lịch trực · đặt lịch nhiều pet theo hạn mức online · tiếp nhận, hàng đợi, cấp cứu, Visit · bệnh án, tiêm phòng · hóa đơn, thanh toán, ca thu ngân · nhắc hẹn Zalo/SMS · Site của shop · đa cơ sở.
- **GĐ2:** kho theo lô (FIFO), phiếu nhập 2 bước, hao hụt, kiểm kê, bán hàng, chuyển kho, trả hàng, trưng bày sản phẩm trên Site, báo cáo lãi gộp.
- **GĐ3:** đăng ký ca, xếp ca, chấm công, lương theo ca.
- **Sau V1:** thành viên/tích điểm, khuyến mãi, lưu chuồng, thu phí gói online, lương tháng.
- Ý mới bổ sung giữ đúng giai đoạn đã hoạch định, chỉ ghi vào tài liệu.

## Điểm nhấn cho CV
- **Chống đặt vượt hạn mức online** (số người trực × tỉ lệ) bằng khóa một hàng đếm, có test concurrency 20 tiến trình.
- Multi-tenant fail closed + FK ghép ở DB (kể cả "pet phải của đúng khách").
- Hàng đợi cấp số nguyên tử, cấp cứu chen hàng; hóa đơn bất biến sau hoàn thành (sửa = hủy + bản thay thế), ca thu ngân.
- Nhắc tiêm phòng / tẩy giun theo phác đồ; nhắc qua Zalo, lỗi thì SMS, idempotent.
- Bệnh án phân quyền ở tầng dữ liệu (lễ tân chỉ thấy cảnh báo; Site không bao giờ thấy ghi chú nội bộ).
- GĐ2: sổ kho append-only, FIFO theo lô, giá vốn chỉ chủ xem.
- Xử lý múi giờ (UTC + giờ shop, test DST), job idempotent, retry.
- README có sơ đồ kiến trúc, ADR, số liệu (coverage, p95 đặt lịch) và demo link.

## Lộ trình
Xem `docs/plan/implementation-plan.md` (phase 1–10 MVP, 11 GĐ2, 12 GĐ3). Phase 1–2 đã xong trên nhánh `dev`.
