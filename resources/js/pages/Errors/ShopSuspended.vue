<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Lock } from 'lucide-vue-next';

defineProps<{
    message: string;
    reason: string | null;
}>();
</script>

<template>
    <Head title="Shop đang tạm khóa" />

    <AppLayout>
        <div class="flex flex-col items-center gap-3 px-6 py-24 text-center">
            <Lock class="size-10 text-danger" />
            <h1 class="text-xl font-semibold">Shop đang tạm khóa</h1>
            <p class="max-w-md text-sm text-muted-foreground">{{ message }}</p>
            <p v-if="reason" class="text-sm">Lý do: {{ reason }}</p>
            <Button variant="outline" @click="router.visit(route('app.dashboard'))">Về Tổng quan</Button>
        </div>
    </AppLayout>
</template>
