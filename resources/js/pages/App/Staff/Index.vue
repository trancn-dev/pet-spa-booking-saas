<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
</script>

<template>
    <Head title="Nhân viên" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Nhân viên</h1>
                    <p class="text-sm" :class="full ? 'text-danger' : 'text-muted-foreground'">
                        Tài khoản theo gói: {{ quota.used }}/{{ quota.max ?? '∞' }} (gồm lời mời còn hạn)
                    </p>
                </div>
                <div v-if="can.manage" class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="route('app.staff.create')"><Plus /> Nhân viên không tài khoản</Link>
                    </Button>
                    <Button :disabled="full" :title="full ? 'Đã đủ số nhân viên của gói. Liên hệ nâng gói.' : undefined" @click="inviting = true">
                        <UserPlus /> Mời nhân viên
                    </Button>
                </div>
            </div>
            <p v-if="can.manage && full" class="text-sm text-danger">Đạt giới hạn gói: liên hệ PawBook để nâng gói.</p>
            <InputError :message="errors.status ?? errors.invitation" />

            <nav class="flex gap-1 border-b" aria-label="Tabs">
                <button
                    v-for="t in [
                        { key: 'members', label: `Nhân viên (${staff.length})` },
                        { key: 'invitations', label: `Lời mời (${invitations.length})` },
                    ]"
                    :key="t.key"
                    type="button"
                    class="-mb-px border-b-2 px-3 py-2 text-sm"
                    :class="tab === t.key ? 'border-primary font-medium' : 'border-transparent text-muted-foreground'"
                    @click="tab = t.key as 'members' | 'invitations'"
                >
                    {{ t.label }}
                </button>
            </nav>

            <!-- Members -->
            <div v-if="tab === 'members'" class="overflow-x-auto rounded-[10px] border bg-card">
                <table class="w-full text-sm">
                    <thead class="bg-muted text-left text-[13px] font-medium text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Nhân viên</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Cơ sở</th>
                            <th class="px-4 py-3">Trạng thái</th>
                            <th class="px-4 py-3"><span class="sr-only">Thao tác</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in staff" :key="s.id" class="border-t" :class="{ 'opacity-60': s.status === 'disabled' }">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold ring-2"
                                        :style="{ '--tw-ring-color': s.color ?? 'transparent' }"
                                        >{{ initials(s.display_name) }}</span
                                    >
                                    <div>
                                        <div class="font-medium">{{ s.display_name }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ s.title ?? '' }}<template v-if="s.title"> · </template>{{ s.email ?? 'Không có tài khoản' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <Badge v-for="r in s.roles" :key="r.name" tone="primary" square>{{ r.label }}</Badge>
                                    <span v-if="!s.roles.length" class="text-muted-foreground">—</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ s.primary_branch ?? '—' }}</div>
                                <div v-if="s.branches.length" class="text-xs text-muted-foreground">+ {{ s.branches.join(', ') }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-md px-2 py-0.5 text-xs font-medium"
                                    :class="s.status === 'active' ? 'bg-primary-soft text-primary' : 'bg-muted text-muted-foreground'"
                                    >{{ s.status === 'active' ? 'Đang làm' : 'Đã vô hiệu' }}</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="can.manage" class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" as-child>
                                        <Link :href="route('app.staff.edit', s.id)" :aria-label="`Sửa ${s.display_name}`"><Pencil /></Link>
                                    </Button>
                                    <Button
                                        v-if="s.status === 'active'"
                                        variant="ghost"
                                        size="sm"
                                        class="text-danger"
                                        @click="setStatus(s, 'disabled')"
                                        >Vô hiệu</Button
                                    >
                                    <Button v-else variant="ghost" size="sm" @click="setStatus(s, 'active')">Kích hoạt lại</Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!staff.length" class="flex flex-col items-center gap-2 px-6 py-16 text-center">
                    <Users class="size-10 text-subtle" />
                    <p class="font-medium">Chưa có nhân viên</p>
                </div>
            </div>

            <!-- Invitations -->
            <div v-else class="overflow-x-auto rounded-[10px] border bg-card">
                <table v-if="invitations.length" class="w-full text-sm">
                    <thead class="bg-muted text-left text-[13px] font-medium text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Cơ sở chính</th>
                            <th class="px-4 py-3">Hết hạn</th>
                            <th class="px-4 py-3"><span class="sr-only">Thao tác</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inv in invitations" :key="inv.id" class="border-t">
                            <td class="px-4 py-3 font-medium">{{ inv.email }}</td>
                            <td class="px-4 py-3">{{ inv.roles.join(', ') }}</td>
                            <td class="px-4 py-3">{{ inv.primary_branch ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums" :class="{ 'text-danger': inv.expired }">
                                {{ inv.expired ? 'Đã hết hạn' : date(inv.expires_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="can.manage" class="flex justify-end gap-1">
                                    <Button variant="ghost" size="sm" @click="resend(inv)"><Send /> Gửi lại</Button>
                                    <Button variant="ghost" size="sm" class="text-danger" @click="revoke(inv)"><X /> Thu hồi</Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-else class="flex flex-col items-center gap-2 px-6 py-16 text-center">
                    <Mail class="size-10 text-subtle" />
                    <p class="font-medium">Không có lời mời đang chờ</p>
                </div>
            </div>
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
                    <div class="grid gap-2">
                        <Label for="invite-email">Email</Label>
                        <Input id="invite-email" v-model="form.email" type="email" required />
                        <InputError :message="form.errors.email" />
                    </div>
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
                    <div class="grid gap-2">
                        <Label for="invite-primary">Cơ sở chính</Label>
                        <select
                            id="invite-primary"
                            v-model="form.primary_branch"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.code }} · {{ b.name }}</option>
                        </select>
                        <InputError :message="formErrors.primary_branch" />
                    </div>
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
