<script setup lang="ts">
/** Design K0 "Timeline": dot + "16/10/2026 · CN Quận 3" caption + bold title + optional body (default slot). */
defineProps<{ caption?: string; title?: string }>();
</script>

<template>
    <li class="relative grid gap-1 rounded-[10px] border bg-card p-4">
        <span class="absolute -left-[1.85rem] top-5 size-3 rounded-full border-2 border-background bg-primary" aria-hidden="true" />
        <p v-if="caption" class="text-xs tabular-nums text-muted-foreground">{{ caption }}</p>
        <p v-if="title" class="text-sm font-semibold">{{ title }}</p>
        <slot />
    </li>
</template>
