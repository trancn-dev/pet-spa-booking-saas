<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import type { BreadcrumbItemType, ShopSharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage<ShopSharedData>();
const status = computed(() => page.props.flash?.status);
const notice = computed(() => page.props.planNotice);
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <ImpersonationBanner />
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div
                v-if="notice"
                role="alert"
                class="mx-4 mt-4 rounded-md border px-4 py-2 text-sm"
                :class="notice.level === 'danger' ? 'border-danger/30 bg-danger/10 text-danger' : 'border-warning/30 bg-warning/10 text-warning'"
            >
                {{ notice.text }}
            </div>
            <div
                v-if="status"
                role="status"
                class="mx-4 mt-4 rounded-md border border-success/30 bg-success/10 px-4 py-2 text-sm text-success"
            >
                {{ status }}
            </div>
            <div class="mx-auto w-full max-w-[1280px]">
                <slot />
            </div>
        </AppContent>
    </AppShell>
</template>
