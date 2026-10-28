<script setup lang="ts">
import FormField from '@/components/pb/FormField.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { MailCheck } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout
        :title="status ? 'Kiểm tra email' : 'Quên mật khẩu'"
        :description="status ? undefined : 'Nhập email nhân viên. PawBook gửi link đặt lại mật khẩu tới email đó.'"
    >
        <Head title="Quên mật khẩu" />

        <div v-if="status" class="grid gap-4">
            <div class="flex items-start gap-3 rounded-[10px] bg-primary-soft p-4 text-sm text-teal-900 dark:text-teal-200" role="status">
                <MailCheck class="mt-0.5 size-5 shrink-0" />
                <p>
                    Nếu <strong>{{ form.email || 'email đó' }}</strong> có tài khoản, link đặt lại mật khẩu đã được gửi. Link có hiệu lực trong 60
                    phút.
                </p>
            </div>
            <p class="text-sm text-muted-foreground">Không thấy email? Kiểm tra thư rác hoặc gửi lại sau 60 giây.</p>
            <Button variant="outline" size="touch" :loading="form.processing" :disabled="!form.email" @click="submit">Gửi lại link</Button>
            <TextLink :href="route('login')" class="text-sm font-medium">Quay lại đăng nhập</TextLink>
        </div>

        <form v-else class="grid gap-5" @submit.prevent="submit">
            <FormField label="Email" input-id="email" :error="form.errors.email">
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    autofocus
                    class="h-11 rounded-[10px]"
                    placeholder="ten@shop.vn"
                />
            </FormField>
            <Button type="submit" size="touch" class="w-full" :loading="form.processing">Gửi link đặt lại</Button>
            <TextLink :href="route('login')" class="text-center text-sm font-medium">Quay lại đăng nhập</TextLink>
        </form>
    </AuthLayout>
</template>
