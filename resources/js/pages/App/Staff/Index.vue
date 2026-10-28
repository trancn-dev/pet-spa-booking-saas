<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import DataTable, { type Column } from '@/components/pb/DataTable.vue';
import EmptyState from '@/components/pb/EmptyState.vue';
import FormField from '@/components/pb/FormField.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import RingAvatar from '@/components/pb/RingAvatar.vue';
import TabsBar from '@/components/pb/TabsBar.vue';
import TwoLine from '@/components/pb/TwoLine.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ShopSharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Mail, Pencil, Plus, Send, UserPlus, Users, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface StaffRow {
    id: number;
    display_name: string;
    email: string | null;
    has_account: boolean;
    title: string | null;
    color: string | null;
    status: 'active' | 'disabled';
    roles: { name: string; label: string }[];
    primary_branch: string | null;
    branches: string[];
}

interface InvitationRow {
    id: number;
    email: string;
    roles: string[];
    primary_branch: string | null;
    expires_at: string | null;
    expired: boolean;
}

interface Option {
    name: string;
    label: string;
}

const props = defineProps<{
    tab: 'members' | 'invitations';
    staff: StaffRow[];
    invitations: InvitationRow[];
    roles: Option[];
    branches: { id: string; code: string; name: string }[];
    quota: { used: number; max: number | null };
    timezone: string;
    can: { manage: boolean };
}>();

const page = usePage<ShopSharedData>();
const errors = computed(() => page.props.errors ?? {});
const tab = ref(props.tab);
const full = computed(() => props.quota.max !== null && props.quota.used >= props.quota.max);

const inviting = ref(false);
const form = useForm({
    email: '',
    roles: [] as string[],
    primary_branch: page.props.currentBranch?.id ?? props.branches[0]?.id ?? '',
    branches: [] as string[],
});
const formErrors = computed(() => form.errors as Record<string, string | undefined>);
const toggle = (list: string[], value: string) => {
    const i = list.indexOf(value);
    if (i >= 0) list.splice(i, 1);
    else list.push(value);
};
const invite = () =>
    form.post(route('app.staff.invite'), {
        preserveScroll: true,
        onSuccess: () => {
            inviting.value = false;
            tab.value = 'invitations';
            form.reset('email', 'roles', 'branches');
        },
    });

const resend = (inv: InvitationRow) => router.post(route('app.staff.invitations.resend', inv.id), {}, { preserveScroll: true });
const revoke = (inv: InvitationRow) => {
    if (confirm(`Thu hồi lời mời gửi ${inv.email}?`)) router.delete(route('app.staff.invitations.revoke', inv.id), { preserveScroll: true });
};
const setStatus = (s: StaffRow, status: 'active' | 'disabled') => {
    if (status === 'disabled' && !confirm(`Vô hiệu ${s.display_name}? Người này không vào được shop nữa (dữ liệu vẫn giữ).`)) return;
    router.patch(route('app.staff.status', s.id), { status }, { preserveScroll: true });
};

