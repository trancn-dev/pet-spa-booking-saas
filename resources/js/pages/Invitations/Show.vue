<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    state: 'pending' | 'accepted' | 'revoked' | 'expired' | 'invalid';
    message: string;
    token?: string;
    shop?: { name: string; slug: string };
    roles?: string[];
    email?: string;
    hasAccount?: boolean;
    signedInAs?: string | null;
}>();

const form = useForm({ name: '', password: '', password_confirmation: '' });

const roleText = computed(() => (props.roles ?? []).join(', ') || 'nhân viên');
const wrongAccount = computed(() => !!props.signedInAs && props.signedInAs !== props.email);
const errors = computed(() => form.errors as Record<string, string | undefined>);

const accept = () => {
    if (!props.token) return;
    form.post(route('invitations.accept', props.token), { onFinish: () => form.reset('password', 'password_confirmation') });
};
</script>

<template>
    <AuthBase
        :title="shop ? shop.name : 'Lời mời'"
        :description="state === 'pending' ? `Bạn được mời làm ${roleText} tại ${shop?.name}` : ''"
    >
        <Head title="Nhận lời mời" />

        <!-- Expired / revoked / used / unknown link -->
        <div v-if="state !== 'pending'" class="flex flex-col gap-4 text-center">
            <p class="text-sm text-muted-foreground">{{ message }}</p>
            <TextLink :href="route('login')">Về trang đăng nhập</TextLink>
        </div>

        <!-- Signed in with the invited e-mail: one click -->
        <form v-else-if="signedInAs && !wrongAccount" class="flex flex-col gap-4" @submit.prevent="accept">
            <p class="text-center text-sm text-muted-foreground">Đang đăng nhập: {{ signedInAs }}</p>
            <InputError :message="errors.email ?? errors.invitation" />
            <Button type="submit" class="w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Nhận lời mời
            </Button>
        </form>

        <!-- Signed in as somebody else -->
        <div v-else-if="wrongAccount" class="flex flex-col gap-4 text-center">
            <p class="text-sm text-destructive">
                Lời mời này dành cho {{ email }}, bạn đang đăng nhập bằng {{ signedInAs }}. Đăng xuất rồi đăng nhập đúng email được mời.
            </p>
            <TextLink :href="route('logout')" method="post" as="button">Đăng xuất</TextLink>
        </div>

        <!-- Account exists: sign in first, the login page brings them back here -->
        <div v-else-if="hasAccount" class="flex flex-col gap-4 text-center">
            <p class="text-sm text-muted-foreground">{{ email }} đã có tài khoản PawBook.</p>
            <Button as-child class="w-full">
                <a :href="route('login')">Đăng nhập để nhận</a>
            </Button>
        </div>

        <!-- No account yet: create it here -->
        <form v-else class="flex flex-col gap-6" @submit.prevent="accept">
            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input id="email" type="email" :model-value="email" disabled />
            </div>
            <div class="grid gap-2">
                <Label for="name">Họ tên</Label>
                <Input id="name" v-model="form.name" required autofocus autocomplete="name" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="password">Mật khẩu</Label>
                <Input id="password" v-model="form.password" type="password" required autocomplete="new-password" />
                <InputError :message="form.errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">Nhập lại mật khẩu</Label>
                <Input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" />
            </div>
            <InputError :message="errors.email ?? errors.invitation" />
            <Button type="submit" class="w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                Tạo tài khoản và nhận lời mời
            </Button>
        </form>
    </AuthBase>
</template>
