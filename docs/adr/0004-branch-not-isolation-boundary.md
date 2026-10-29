# ADR-0004: Cơ sở là `branch_id` + FK ghép, không phải ranh giới cô lập

- **Trạng thái:** Chấp nhận
- **Ngày:** 2026-10-21
- **Giai đoạn:** MVP

## Bối cảnh
Một shop có nhiều cơ sở. Khách, pet, bệnh án, dịch vụ, giá dùng chung mọi cơ sở. Nhân viên có cơ sở chính và phụ. Nghiệp vụ cần nhìn chéo: tra tồn kho cơ sở khác (GĐ2), bác sĩ xem bệnh án pet khám ở cơ sở khác, chủ shop xem báo cáo mọi cơ sở.

## Quyết định
- Bảng thuộc cơ sở (lịch hẹn, Visit, hóa đơn, két, tồn kho…) có `branch_id` với FK ghép `(tenant_id, branch_id)`.
- **Không** có global scope theo cơ sở. Lọc cơ sở làm ở Query class + Policy, theo `staff_branches` và quyền `branches.all`.
- Cơ sở đang làm việc (`CurrentBranch`) lưu trong session, chỉ chọn được cơ sở có trong `staff_branches`.
- Dữ liệu dùng chung mọi cơ sở (khách, pet, bệnh án, dịch vụ) không có `branch_id`.

## Phương án đã cân nhắc
| Phương án | Ưu | Nhược | Lý do loại |
|---|---|---|---|
| Global scope theo cơ sở giống tenant | Ít quên lọc | Mọi màn xem chéo phải tắt scope; tắt scope thường xuyên làm yếu chính quy tắc cấm tắt scope của tenant | Xem chéo là nghiệp vụ bình thường, không phải ngoại lệ |
| Mỗi cơ sở là một tenant | Tái dùng cơ chế tenant | Khách, pet, bệnh án bị chia nhỏ; khách đến cơ sở khác phải tạo lại hồ sơ | Sai mô hình nghiệp vụ |

## Hệ quả
- Rò rỉ giữa cơ sở trong cùng shop là vấn đề **phân quyền**, không phải cô lập dữ liệu. Hậu quả nhẹ hơn rò rỉ giữa shop.
- Mỗi Query/Policy phải tự lọc cơ sở; quên là nhân viên cơ sở A thấy danh sách cơ sở B. Chặn bằng test ma trận role × cơ sở.
- FK ghép vẫn chặn gắn nhầm `branch_id` của shop khác.

## Kiểm chứng
- Test: lễ tân chỉ có CS1 gọi danh sách lịch/hóa đơn CS2 → 403 hoặc rỗng; chủ shop có `branches.all` thấy cả hai.
- Test: bác sĩ CS1 xem được bệnh án pet từng khám ở CS2.

## Liên quan
`docs/database/db-schema.md` mục 1, 2.2 · ADR-0001 · ADR-0009.
