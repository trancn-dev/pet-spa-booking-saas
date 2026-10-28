<script setup lang="ts">
import SupportSessionsTable, { type SupportSession } from '@/components/SupportSessionsTable.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    sessions: SupportSession[];
    timezone: string;
}>();
</script>

<template>
    <Head title="Lịch sử truy cập hỗ trợ" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div>
                <h1 class="text-2xl font-semibold">Lịch sử truy cập hỗ trợ</h1>
                <p class="text-sm text-muted-foreground">
                    Mỗi lần nhân viên PawBook đăng nhập thay để hỗ trợ: tối đa 30 phút, có lý do, mọi thao tác được ghi lại.
                </p>
            </div>
            <SupportSessionsTable :sessions="sessions" :timezone="timezone" />
        </div>
    </AppLayout>
</template>
