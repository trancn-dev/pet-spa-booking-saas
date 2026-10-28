<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import Banner from '@/components/pb/Banner.vue';
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
            <Banner v-if="notice" :tone="notice.level === 'danger' ? 'danger' : 'warning'" class="mx-4 mt-4">{{ notice.text }}</Banner>
            <Banner v-if="status" tone="success" class="mx-4 mt-4">{{ status }}</Banner>
            <div class="mx-auto w-full max-w-[1280px]">
                <slot />
            </div>
        </AppContent>
    </AppShell>
</template>
