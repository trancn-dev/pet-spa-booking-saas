<script setup lang="ts">
import PageHeader from '@/components/pb/PageHeader.vue';
import SettingsLayout from '@/components/shell/SettingsLayout.vue';
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
        <SettingsLayout>
            <div class="flex flex-col gap-6">
                <PageHeader
                    title="Lịch sử truy cập hỗ trợ"
                    subtitle="Mỗi lần nhân viên PawBook đăng nhập thay để hỗ trợ: tối đa 30 phút, có lý do, mọi thao tác được ghi lại."
                />
                <SupportSessionsTable :sessions="sessions" :timezone="timezone" />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
