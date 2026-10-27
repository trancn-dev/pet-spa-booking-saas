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
