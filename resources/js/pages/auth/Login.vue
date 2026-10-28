<script setup lang="ts">
import FormField from '@/components/pb/FormField.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: true,
});
const reveal = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase title="Đăng nhập" description="Dùng email nhân viên đã được mời vào shop.">
        <Head title="Đăng nhập" />

        <p
            v-if="status"
            class="mb-4 rounded-[10px] bg-green-100 px-4 py-3 text-sm text-green-900 dark:bg-green-500/15 dark:text-green-200"
            role="status"
        >
            {{ status }}
        </p>

        <form class="grid gap-5" @submit.prevent="submit">
            <FormField label="Email" input-id="email" :error="form.errors.email">
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    tabindex="1"
                    class="h-11 rounded-[10px]"
                    placeholder="ten@shop.vn"
                />
            </FormField>

            <FormField label="Mật khẩu" input-id="password" :error="form.errors.password">
                <div class="relative">
                    <Input
                        id="password"
                        v-model="form.password"
                        :type="reveal ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        tabindex="2"
                        class="h-11 rounded-[10px] pr-11"
                    />
                    <button
                        type="button"
                        class="absolute right-2 top-1/2 grid size-8 -translate-y-1/2 place-items-center rounded-md text-muted-foreground hover:bg-surface-muted"
                        :aria-label="reveal ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
                        :aria-pressed="reveal"
                        @click="reveal = !reveal"
                    >
                        <EyeOff v-if="reveal" class="size-4" /><Eye v-else class="size-4" />
                    </button>
                </div>
            </FormField>

            <div class="flex items-center justify-between gap-3 text-sm">
                <Label for="remember" class="flex items-center gap-2 font-normal">
                    <Checkbox id="remember" v-model:checked="form.remember" tabindex="3" />
                    Ghi nhớ đăng nhập
                </Label>
                <TextLink v-if="canResetPassword" :href="route('password.request')" tabindex="5" class="font-medium">Quên mật khẩu?</TextLink>
            </div>

            <Button type="submit" size="touch" class="w-full" tabindex="4" :loading="form.processing">Đăng nhập</Button>
        </form>

        <p class="mt-6 border-t pt-6 text-sm text-muted-foreground">Chưa có tài khoản? Dùng link mời trong email từ chủ shop.</p>
    </AuthBase>
</template>
