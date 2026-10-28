import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface BranchOption {
    id: string;
    code: string;
    name: string;
}

/** Props shared on every page inside a shop (`/app/{slug}`, HandleInertiaRequests). */
export interface ShopSharedData extends SharedData {
    tenant: { slug: string; name: string } | null;
    permissions: string[];
    workspaces: { slug: string; name: string }[];
    branches: BranchOption[];
    currentBranch: BranchOption | null;
    flash: { status: string | null };
    errors: Record<string, string>;
}

export interface NavGroup {
    label?: string;
    items: NavItem[];
}
