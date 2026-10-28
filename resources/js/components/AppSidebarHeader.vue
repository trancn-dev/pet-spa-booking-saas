<script setup lang="ts">
import BranchSwitcher from '@/components/BranchSwitcher.vue';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType, ShopSharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const page = usePage<ShopSharedData>();
const inShop = computed(() => !!page.props.tenant);
</script>

<template>
    <!-- Topbar 56px (design 3.4): shop › branch switcher inside a shop, breadcrumbs elsewhere. -->
    <header class="flex h-14 shrink-0 items-center gap-3 border-b border-sidebar-border/70 px-4">
        <SidebarTrigger class="-ml-1" />
        <BranchSwitcher v-if="inShop" />
        <template v-else-if="breadcrumbs && breadcrumbs.length > 0">
            <Breadcrumb>
                <BreadcrumbList>
                    <template v-for="(item, index) in breadcrumbs" :key="index">
                        <BreadcrumbItem>
                            <BreadcrumbPage v-if="index === breadcrumbs.length - 1">{{ item.title }}</BreadcrumbPage>
                            <BreadcrumbLink v-else :href="item.href">{{ item.title }}</BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                    </template>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
    </header>
</template>
