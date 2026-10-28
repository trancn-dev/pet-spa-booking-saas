<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ShopSharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

/* Design K1 topbar: "PH Paw House Spa & Vet ▾". The open shop is always visible (rule 1); people in several shops switch here. */
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
    <DropdownMenu v-if="tenant">
        <DropdownMenuTrigger
            class="flex min-w-0 items-center gap-2 rounded-[10px] px-2 py-1 text-sm font-semibold hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :disabled="workspaces.length < 2"
        >
            <span class="grid size-6 shrink-0 place-items-center rounded-md bg-coral/20 text-[10px] font-bold text-orange-900 dark:text-coral">{{
                initials
            }}</span>
            <span class="truncate">{{ tenant.name }}</span>
            <ChevronDown v-if="workspaces.length > 1" class="size-4 shrink-0 text-muted-foreground" />
        </DropdownMenuTrigger>
        <DropdownMenuContent v-if="workspaces.length > 1" class="min-w-56" align="start">
            <DropdownMenuLabel class="text-xs text-muted-foreground">Chuyển shop</DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem v-for="w in workspaces" :key="w.slug" as-child>
                <Link :href="route('app.dashboard', { tenant: w.slug })" class="flex w-full items-center gap-2">
                    <span class="flex-1 truncate">{{ w.name }}</span>
                    <Check v-if="w.slug === tenant.slug" class="size-4 text-primary" />
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
