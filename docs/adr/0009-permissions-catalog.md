# ADR-0009: Phân quyền bằng spatie teams mode, danh mục quyền cố định trong code

- **Trạng thái:** Chấp nhận (chốt 21/10/2026)
- **Ngày:** 2026-10-21
- **Giai đoạn:** MVP

## Bối cảnh
Mỗi shop tổ chức nhân sự khác nhau: có shop lễ tân kiêm thu ngân, có shop chủ kiêm bác sĩ. Chủ shop muốn đổi tên role, sửa quyền, tạo role mới. Một người có thể làm ở nhiều shop với vai trò khác nhau. Một số quyền chạm dữ liệu nhạy cảm (giá vốn, giá nhập, sửa hóa đơn đã hoàn thành, mọi cơ sở, lương). Nếu shop tự tạo quyền tùy ý, code không biết quyền đó kiểm ở đâu.

## Quyết định
- `spatie/laravel-permission` **teams mode**, `team_id = tenant_id`. `ResolveTenant` gọi `setPermissionsTeamId()` theo shop đang mở. Chỉ guard `web`.
- **Danh mục quyền nằm trong code** (`config/permissions.php`: mã, tên tiếng Việt, mô tả, module, cờ nhạy cảm). Shop và admin nền tảng **không** tạo hay đổi tên quyền. Quyền mới thêm sau mặc định tắt ở mọi role trừ `owner`.
- Role thuộc shop. Tạo shop sinh 7 role mẫu (`owner`, `branch_manager`, `receptionist`, `cashier`, `vet`, `groomer`, `inventory`). `roles.name` là mã ổn định cho code (role tự tạo là `custom_{id}`); `roles.display_name` là tên shop thấy và đổi được.
- Chủ shop đổi tên role, bật/tắt quyền, tạo role. Role `owner` không sửa/xóa được.
- Quyền nhạy cảm (`inventory.view_cost`, `purchase.cost`, `invoices.amend`, `branches.all`, `payroll.view`) mặc định chỉ `owner`; giao cho role khác cần modal xác nhận + `activity_logs`. `roles.manage` không giao được, chặn nhân viên tự nâng quyền.
- Một người nhiều role thì cộng quyền. Quyền lấy từ role, không từ chức danh.
- **Không** dùng `Gate::before` cho owner: chủ shop không tự ghi bệnh án hay nhận Visit khám nếu không có thêm role `vet`.
- Quyền ảnh hưởng dữ liệu trả về (vd `medical.view_full`, `inventory.view_cost`) áp ở tầng API Resource riêng, không chỉ ẩn trên UI.

## Phương án đã cân nhắc
| Phương án | Ưu | Nhược | Lý do loại |
|---|---|---|---|
| Role cố định, shop không sửa | Đơn giản nhất | Không hợp shop nhỏ kiêm nhiệm | Thiếu linh hoạt bắt buộc |
| Shop tự tạo quyền | Linh hoạt tối đa | Quyền không gắn với chỗ kiểm nào trong code, vô nghĩa | Quyền phải có code kiểm |
| Tự viết bảng role/permission | Kiểm soát hoàn toàn | Viết lại cache, middleware, teams | spatie đã có teams mode ổn định |
| `Gate::before` cho owner | Ít code | Owner vượt mọi rule nghiệp vụ, kể cả ghi bệnh án | Trái quy định chuyên môn |

## Hệ quả
- Code chỉ kiểm mã quyền đã biết; UI phân quyền là danh sách bật/tắt.
- Đổi shop phải đổi team id và làm mới cache quyền đúng cách.
- Thêm quyền mới = sửa config + migration gán cho `owner` các shop hiện có.

## Kiểm chứng
- Test ma trận role × hành động theo `docs/design/design.md` mục 8 (Pest dataset).
- Test: gán `roles.manage` cho role khác → từ chối; giao `inventory.view_cost` cho quản lý → có 1 dòng `activity_logs`.
- Test: user là owner shop A, groomer shop B → quyền đúng theo shop đang mở.
- Test dataset "role × endpoint → response không có khóa nhạy cảm".

## Liên quan
`docs/database/db-schema.md` mục 3 · `docs/design/design.md` mục 8 · `docs/analysis/nghiep-vu-da-chot.md` mục 2 · ADR-0004.
