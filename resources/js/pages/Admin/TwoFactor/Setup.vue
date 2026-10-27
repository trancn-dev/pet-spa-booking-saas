<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminAuthLayout from '@/layouts/admin/AdminAuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{ email: string; qrSvg: string; secret: string }>();

const form = useForm({ code: '' });

const submit = () => form.post(route('admin.two-factor.confirm'), { onFinish: () => form.reset('code') });
</script>

<template>
    <AdminAuthLayout title="Bật xác thực 2 bước" description="Bắt buộc với mọi quản trị viên. Quét mã bằng Google Authenticator, 1Password hoặc ứng dụng tương tự.">
        <Head title="Bật xác thực 2 bước" />

        <div class="grid gap-5">
            <!-- SVG generated server-side from the admin's own secret. -->
            <div class="mx-auto rounded-lg bg-white p-2" v-html="qrSvg" />
            <div class="text-center text-xs text-stone-500">
                Không quét được? Nhập khóa:
                <div class="mt-1 select-all font-mono text-sm text-stone-800 dark:text-slate-200">{{ secret }}</div>
            </div>

            <form class="grid gap-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="code">Mã 6 số trong ứng dụng ({{ email }})</Label>
                    <Input
                        id="code"
                        v-model="form.code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="7"
                        required
                        class="text-center font-mono text-lg tracking-[0.4em] tabular-nums"
                    />
                    <InputError :message="form.errors.code" />
                </div>
                <Button type="submit" class="w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Xác nhận và đăng nhập
                </Button>
            </form>
        </div>
    </AdminAuthLayout>
</template>
