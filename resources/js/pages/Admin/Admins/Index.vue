<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { roleLabels, type AdminSharedData, type PlatformAdminRole } from '@/types/admin';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, ShieldAlert, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';

interface AdminRow {
    id: number;
    name: string;
    email: string;
    role: PlatformAdminRole;
    two_factor: boolean;
    last_login_at: string | null;
    is_active: boolean;
}

defineProps<{
    admins: AdminRow[];
    invitations: { id: number; email: string; role: PlatformAdminRole; expires_at: string | null }[];
}>();

const page = usePage<AdminSharedData>();
const me = computed(() => page.props.admin);

const form = useForm<{ email: string; role: PlatformAdminRole }>({ email: '', role: 'support' });
const invite = () => form.post(route('admin.admins.invite'), { preserveScroll: true, onSuccess: () => form.reset('email') });

const statusErrors = computed(() => page.props.errors as Record<string, string>);

const setActive = (admin: AdminRow, isActive: boolean) => {
    if (!isActive && !confirm(`Vô hiệu hóa ${admin.name}? Người này sẽ bị đăng xuất ngay.`)) return;
    router.patch(route('admin.admins.status', admin.id), { is_active: isActive }, { preserveScroll: true });
};

const revoke = (id: number) => router.delete(route('admin.admins.invitations.revoke', id), { preserveScroll: true });

const dateTime = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Ho_Chi_Minh' });
const fmt = (iso: string | null) => (iso ? dateTime.format(new Date(iso)) : '—');
</script>

<template>
    <AdminLayout title="Quản lý admin">
        <Head title="Quản lý admin" />

        <InputError :message="statusErrors.is_active" class="mb-4" />

        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-[13px] font-medium text-stone-500 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3">Tên</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Vai trò</th>
                        <th class="px-4 py-3">2FA</th>
                        <th class="px-4 py-3">Đăng nhập cuối</th>
                        <th class="px-4 py-3">Trạng thái</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in admins" :key="a.id" class="border-t border-stone-100 dark:border-slate-800">
                        <td class="px-4 py-3 font-medium">{{ a.name }}</td>
                        <td class="px-4 py-3">{{ a.email }}</td>
                        <td class="px-4 py-3">{{ roleLabels[a.role] }}</td>
                        <td class="px-4 py-3">
                            <span v-if="a.two_factor" class="inline-flex items-center gap-1 text-green-700"><ShieldCheck class="size-4" /> Đã bật</span>
                            <span v-else class="inline-flex items-center gap-1 text-amber-700"><ShieldAlert class="size-4" /> Chưa bật</span>
                        </td>
                        <td class="px-4 py-3 tabular-nums">{{ fmt(a.last_login_at) }}</td>
                        <td class="px-4 py-3">{{ a.is_active ? 'Hoạt động' : 'Đã vô hiệu' }}</td>
                        <td class="px-4 py-3 text-right">
                            <template v-if="a.id !== me?.id">
                                <Button v-if="a.is_active" variant="ghost" size="sm" class="text-red-600" @click="setActive(a, false)">Vô hiệu hóa</Button>
                                <Button v-else variant="ghost" size="sm" @click="setActive(a, true)">Kích hoạt lại</Button>
                            </template>
                            <span v-else class="text-xs text-stone-400">Bạn</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-stone-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold">Mời admin</h2>
                <p class="mt-1 text-sm text-stone-500">Người được mời nhận email, đặt mật khẩu rồi bắt buộc bật 2FA. Lời mời hết hạn sau 72 giờ.</p>
                <form class="mt-4 grid gap-4" @submit.prevent="invite">
                    <div class="grid gap-2">
                        <Label for="invite-email">Email</Label>
                        <Input id="invite-email" v-model="form.email" type="email" required />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="invite-role">Vai trò</Label>
                        <select id="invite-role" v-model="form.role" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="support">Support: xem shop, đăng nhập thay</option>
                            <option value="super_admin">Super-admin: toàn quyền</option>
                        </select>
                        <InputError :message="form.errors.role" />
                    </div>
                    <Button type="submit" class="justify-self-start" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        Gửi lời mời
                    </Button>
                </form>
            </section>

            <section class="rounded-xl border border-stone-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold">Lời mời đang chờ</h2>
                <p v-if="!invitations.length" class="mt-4 text-sm text-stone-500">Không có lời mời nào đang chờ.</p>
                <ul v-else class="mt-4 divide-y divide-stone-100 dark:divide-slate-800">
                    <li v-for="i in invitations" :key="i.id" class="flex items-center justify-between py-2 text-sm">
                        <div>
                            <div>{{ i.email }} · {{ roleLabels[i.role] }}</div>
                            <div class="text-xs text-stone-500">Hết hạn {{ fmt(i.expires_at) }}</div>
                        </div>
                        <Button variant="ghost" size="sm" @click="revoke(i.id)">Thu hồi</Button>
                    </li>
                </ul>
            </section>
        </div>
    </AdminLayout>
</template>
