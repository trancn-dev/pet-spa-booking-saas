<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { ShopSharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

// Task 4.6: red banner with a countdown while a PawBook admin acts as the owner.
const page = usePage<ShopSharedData>();
const session = computed(() => page.props.impersonating);
const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => (timer = setInterval(() => (now.value = Date.now()), 1000)));
onBeforeUnmount(() => clearInterval(timer));

const left = computed(() => {
    if (!session.value) return '';
    const ms = Math.max(0, new Date(session.value.expires_at).getTime() - now.value);
    if (ms === 0) router.reload();
    const m = Math.floor(ms / 60000);
    const s = Math.floor((ms % 60000) / 1000);
    return `${m}:${String(s).padStart(2, '0')}`;
});

const exit = () => router.post(route('impersonation.exit'));
</script>

<template>
    <div v-if="session" role="alert" class="flex flex-wrap items-center gap-3 bg-danger px-4 py-2 text-sm text-white">
        <ShieldAlert class="size-4 shrink-0" />
        <span class="flex-1">
            <strong>{{ session.admin }}</strong> (PawBook) đang đăng nhập thay chủ shop {{ session.shop }} · còn
            <span class="font-mono tabular-nums">{{ left }}</span>. Mọi thao tác được ghi lại. {{ session.blocked }}
        </span>
        <Button size="sm" variant="secondary" @click="exit">Thoát</Button>
    </div>
</template>
