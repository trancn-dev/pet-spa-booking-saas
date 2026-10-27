<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminAuthLayout from '@/layouts/admin/AdminAuthLayout.vue';
import { roleLabels, type PlatformAdminRole } from '@/types/admin';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{ token: string; email: string; role: PlatformAdminRole }>();

const form = useForm({ name: '', password: '', password_confirmation: '' });

const submit = () =>
    form.post(route('admin.invitations.store', props.token), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <AdminAuthLayout title="Nhận lời mời quản trị" :description="`${email} · ${roleLabels[role]}. Đặt mật khẩu, bước sau bật xác thực 2 bước.`">
        <Head title="Nhận lời mời quản trị" />

        <form class="grid gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Họ tên</Label>
                <Input id="name" v-model="form.name" required autofocus autocomplete="name" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="password">Mật khẩu (tối thiểu 12 ký tự)</Label>
                <Input id="password" v-model="form.password" type="password" required autocomplete="new-password" />
                <InputError :message="form.errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">Nhập lại mật khẩu</Label>
                <Input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" />
            </div>
            <Button type="submit" class="w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Tiếp tục
            </Button>
        </form>
    </AdminAuthLayout>
</template>
