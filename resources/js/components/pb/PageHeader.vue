<script setup lang="ts">
import type { ShopSharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Design "head": h1 24/32 600, optional sub line, actions on the right (`actions` slot), `before` for a back link.
 * `branch`: operational screens show the open branch next to the title (design 10, rule 1).
 */
defineProps<{ title: string; subtitle?: string; branch?: boolean }>();
const currentBranch = computed(() => usePage<ShopSharedData>().props.currentBranch);
</script>

<template>
    <header class="flex flex-wrap items-center gap-3">
        <div class="min-w-[14rem] flex-1">
            <slot name="before" />
            <h1 class="flex flex-wrap items-center gap-2 text-2xl font-semibold leading-8">
                <span class="min-w-0">{{ title }}</span>
                <slot name="badges" />
                <span
                    v-if="branch && currentBranch"
                    class="rounded-md border bg-surface-muted px-2 py-0.5 text-xs font-medium text-stone-700 dark:text-stone-300"
                    >{{ currentBranch.name }}</span
                >
            </h1>
            <p v-if="subtitle || $slots.subtitle" class="mt-0.5 text-sm text-muted-foreground">
                <slot name="subtitle">{{ subtitle }}</slot>
            </p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
    </header>
</template>