const date = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: props.timezone }).format(new Date(iso)) : '—';
const initials = (name: string) =>
    name
        .split(/\s+/)
        .slice(-2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();

const memberColumns: Column[] = [
    { key: 'name', label: 'Nhân viên', width: 'minmax(16rem,3fr)' },
    { key: 'roles', label: 'Role', width: 'minmax(10rem,2fr)' },
    { key: 'branches', label: 'Cơ sở', width: 'minmax(9rem,1.5fr)' },
    { key: 'status', label: 'Trạng thái', width: '9rem' },
    { key: 'actions', label: '', width: '12rem' },
];
const invitationColumns: Column[] = [
    { key: 'email', label: 'Email', width: 'minmax(14rem,2fr)' },
    { key: 'roles', label: 'Role', width: 'minmax(10rem,2fr)' },
    { key: 'primary_branch', label: 'Cơ sở chính', width: 'minmax(9rem,1.5fr)' },
    { key: 'expires_at', label: 'Hết hạn', width: '9rem' },
    { key: 'actions', label: '', width: '14rem' },
];
</script>

<template>
    <Head title="Nhân viên" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <PageHeader title="Nhân viên">
                <template #subtitle>
                    <span :class="full ? 'text-danger' : ''">Tài khoản theo gói: {{ quota.used }}/{{ quota.max ?? '∞' }} (gồm lời mời còn hạn)</span>
                </template>
                <template v-if="can.manage" #actions>
                    <Button variant="outline" as-child>
                        <Link :href="route('app.staff.create')"><Plus /> Nhân viên không tài khoản</Link>
                    </Button>
                    <Button
                        :disabled="full"
                        :disabled-reason="full ? 'Đã đủ số nhân viên của gói. Liên hệ nâng gói.' : undefined"
                        @click="inviting = true"
                    >
                        <UserPlus /> Mời nhân viên
                    </Button>
                </template>
            </PageHeader>
            <p v-if="can.manage && full" class="text-sm text-danger">Đạt giới hạn gói: liên hệ PawBook để nâng gói.</p>
            <InputError :message="errors.status ?? errors.invitation" />

            <TabsBar
                v-model="tab"
                label="Nhân viên và lời mời"
                :items="[
                    { value: 'members', label: 'Nhân viên', count: staff.length },
                    { value: 'invitations', label: 'Lời mời', count: invitations.length },
                ]"
            />

            <DataTable
                v-if="tab === 'members'"
                :columns="memberColumns"
                :rows="staff"
                row-key="id"
                :row-class="(s) => (s.status === 'disabled' ? 'opacity-60' : undefined)"
            >
                <template #cell-name="{ row }">
                    <div class="flex items-center gap-3">
                        <RingAvatar :initials="initials(row.display_name)" :color="row.color" :size="36" />
                        <TwoLine :title="row.display_name" :caption="[row.title, row.email ?? 'Không có tài khoản'].filter(Boolean).join(' · ')" />
                    </div>
                </template>
                <template #cell-roles="{ row }">
                    <div class="flex flex-wrap gap-1">
                        <Badge v-for="r in row.roles" :key="r.name" tone="primary" square>{{ r.label }}</Badge>
                        <span v-if="!row.roles.length" class="text-muted-foreground">—</span>
                    </div>
                </template>
                <template #cell-branches="{ row }">
                    <div>{{ row.primary_branch ?? '—' }}</div>
                    <div v-if="row.branches.length" class="text-xs text-muted-foreground">+ {{ row.branches.join(', ') }}</div>
                </template>
                <template #cell-status="{ row }">
                    <Badge :tone="row.status === 'active' ? 'success' : 'neutral'" dot>{{
                        row.status === 'active' ? 'Đang làm' : 'Đã vô hiệu'
                    }}</Badge>
                </template>
                <template #cell-actions="{ row }">
                    <div v-if="can.manage" class="flex justify-end gap-1">
                        <Button variant="ghost" size="icon" as-child>
                            <Link :href="route('app.staff.edit', row.id)" :aria-label="`Sửa ${row.display_name}`"><Pencil /></Link>
                        </Button>
                        <Button v-if="row.status === 'active'" variant="ghostDanger" size="sm" @click="setStatus(row, 'disabled')">Vô hiệu</Button>
                        <Button v-else variant="ghost" size="sm" @click="setStatus(row, 'active')">Kích hoạt lại</Button>
                    </div>
                </template>
                <template #empty
                    ><EmptyState title="Chưa có nhân viên"
                        ><template #icon><Users /></template></EmptyState
                ></template>
            </DataTable>

            <DataTable v-else :columns="invitationColumns" :rows="invitations" row-key="id">
                <template #cell-email="{ row }"
                    ><span class="font-medium">{{ row.email }}</span></template
                >
                <template #cell-roles="{ row }">{{ row.roles.join(', ') }}</template>
                <template #cell-primary_branch="{ row }">{{ row.primary_branch ?? '—' }}</template>
                <template #cell-expires_at="{ row }">
                    <span class="tabular-nums" :class="{ 'text-danger': row.expired }">{{ row.expired ? 'Đã hết hạn' : date(row.expires_at) }}</span>
                </template>
                <template #cell-actions="{ row }">
                    <div v-if="can.manage" class="flex justify-end gap-1">
                        <Button variant="ghost" size="sm" @click="resend(row)"><Send /> Gửi lại</Button>
                        <Button variant="ghostDanger" size="sm" @click="revoke(row)"><X /> Thu hồi</Button>
                    </div>
                </template>
                <template #empty
                    ><EmptyState title="Không có lời mời đang chờ"
                        ><template #icon><Mail /></template></EmptyState
                ></template>
            </DataTable>
        </div>

        <Dialog v-model:open="inviting">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Mời nhân viên</DialogTitle>
                    <DialogDescription
                        >Người được mời nhận email, tạo tài khoản hoặc đăng nhập rồi vào shop. Link hết hạn sau 7 ngày.</DialogDescription
                    >
                </DialogHeader>
                <form id="invite-form" class="grid gap-5" @submit.prevent="invite">
                    <FormField label="Email" input-id="invite-email" :error="form.errors.email"
                        ><Input id="invite-email" v-model="form.email" type="email" required
                    /></FormField>
                    <fieldset class="grid gap-2">
                        <legend class="text-sm font-medium">Role (chọn một hoặc nhiều)</legend>
                        <div class="flex flex-wrap gap-2">
                            <label
                                v-for="r in roles"
                                :key="r.name"
                                class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
                                :class="{ 'border-primary bg-primary-soft': form.roles.includes(r.name) }"
                            >
                                <input
                                    type="checkbox"
                                    class="accent-primary"
                                    :checked="form.roles.includes(r.name)"
                                    @change="toggle(form.roles, r.name)"
                                />
                                {{ r.label }}
                            </label>
                        </div>
                        <InputError :message="form.errors.roles" />
                    </fieldset>
                    <FormField label="Cơ sở chính" input-id="invite-primary" :error="formErrors.primary_branch"
                        ><select
                            id="invite-primary"
                            v-model="form.primary_branch"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.code }} · {{ b.name }}</option>
                        </select></FormField
                    >
                    <fieldset v-if="branches.length > 1" class="grid gap-2">
                        <legend class="text-sm font-medium">Cơ sở phụ</legend>
                        <div class="flex flex-wrap gap-3 text-sm">
                            <label v-for="b in branches.filter((b) => b.id !== form.primary_branch)" :key="b.id" class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    class="accent-primary"
                                    :checked="form.branches.includes(b.id)"
                                    @change="toggle(form.branches, b.id)"
                                />
                                {{ b.name }}
                            </label>
                        </div>
                        <InputError :message="formErrors.branches" />
                    </fieldset>
                </form>
                <DialogFooter>
                    <Button variant="outline" @click="inviting = false">Hủy</Button>
                    <Button type="submit" form="invite-form" :disabled="form.processing">Gửi lời mời</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
