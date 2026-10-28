<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
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
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
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
