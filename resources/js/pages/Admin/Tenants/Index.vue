<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, Store } from 'lucide-vue-next';
import { ref } from 'vue';

type TenantStatus = 'trial' | 'active' | 'past_due' | 'suspended' | 'cancelled';

interface TenantRow {
    id: string;
    name: string;
    slug: string;
    owner_name: string | null;
    owner_phone: string | null;
    plan: string | null;
    plan_expires_at: string | null;
    status: TenantStatus;
    branches_count: number;
    staff_count: number;
    appointments_this_month: number;
}

const props = defineProps<{
    kpis: { total: number; active: number; expiring: number; overdue: number };
    tenants: { data: TenantRow[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filters: { q: string | null; status: TenantStatus | null };
    expiringDays: number;
    can: { createTenant: boolean };
}>();

const statusMeta: Record<TenantStatus, { label: string; class: string }> = {
    trial: { label: 'Dùng thử', class: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200' },
    active: { label: 'Hoạt động', class: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' },
    past_due: { label: 'Quá hạn', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' },
    suspended: { label: 'Tạm khóa', class: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' },
    cancelled: { label: 'Đã hủy', class: 'bg-stone-100 text-stone-500 line-through dark:bg-slate-800' },
};

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

const apply = () =>
    router.get(route('admin.tenants.index'), { q: q.value || undefined, status: status.value || undefined }, { preserveState: true, replace: true });

const dateFormat = new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'Asia/Ho_Chi_Minh' });
const number = new Intl.NumberFormat('vi-VN');

/** Expiry: red when past, amber within N days (design 6.10, A2). */
const expiry = (iso: string | null) => {
    if (!iso) return { text: '—', class: 'text-stone-400' };
    const date = new Date(iso);
    const days = (date.getTime() - Date.now()) / 86_400_000;
    const cls = days < 0 ? 'text-red-600 font-medium' : days <= props.expiringDays ? 'text-amber-600 font-medium' : '';
    return { text: dateFormat.format(date), class: cls };
};

const kpiCards = [
    { key: 'total', label: 'Tổng số shop' },
    { key: 'active', label: 'Đang hoạt động' },
    { key: 'expiring', label: `Sắp hết hạn (≤ ${props.expiringDays} ngày)` },
    { key: 'overdue', label: 'Quá hạn / tạm khóa' },
] as const;
</script>

<template>
    <AdminLayout title="Shop">
        <Head title="Shop" />

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="card in kpiCards" :key="card.key" class="rounded-xl border border-stone-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="text-[13px] text-stone-500">{{ card.label }}</div>
                <div class="mt-1 text-[32px] font-bold leading-10 tabular-nums">{{ number.format(kpis[card.key]) }}</div>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <form class="flex flex-1 flex-wrap items-center gap-3" @submit.prevent="apply">
                <div class="relative min-w-[240px] flex-1">
                    <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                    <Input v-model="q" class="pl-9" placeholder="Tìm tên shop, slug, chủ shop, SĐT" aria-label="Tìm shop" />
                </div>
                <select
                    v-model="status"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    aria-label="Lọc trạng thái"
                    @change="apply"
                >
                    <option value="">Mọi trạng thái</option>
                    <option v-for="(meta, key) in statusMeta" :key="key" :value="key">{{ meta.label }}</option>
                </select>
            </form>
            <Button v-if="can.createTenant" as-child>
                <Link :href="route('admin.tenants.create')"><Plus /> Tạo shop</Link>
            </Button>
        </div>

        <div class="mt-4 overflow-x-auto rounded-xl border border-stone-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table v-if="tenants.data.length" class="w-full text-sm">
                <thead class="sticky top-0 bg-stone-50 text-left text-[13px] font-medium text-stone-500 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3">Shop</th>
                        <th class="px-4 py-3">Chủ shop</th>
                        <th class="px-4 py-3">Gói</th>
                        <th class="px-4 py-3">Hạn dùng</th>
                        <th class="px-4 py-3">Trạng thái</th>
                        <th class="px-4 py-3 text-right">Cơ sở</th>
                        <th class="px-4 py-3 text-right">Nhân viên</th>
                        <th class="px-4 py-3 text-right">Lịch tháng này</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in tenants.data" :key="t.id" class="border-t border-stone-100 hover:bg-stone-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3">
                            <Link :href="route('admin.tenants.show', t.id)" class="font-medium hover:underline">{{ t.name }}</Link>
                            <div class="font-mono text-xs text-stone-500">{{ t.slug }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div>{{ t.owner_name ?? '—' }}</div>
                            <div class="text-xs tabular-nums text-stone-500">{{ t.owner_phone }}</div>
                        </td>
                        <td class="px-4 py-3">{{ t.plan ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums" :class="expiry(t.plan_expires_at).class">{{ expiry(t.plan_expires_at).text }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-md px-2 py-0.5 text-xs font-medium" :class="statusMeta[t.status].class">{{ statusMeta[t.status].label }}</span>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ t.branches_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ t.staff_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number.format(t.appointments_this_month) }}</td>
                    </tr>
                </tbody>
            </table>

            <div v-else class="flex flex-col items-center gap-2 px-6 py-16 text-center">
                <Store class="size-10 text-stone-300" />
                <p class="font-medium">{{ filters.q || filters.status ? 'Không có shop nào khớp bộ lọc' : 'Chưa có shop nào' }}</p>
                <p v-if="!filters.q && !filters.status" class="text-sm text-stone-500">Shop do admin tạo; chủ shop vào CMS qua lời mời.</p>
            </div>
        </div>

        <nav v-if="tenants.links.length > 3" class="mt-4 flex flex-wrap gap-1" aria-label="Phân trang">
            <template v-for="link in tenants.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded-md px-3 py-1 text-sm"
                    :class="link.active ? 'bg-teal-600 text-white' : 'hover:bg-stone-100 dark:hover:bg-slate-800'"
                    preserve-scroll
                    ><span v-html="link.label"
                /></Link>
                <span v-else class="px-3 py-1 text-sm text-stone-400" v-html="link.label" />
            </template>
        </nav>
    </AdminLayout>
</template>
