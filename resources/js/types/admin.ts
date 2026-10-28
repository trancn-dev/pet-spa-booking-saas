export type PlatformAdminRole = 'super_admin' | 'support';

export interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: PlatformAdminRole;
    is_super_admin: boolean;
}

export interface AdminSharedData {
    name: string;
    admin: AdminUser | null;
    environment: string;
    flash: { status: string | null };
    [key: string]: unknown;
}

export const roleLabels: Record<PlatformAdminRole, string> = {
    super_admin: 'Super-admin',
    support: 'Support',
};

export interface AuditLogRow {
    id: number;
    action: string;
    admin: string | null;
    tenant: { id: string; name: string } | null;
    changes: Record<string, unknown> | null;
    ip: string | null;
    created_at: string;
}

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

/** Vietnamese labels of platform_audit_logs.action (A4 Nhật ký, A5). */
export const auditActionLabels: Record<string, string> = {
    'admin.login': 'Đăng nhập',
    'admin.logout': 'Đăng xuất',
    'admin.invited': 'Mời admin',
    'admin.invitation_accepted': 'Admin nhận lời mời',
    'admin.invitation_revoked': 'Thu hồi lời mời admin',
    'admin.disabled': 'Vô hiệu admin',
    'admin.enabled': 'Kích hoạt admin',
    'tenant.created': 'Tạo shop',
    'tenant.extended': 'Gia hạn',
    'tenant.suspended': 'Tạm khóa',
    'tenant.resumed': 'Mở khóa',
    'tenant.past_due': 'Chuyển quá hạn',
    'tenant.invitation_resent': 'Gửi lại lời mời chủ shop',
    'tenant.invitation_link_copied': 'Tạo link mời chủ shop',
};

export const auditActionLabel = (action: string) => auditActionLabels[action] ?? action;

export interface SubscriptionRow {
    id: number;
    plan: string | null;
    starts_at: string;
    ends_at: string;
    amount: string;
    paid_at: string | null;
    payment_ref: string | null;
    note: string | null;
    by: string | null;
}
