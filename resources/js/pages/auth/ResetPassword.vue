<script setup lang="ts">
import FormField from '@/components/pb/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Check, Circle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    token: string;
    email: string;
}

const props = defineProps<Props>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

// Design S37: the rules light up as the person types (the server validates again).
const rules = computed(() => [
    { text: 'Ít nhất 12 ký tự', ok: form.password.length >= 12 },
    { text: 'Có chữ hoa, chữ thường và số', ok: /[A-Z]/.test(form.password) && /[a-z]/.test(form.password) && /\d/.test(form.password) },
]);
const mismatch = computed(() => form.password_confirmation !== '' && form.password !== form.password_confirmation);

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <AuthLayout title="Đặt mật khẩu mới" :description="`Chọn mật khẩu mới cho ${email}.`">
        <Head title="Đặt mật khẩu mới" />

        <form class="grid gap-5" @submit.prevent="submit">
            <input v-model="form.email" type="hidden" name="email" autocomplete="email" />
            <p
                v-if="form.errors.email"
                class="rounded-[10px] bg-red-100 px-4 py-3 text-sm text-red-900 dark:bg-red-500/15 dark:text-red-200"
                role="alert"
            >
                {{ form.errors.email }}
            </p>

            <FormField label="Mật khẩu mới" input-id="password" :error="form.errors.password">
                <Input id="password" v-model="form.password" type="password" autocomplete="new-password" autofocus class="h-11 rounded-[10px]" />
                <ul class="mt-2 grid gap-1 text-xs">
                    <li v-for="r in rules" :key="r.text" class="flex items-center gap-1.5" :class="r.ok ? 'text-success' : 'text-muted-foreground'">
                        <Check v-if="r.ok" class="size-3.5" /><Circle v-else class="size-3.5" />{{ r.text }}
                    </li>
                </ul>
            </FormField>

            <FormField
                label="Nhập lại mật khẩu"
                input-id="password_confirmation"
                :error="mismatch ? 'Hai mật khẩu chưa khớp.' : form.errors.password_confirmation"
            >
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    class="h-11 rounded-[10px]"
                />
            </FormField>

            <Button type="submit" size="touch" class="w-full" :loading="form.processing" :disabled="mismatch">Đặt mật khẩu</Button>
        </form>
    </AuthLayout>
</template>
