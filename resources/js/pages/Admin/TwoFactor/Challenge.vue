<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminAuthLayout from '@/layouts/admin/AdminAuthLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{ email: string }>();

const form = useForm({ code: '' });

const submit = () => form.post(route('admin.two-factor.verify'), { onFinish: () => form.reset('code') });
</script>

<template>
    <AdminAuthLayout title="Mã xác thực" :description="`Nhập mã 6 số trong ứng dụng xác thực cho ${email}.`">
        <Head title="Mã xác thực" />

        <form class="grid gap-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="code">Mã 6 số</Label>
                <Input
                    id="code"
                    v-model="form.code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="7"
                    required
                    autofocus
                    class="text-center font-mono text-lg tracking-[0.4em] tabular-nums"
                />
                <InputError :message="form.errors.code" />
            </div>
            <Button type="submit" class="w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Đăng nhập
            </Button>
            <Link :href="route('admin.login')" class="text-center text-sm text-stone-500 hover:underline">Dùng tài khoản khác</Link>
        </form>
    </AdminAuthLayout>
</template>
