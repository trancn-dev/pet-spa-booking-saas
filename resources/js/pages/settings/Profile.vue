<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import FormField from '@/components/pb/FormField.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, SharedData, User } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Check, Circle } from 'lucide-vue-next';
import { computed } from 'vue';

/* Design S39: personal account, two cards (profile, password). The email is the login and cannot be changed here. */
const props = defineProps<{ status?: string }>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tài khoản cá nhân', href: '/settings/profile' }];
const user = usePage<SharedData>().props.auth.user as User;

const profile = useForm({ name: user.name, email: user.email });
const saveProfile = () => profile.patch(route('profile.update'), { preserveScroll: true });

const password = useForm({ current_password: '', password: '', password_confirmation: '' });
const savePassword = () =>
    password.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => password.reset(),
        onError: () => password.reset('password', 'password_confirmation'),
    });
const rules = computed(() => [
    { text: 'Ít nhất 12 ký tự', ok: password.password.length >= 12 },
    { text: 'Có chữ hoa, chữ thường và số', ok: /[A-Z]/.test(password.password) && /[a-z]/.test(password.password) && /\d/.test(password.password) },
]);
const mismatch = computed(() => password.password_confirmation !== '' && password.password !== password.password_confirmation);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Tài khoản cá nhân" />

        <div class="flex flex-col gap-6 p-4 md:p-6">
            <PageHeader title="Tài khoản cá nhân" />
            <p
                v-if="props.status || profile.recentlySuccessful || password.recentlySuccessful"
                class="rounded-[10px] bg-green-100 px-4 py-3 text-sm text-green-900 dark:bg-green-500/15 dark:text-green-200"
                role="status"
            >
                Đã lưu thay đổi.
            </p>

            <div class="grid max-w-5xl gap-6 md:grid-cols-2 md:items-start">
                <form class="grid gap-4 rounded-xl border bg-card p-6" @submit.prevent="saveProfile">
                    <div>
                        <h2 class="text-lg font-semibold">Thông tin cá nhân</h2>
                        <p class="text-[13px] text-muted-foreground">Tên hiển thị với đồng nghiệp trong shop.</p>
                    </div>
                    <FormField label="Họ tên" input-id="name" :error="profile.errors.name">
                        <Input id="name" v-model="profile.name" required autocomplete="name" class="h-11 rounded-[10px]" />
                    </FormField>
                    <FormField label="Email" input-id="email" hint="Email dùng để đăng nhập, không đổi được.">
                        <Input id="email" :model-value="user.email" readonly class="h-11 rounded-[10px] bg-surface-muted text-muted-foreground" />
                    </FormField>
                    <div><Button type="submit" :loading="profile.processing">Lưu thay đổi</Button></div>
                </form>

                <form class="grid gap-4 rounded-xl border bg-card p-6" @submit.prevent="savePassword">
                    <div>
                        <h2 class="text-lg font-semibold">Đổi mật khẩu</h2>
                        <p class="text-[13px] text-muted-foreground">Sau khi đổi, các thiết bị khác bị đăng xuất.</p>
                    </div>
                    <FormField label="Mật khẩu hiện tại" input-id="current_password" :error="password.errors.current_password">
                        <Input
                            id="current_password"
                            v-model="password.current_password"
                            type="password"
                            autocomplete="current-password"
                            class="h-11 rounded-[10px]"
                        />
                    </FormField>
                    <FormField label="Mật khẩu mới" input-id="new_password" :error="password.errors.password">
                        <Input
                            id="new_password"
                            v-model="password.password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Ít nhất 12 ký tự"
                            class="h-11 rounded-[10px]"
                        />
                        <ul class="mt-2 grid gap-1 text-xs">
                            <li
                                v-for="r in rules"
                                :key="r.text"
                                class="flex items-center gap-1.5"
                                :class="r.ok ? 'text-success' : 'text-muted-foreground'"
                            >
                                <Check v-if="r.ok" class="size-3.5" /><Circle v-else class="size-3.5" />{{ r.text }}
                            </li>
                        </ul>
                    </FormField>
                    <FormField
                        label="Nhập lại mật khẩu mới"
                        input-id="password_confirmation"
                        :error="mismatch ? 'Hai mật khẩu chưa khớp.' : password.errors.password_confirmation"
                    >
                        <Input
                            id="password_confirmation"
                            v-model="password.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Nhập lại"
                            class="h-11 rounded-[10px]"
                        />
                    </FormField>
                    <div>
                        <Button type="submit" :loading="password.processing" :disabled="mismatch || !password.current_password || !password.password"
                            >Đổi mật khẩu</Button
                        >
                    </div>
                </form>

                <section class="grid gap-3 rounded-xl border bg-card p-6 md:col-span-2">
                    <div>
                        <h2 class="text-lg font-semibold">Giao diện</h2>
                        <p class="text-[13px] text-muted-foreground">Chọn sáng, tối hoặc theo thiết bị. Chỉ áp dụng trên trình duyệt này.</p>
                    </div>
                    <AppearanceTabs class="w-fit" />
                </section>
            </div>
        </div>
    </AppLayout>
</template>
