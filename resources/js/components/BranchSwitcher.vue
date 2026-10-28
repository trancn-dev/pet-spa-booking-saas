<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { ShopSharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, MapPin } from 'lucide-vue-next';
import { computed } from 'vue';

// Topbar branch ▾ (design 3.4, after the shop chip): only branches the person works at.
const page = usePage<ShopSharedData>();
const tenant = computed(() => page.props.tenant);
const branches = computed(() => page.props.branches ?? []);
const current = computed(() => page.props.currentBranch);

const choose = (id: string) => {
    if (id !== current.value?.id) router.post(route('app.branch.switch', id), {}, { preserveScroll: true });
};
</script>

<template>
    <div v-if="tenant" class="flex min-w-0 items-center gap-1 text-sm">
        <DropdownMenu v-if="branches.length > 1">
            <DropdownMenuTrigger class="flex items-center gap-1 rounded-md px-2 py-1 hover:bg-accent">
                <MapPin class="size-4 text-primary" />
                <span class="truncate">{{ current?.name ?? 'Chọn cơ sở' }}</span>
                <ChevronDown class="size-4 text-muted-foreground" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="min-w-56">
                <DropdownMenuLabel class="text-xs text-muted-foreground">Cơ sở đang làm việc</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem v-for="b in branches" :key="b.id" class="gap-2" @select="choose(b.id)">
                    <span class="font-mono text-xs text-muted-foreground">{{ b.code }}</span>
                    <span class="flex-1 truncate">{{ b.name }}</span>
                    <Check v-if="b.id === current?.id" class="size-4 text-primary" />
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
        <span v-else-if="current" class="flex items-center gap-1 px-2 py-1"><MapPin class="size-4 text-primary" /> {{ current.name }}</span>
    </div>
</template>
