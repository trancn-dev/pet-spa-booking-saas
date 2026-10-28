<?php

declare(strict_types=1);

/*
| Permission catalogue (ADR 0009). Defined in code only: shops and platform admins never create
| or rename permissions. Synced to the `permissions` table by `php artisan permissions:sync`
| (also run by ReferenceDataSeeder).
|
| Per permission:
| - label / description: Vietnamese, shown on S12.3;
| - sensitive: default only on `owner`; granting it to another role needs a confirmation and is
|   written to activity_logs (db-schema 3.1);
| - assignable: false = can never be granted to a role other than owner (`roles.manage`);
| - owner: false = not even the owner has it by default (clinical work: an owner who is also a
|   vet gets the `vet` role as well). A new permission is off for every role except owner.
| - phase: MVP / GĐ2 / GĐ3; later-phase codes exist now so the template roles are complete.
*/

return [
    'modules' => [
        'customers' => 'Khách & thú cưng',
        'medical' => 'Bệnh án & tiêm phòng',
        'appointments' => 'Lịch hẹn & lịch trực',
        'visits' => 'Tiếp nhận & Visit',
        'billing' => 'Hóa đơn & thu ngân',
        'inventory' => 'Kho (GĐ2)',
        'catalog' => 'Dịch vụ',
        'staff' => 'Nhân viên & phân quyền',
        'hr' => 'Ca, chấm công, lương (GĐ3)',
        'reports' => 'Báo cáo & nhật ký',
        'settings' => 'Cài đặt & cơ sở',
    ],

    'permissions' => [
        // Customers & pets
        'customers.view' => ['module' => 'customers', 'label' => 'Xem hồ sơ khách'],
        'customers.manage' => ['module' => 'customers', 'label' => 'Tạo, sửa hồ sơ khách'],
        'customers.delete' => ['module' => 'customers', 'label' => 'Xóa hồ sơ khách'],
        'pets.view' => ['module' => 'customers', 'label' => 'Xem hồ sơ thú cưng và cảnh báo an toàn'],
        'pets.manage' => ['module' => 'customers', 'label' => 'Tạo, sửa thú cưng, cảnh báo dị ứng'],
        'pets.delete' => ['module' => 'customers', 'label' => 'Xóa hồ sơ thú cưng'],

        // Medical
        'medical.view_full' => ['module' => 'medical', 'label' => 'Xem đầy đủ bệnh án', 'description' => 'Chẩn đoán, điều trị, ghi chú nội bộ.'],
        'medical.view_alerts' => ['module' => 'medical', 'label' => 'Xem cảnh báo và ngày khám', 'description' => 'Dị ứng, bệnh nền, ngày khám; không thấy nội dung bệnh án.'],
        'medical.write' => ['module' => 'medical', 'label' => 'Ghi bệnh án', 'description' => 'Chỉ sửa dòng của mình trong 24 giờ.', 'owner' => false],
        'vaccinations.view' => ['module' => 'medical', 'label' => 'Xem sổ tiêm'],
        'vaccinations.manage' => ['module' => 'medical', 'label' => 'Ghi sổ tiêm, hoãn tiêm', 'owner' => false],

        // Appointments & rosters
        'appointments.view' => ['module' => 'appointments', 'label' => 'Xem lịch hẹn'],
        'appointments.manage' => ['module' => 'appointments', 'label' => 'Đặt, dời, hủy lịch hẹn'],
        'rosters.view' => ['module' => 'appointments', 'label' => 'Xem lịch trực của mọi người', 'description' => 'Ai cũng xem được lịch trực của chính mình.'],
        'rosters.manage' => ['module' => 'appointments', 'label' => 'Sửa lịch trực, tỉ lệ đặt online'],

        // Intake & visits
        'visits.view' => ['module' => 'visits', 'label' => 'Xem hàng đợi và Visit'],
        'visits.intake' => ['module' => 'visits', 'label' => 'Tiếp nhận, tạo Visit, cấp cứu'],
        'visits.medical' => ['module' => 'visits', 'label' => 'Nhận, hoàn tất Visit khám / tiêm', 'owner' => false],
        'visits.grooming' => ['module' => 'visits', 'label' => 'Nhận, hoàn tất Visit spa'],
        'prescriptions.manage' => ['module' => 'visits', 'label' => 'Kê thuốc, chỉ định xét nghiệm', 'owner' => false],

        // Billing
        'invoices.view' => ['module' => 'billing', 'label' => 'Xem, in hóa đơn'],
        'invoices.charge' => ['module' => 'billing', 'label' => 'Thêm dòng, thu tiền hóa đơn chưa hoàn thành'],
        'invoices.void_draft' => ['module' => 'billing', 'label' => 'Hủy hóa đơn chưa hoàn thành (có lý do)'],
        'invoices.amend' => ['module' => 'billing', 'label' => 'Sửa hóa đơn đã hoàn thành (bản thay thế)', 'sensitive' => true],
        'returns.create' => ['module' => 'billing', 'label' => 'Trả hàng, hoàn tiền trong hạn', 'phase' => 'GĐ2'],
        'returns.overdue' => ['module' => 'billing', 'label' => 'Trả hàng quá hạn', 'phase' => 'GĐ2'],
        'cash_sessions.view' => ['module' => 'billing', 'label' => 'Xem ca thu ngân'],
        'cash_sessions.manage' => ['module' => 'billing', 'label' => 'Mở, đóng ca thu ngân'],

        // Inventory (GĐ2)
        'inventory.view' => ['module' => 'inventory', 'label' => 'Xem tồn kho, tra tồn cơ sở khác', 'phase' => 'GĐ2'],
        'inventory.receive' => ['module' => 'inventory', 'label' => 'Lập phiếu nhập, kiểm kê', 'phase' => 'GĐ2'],
        'inventory.adjust' => ['module' => 'inventory', 'label' => 'Báo hao hụt, hủy hàng hết hạn', 'phase' => 'GĐ2'],
        'inventory.approve' => ['module' => 'inventory', 'label' => 'Duyệt hao hụt, kiểm kê', 'phase' => 'GĐ2'],
        'inventory.defects' => ['module' => 'inventory', 'label' => 'Xử lý hàng lỗi', 'phase' => 'GĐ2'],
        'inventory.override_batch' => ['module' => 'inventory', 'label' => 'Chọn lô khác FIFO', 'phase' => 'GĐ2'],
        'inventory.alerts' => ['module' => 'inventory', 'label' => 'Nhận cảnh báo kho', 'phase' => 'GĐ2'],
        'inventory.view_cost' => ['module' => 'inventory', 'label' => 'Xem giá vốn, giá trị tồn, lãi gộp', 'sensitive' => true, 'phase' => 'GĐ2'],
        'purchase.cost' => ['module' => 'inventory', 'label' => 'Điền giá phiếu nhập', 'sensitive' => true, 'phase' => 'GĐ2'],
        'transfers.request' => ['module' => 'inventory', 'label' => 'Tạo yêu cầu chuyển kho', 'phase' => 'GĐ2'],
        'transfers.approve' => ['module' => 'inventory', 'label' => 'Duyệt chuyển kho', 'phase' => 'GĐ2'],
        'transfers.ship' => ['module' => 'inventory', 'label' => 'Giao, nhận hàng chuyển kho', 'phase' => 'GĐ2'],

        // Catalogue
        'services.view' => ['module' => 'catalog', 'label' => 'Xem dịch vụ, giá'],
        'services.manage' => ['module' => 'catalog', 'label' => 'Sửa dịch vụ, combo, giá'],

        // Staff & roles
        'staff.view' => ['module' => 'staff', 'label' => 'Xem nhân viên'],
        'staff.manage' => ['module' => 'staff', 'label' => 'Mời, sửa, vô hiệu nhân viên'],
        'roles.manage' => ['module' => 'staff', 'label' => 'Phân quyền', 'description' => 'Chỉ Chủ shop; không giao được cho role khác.', 'assignable' => false],

        // HR (GĐ3)
        'shifts.manage' => ['module' => 'hr', 'label' => 'Xếp ca', 'phase' => 'GĐ3'],
        'attendance.approve' => ['module' => 'hr', 'label' => 'Duyệt chấm công', 'phase' => 'GĐ3'],
        'payroll.view' => ['module' => 'hr', 'label' => 'Xem bảng lương', 'sensitive' => true, 'phase' => 'GĐ3'],

        // Reports & logs
        'reports.view_today' => ['module' => 'reports', 'label' => 'Doanh thu hôm nay, cơ sở mình'],
        'reports.view_branch' => ['module' => 'reports', 'label' => 'Báo cáo cơ sở mình, mọi khoảng ngày'],
        'reports.view_all' => ['module' => 'reports', 'label' => 'Báo cáo mọi cơ sở, lãi gộp'],
        'reports.cash_session' => ['module' => 'reports', 'label' => 'Báo cáo ca thu ngân'],
        'activity_logs.view' => ['module' => 'reports', 'label' => 'Xem nhật ký thao tác'],

        // Settings & branches
        'settings.view' => ['module' => 'settings', 'label' => 'Xem cài đặt shop'],
        'settings.manage' => ['module' => 'settings', 'label' => 'Sửa cài đặt shop, kênh nhắn tin, Site'],
        'branches.manage' => ['module' => 'settings', 'label' => 'Sửa cơ sở, giờ mở cửa, két'],
        'branches.all' => ['module' => 'settings', 'label' => 'Xem, thao tác mọi cơ sở', 'sensitive' => true],
    ],

    /*
    | Template roles created with every shop (design.md section 8). `owner` gets every permission
    | whose `owner` flag is not false; it cannot be edited or deleted.
    */
    'roles' => [
        'owner' => ['display_name' => 'Chủ shop', 'permissions' => '*'],

        'branch_manager' => ['display_name' => 'Quản lý chi nhánh', 'permissions' => [
            'customers.view', 'customers.manage', 'pets.view', 'pets.manage',
            'medical.view_alerts', 'vaccinations.view',
            'appointments.view', 'appointments.manage', 'rosters.view', 'rosters.manage',
            'visits.view', 'visits.intake',
            'invoices.view', 'invoices.charge', 'invoices.void_draft', 'returns.create', 'cash_sessions.view',
            'inventory.view', 'inventory.approve', 'transfers.approve', 'inventory.alerts',
            'services.view', 'staff.view', 'shifts.manage',
            'reports.view_branch', 'settings.view',
        ]],

        'receptionist' => ['display_name' => 'Lễ tân', 'permissions' => [
            'customers.view', 'customers.manage', 'pets.view', 'pets.manage',
            'medical.view_alerts', 'vaccinations.view',
            'appointments.view', 'appointments.manage', 'rosters.view',
            'visits.view', 'visits.intake',
            'invoices.view', 'invoices.charge', 'invoices.void_draft', 'returns.create',
            'cash_sessions.view', 'cash_sessions.manage',
            'inventory.view', 'transfers.request', 'services.view', 'reports.view_today',
        ]],

        'cashier' => ['display_name' => 'Thu ngân', 'permissions' => [
            'customers.view', 'pets.view',
            'invoices.view', 'invoices.charge', 'invoices.void_draft', 'returns.create',
            'cash_sessions.view', 'cash_sessions.manage',
            'inventory.view', 'services.view', 'reports.cash_session',
        ]],

        'vet' => ['display_name' => 'Bác sĩ', 'permissions' => [
            'customers.view', 'pets.view', 'pets.manage',
            'medical.view_full', 'medical.write', 'vaccinations.view', 'vaccinations.manage',
            'appointments.view', 'visits.view', 'visits.medical', 'prescriptions.manage',
            'inventory.view', 'transfers.request', 'services.view',
        ]],

        'groomer' => ['display_name' => 'Nhân viên spa', 'permissions' => [
            'customers.view', 'pets.view', 'medical.view_alerts', 'vaccinations.view',
            'appointments.view', 'visits.view', 'visits.grooming',
            'inventory.view', 'services.view',
        ]],

        'inventory' => ['display_name' => 'Nhân viên kho', 'permissions' => [
            'inventory.view', 'inventory.receive', 'inventory.adjust', 'inventory.alerts',
            'transfers.ship', 'services.view',
        ]],
    ],
];
