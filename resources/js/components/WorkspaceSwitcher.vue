<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { ShopSharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, PawPrint } from 'lucide-vue-next';
import { computed } from 'vue';

// The open shop's name is always visible (F18); people working at several shops switch here.
const page = usePage<ShopSharedData>();
const tenant = computed(() => page.props.tenant);
const workspaces = computed(() => page.props.workspaces ?? []);
const initials = computed(() =>
    (tenant.value?.name ?? 'P')
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton size="lg" class="data-[state=open]:bg-sidebar-accent">
                        <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-primary text-sm font-semibold text-primary-foreground">
                            <PawPrint v-if="!tenant" class="size-4" />
                            <span v-else>{{ initials }}</span>
                        </div>
                        <div class="grid flex-1 text-left text-sm leading-tight">
                            <span class="truncate font-semibold text-foreground">{{ tenant?.name ?? 'PawBook' }}</span>
                            <span class="truncate text-xs text-muted-foreground">PawBook</span>
                        </div>
                        <ChevronsUpDown v-if="workspaces.length > 1" class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent v-if="workspaces.length > 1" class="min-w-56" align="start">
                    <DropdownMenuLabel class="text-xs text-muted-foreground">Chuyển shop</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem v-for="w in workspaces" :key="w.slug" as-child>
                        <Link :href="route('app.dashboard', { tenant: w.slug })" class="flex w-full items-center gap-2">
                            <span class="flex-1 truncate">{{ w.name }}</span>
                            <Check v-if="w.slug === tenant?.slug" class="size-4 text-primary" />
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
