# ADR-0008: Admin nền tảng cùng app, bảng + guard riêng, đăng nhập thay bằng token 1 lần

- **Trạng thái:** Chấp nhận
- **Ngày:** 2026-10-21
- **Giai đoạn:** MVP

## Bối cảnh
Nhà cung cấp (admin nền tảng) tạo shop, gán gói + hạn dùng, tạm khóa, và đôi khi cần vào CMS của shop để hỗ trợ. Không có tự đăng ký. Admin là tài khoản quyền lực nhất hệ thống, nên bị lộ là mất mọi shop.

## Quyết định
- Khu `/admin` nằm **cùng app Laravel**, namespace `App\Platform`.
- Bảng `platform_admins` riêng, guard `platform` riêng, cookie phiên riêng (`pawbook_admin_session`). Không thêm cột quyền nền tảng vào `users`. Role admin: `super_admin`, `support` (cột enum, không dùng spatie).
- 2FA TOTP bắt buộc. Admin đầu tiên tạo bằng `php artisan platform:admin-create`; admin sau do super-admin mời (token 1 lần, sha256, 72 giờ).
- Admin chỉ thấy số tổng hợp. Muốn xem dữ liệu shop phải **đăng nhập thay**: nhập lý do → `impersonation_sessions` + token 32 byte (lưu sha256, sống 60 giây, dùng 1 lần) → tab mới `/app/{slug}/impersonate/{token}` → đăng nhập guard `web` là chủ shop, phiên tối đa 30 phút, banner đỏ.
- Trong phiên đăng nhập thay: chặn route nhạy cảm (đổi mật khẩu, lương, sửa/hủy hóa đơn, mời/khóa nhân viên, đổi quyền); mọi request ghi tạo `platform_audit_logs`; `activity_logs` của shop mang `impersonation_session_id`; chủ shop xem được lịch sử truy cập hỗ trợ.
- Đổi trạng thái shop bằng compare-and-set (`UPDATE … WHERE status = :expected`).

## Phương án đã cân nhắc
| Phương án | Ưu | Nhược | Lý do loại |
|---|---|---|---|
| App riêng cho admin | Tách biệt triệt để | Hai codebase, chia sẻ model/migration khó, đăng nhập thay phải qua SSO | Quá nặng cho một người làm |
| Cột `is_admin` trên `users` | Nhanh | Một lỗi phân quyền là nhân viên shop thành admin; dùng chung phiên, chung 2FA policy | Trộn hai mức tin cậy |
| Đăng nhập thay bằng cách đổi `Auth::id()` trong phiên admin | Không cần token | Phiên admin và web dùng cookie khác nhau; trộn guard dễ rò quyền | Token 1 lần sạch hơn và để lại dấu vết |

## Hệ quả
- Một codebase, một deploy; ranh giới admin/shop nằm ở guard, cookie, namespace.
- `withoutTenancy()` chỉ được gọi trong `App\Platform` và command trong whitelist (arch test).
- Mọi hành động admin có dấu vết; chủ shop thấy được admin đã làm gì trong shop mình.

## Kiểm chứng
- Test: user guard `web` gọi `/admin/*` → 404; đăng xuất CMS không đăng xuất admin.
- Test: token đăng nhập thay dùng 2 lần, hết hạn, dùng với slug shop khác → 404; route nhạy cảm bị chặn; hết 30 phút bị đăng xuất.

## Liên quan
`design/db-schema.md` mục 2.1, 5.7 · plan task 4.1, 4.5, 4.6.
