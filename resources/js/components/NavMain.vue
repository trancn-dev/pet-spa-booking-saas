<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { NavGroup } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    groups: NavGroup[];
}>();
</script>

<template>
    <SidebarGroup v-for="(group, index) in groups" :key="group.label ?? index" class="px-2 py-0">
        <SidebarGroupLabel v-if="group.label">{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton as-child :is-active="item.isActive" :tooltip="item.title" class="data-[active=true]:bg-primary-soft data-[active=true]:font-semibold data-[active=true]:text-foreground data-[active=true]:shadow-[inset_3px_0_0_hsl(var(--color-primary))]">
                    <Link :href="item.href">
                        <component :is="item.icon" v-if="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
