<script setup lang="ts">
/** Design K0 "Empty state": soft icon disc, one sentence, at most one action (slot `action`). Skeletons live in `ui/skeleton`. */
withDefaults(defineProps<{ title?: string; compact?: boolean }>(), { compact: false });
</script>

<template>
    <div
        class="flex flex-col items-center gap-2 rounded-[10px] border border-dashed text-center text-sm text-muted-foreground"
        :class="compact ? 'p-6' : 'px-6 py-12'"
    >
        <span v-if="$slots.icon" class="mb-1 grid size-12 place-items-center rounded-full bg-primary-soft text-primary" aria-hidden="true"
            ><slot name="icon"
        /></span>
        <p v-if="title" class="font-medium text-foreground">{{ title }}</p>
        <p v-if="$slots.default"><slot /></p>
        <div v-if="$slots.action" class="mt-1"><slot name="action" /></div>
    </div>
</template>
