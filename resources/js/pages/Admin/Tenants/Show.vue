<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Copy, Mail } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type TenantStatus = 'trial' | 'active' | 'past_due' | 'suspended' | 'cancelled';

interface Overview {
    id: string;
    name: string;
    slug: string;
    status: TenantStatus;
    timezone: string;
    plan: string | null;
    plan_expires_at: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    admin_note: string | null;
    created_at: string | null;
    site_url: string;
    invitation: { email: string; state: string; state_label: string; expires_at: string | null; accepted_at: string | null } | null;
    usage: { label: string; used: number; max: number | null }[];
}

const props = defineProps<{
    tenant: Overview;
    inviteUrl: string | null;
    can: { manage: boolean };
}>();

const page = usePage();
const errors = computed(() => page.props.errors as Record<string, string | undefined>);

const statusMeta: Record<TenantStatus, { label: string; class: string }> = {
    trial: { label: 'Dùng thử', class: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200' },
    active: { label: 'Hoạt động', class: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' },
    past_due: { label: 'Quá hạn', class: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200' },
    suspended: { label: 'Tạm khóa', class: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' },
    cancelled: { label: 'Đã hủy', class: 'bg-stone-100 text-stone-500 dark:bg-slate-800' },
};

const tabs = [
    { key: 'overview', label: 'Tổng quan', ready: true },
    { key: 'billing', label: 'Gói & gia hạn', ready: false },
    { key: 'support', label: 'Truy cập hỗ trợ', ready: false },
    { key: 'logs', label: 'Nhật ký', ready: false },
];

const dateTime = (iso: string | null) =>
    iso
        ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: props.tenant.timezone }).format(new Date(iso))
        : '—';
const date = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeZone: props.tenant.timezone }).format(new Date(iso)) : '—';

const pendingInvitation = computed(() => props.tenant.invitation && props.tenant.invitation.state !== 'accepted');
const busy = ref(false);
const copied = ref(false);

const resend = (linkOnly: boolean) => {
    busy.value = true;
    router.post(
        route('admin.tenants.invitation.resend', props.tenant.id),
        { link_only: linkOnly },
        { preserveScroll: true, onFinish: () => (busy.value = false), onSuccess: () => linkOnly && copy() },
    );
};

const copy = async () => {
    if (!props.inviteUrl) return;
    await navigator.clipboard?.writeText(props.inviteUrl);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const copyLink = () => (props.inviteUrl ? copy() : resend(true));
</script>

<template>
    <AdminLayout :title="tenant.name">
        <Head :title="tenant.name" />

        <Link :href="route('admin.tenants.index')" class="mb-4 inline-flex items-center gap-1 text-sm text-stone-500 hover:underline">
            <ArrowLeft class="size-4" /> Danh sách shop
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-semibold">{{ tenant.name }}</h2>
                    <span class="rounded-md px-2 py-0.5 text-xs font-medium" :class="statusMeta[tenant.status].class">{{ statusMeta[tenant.status].label }}</span>
                </div>
                <p class="mt-1 text-sm text-stone-500">
                    {{ tenant.plan ?? '—' }} · hạn {{ date(tenant.plan_expires_at) }} · <span class="font-mono">{{ tenant.slug }}</span>
                </p>
            </div>
        </header>

        <nav class="mt-6 flex gap-1 border-b border-stone-200 dark:border-slate-800" aria-label="Tabs">
            <span
                v-for="tab in tabs"
                :key="tab.key"
                class="-mb-px border-b-2 px-3 py-2 text-sm"
                :class="tab.key === 'overview' ? 'border-teal-600 font-medium' : 'border-transparent text-stone-400'"
                :title="tab.ready ? undefined : 'Có ở bước sau (task 4.5, 4.6)'"
            >
                {{ tab.label }}
            </span>
        </nav>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-stone-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-4 font-medium">Thông tin</h3>
                <dl class="grid grid-cols-[140px_1fr] gap-y-2 text-sm">
                    <dt class="text-stone-500">Chủ shop</dt>
                    <dd>{{ tenant.contact_name ?? '—' }}</dd>
                    <dt class="text-stone-500">Điện thoại</dt>
                    <dd class="tabular-nums">{{ tenant.contact_phone ?? '—' }}</dd>
                    <dt class="text-stone-500">Site khách</dt>
                    <dd class="font-mono">{{ tenant.site_url }}</dd>
                    <dt class="text-stone-500">Múi giờ</dt>
                    <dd>{{ tenant.timezone }}</dd>
                    <dt class="text-stone-500">Ngày tạo</dt>
                    <dd>{{ dateTime(tenant.created_at) }}</dd>
                    <dt class="text-stone-500">Ghi chú</dt>
                    <dd class="whitespace-pre-line">{{ tenant.admin_note ?? '—' }}</dd>
                </dl>
            </section>

            <section class="rounded-xl border border-stone-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-4 font-medium">Lời mời chủ shop</h3>
                <div v-if="tenant.invitation" class="grid gap-3 text-sm">
                    <p>
                        <span class="font-medium">{{ tenant.invitation.email }}</span> ·
                        <span :class="pendingInvitation ? 'text-amber-600' : 'text-green-600'">{{ tenant.invitation.state_label }}</span>
                    </p>
                    <p v-if="tenant.invitation.accepted_at" class="text-stone-500">Nhận lúc {{ dateTime(tenant.invitation.accepted_at) }}</p>
                    <p v-else class="text-stone-500">Hết hạn {{ dateTime(tenant.invitation.expires_at) }}</p>

                    <div v-if="pendingInvitation && can.manage" class="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" :disabled="busy" @click="resend(false)"><Mail /> Gửi lại</Button>
                        <Button size="sm" variant="outline" :disabled="busy" @click="copyLink"><Copy /> {{ copied ? 'Đã sao chép' : 'Sao chép link' }}</Button>
                    </div>
                    <p v-if="pendingInvitation && can.manage && !inviteUrl" class="text-xs text-stone-500">
                        Sao chép link tạo link mới; link trong email cũ sẽ không dùng được nữa.
                    </p>
                    <input
                        v-if="inviteUrl && pendingInvitation"
                        :value="inviteUrl"
                        readonly
                        class="w-full rounded-md border border-input bg-stone-50 px-3 py-1.5 font-mono text-xs dark:bg-slate-800"
                        aria-label="Link mời"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <InputError :message="errors.invitation" />
                </div>
                <p v-else class="text-sm text-stone-500">Không có lời mời.</p>
            </section>

            <section class="rounded-xl border border-stone-200 bg-white p-5 lg:col-span-2 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="mb-4 font-medium">Hạn mức đã dùng</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div v-for="item in tenant.usage" :key="item.label">
                        <div class="text-[13px] text-stone-500">{{ item.label }}</div>
                        <div class="mt-1 text-2xl font-semibold tabular-nums">
                            {{ item.used }}<span class="text-base font-normal text-stone-400"> / {{ item.max ?? '∞' }}</span>
                        </div>
                        <div v-if="item.max" class="mt-2 h-1.5 rounded-full bg-stone-100 dark:bg-slate-800">
                            <div
                                class="h-1.5 rounded-full"
                                :class="item.used >= item.max ? 'bg-red-500' : 'bg-teal-600'"
                                :style="{ width: Math.min(100, (item.used / item.max) * 100) + '%' }"
                            />
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
