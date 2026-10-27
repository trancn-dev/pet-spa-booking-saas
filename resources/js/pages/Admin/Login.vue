<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminAuthLayout from '@/layouts/admin/AdminAuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({ email: '', password: '' });

const submit = () => form.post(route('admin.login.store'), { onFinish: () => form.reset('password') });
</script>

<template>
    <AdminAuthLayout title="Đăng nhập quản trị" description="Email, mật khẩu và mã xác thực 2 bước.">
        <Head title="Đăng nhập quản trị" />

        <form class="grid gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" />
                <InputError :message="form.errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="password">Mật khẩu</Label>
                <Input id="password" v-model="form.password" type="password" required autocomplete="current-password" />
                <InputError :message="form.errors.password" />
            </div>
            <Button type="submit" class="w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Tiếp tục
            </Button>
        </form>
    </AdminAuthLayout>
</template>
