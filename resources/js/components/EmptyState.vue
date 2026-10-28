<script setup lang="ts">
/** Design 3.5 "Empty state": one sentence and at most one action (slot `action`). */
withDefaults(defineProps<{ title?: string; compact?: boolean }>(), { compact: false });
</script>

<template>
    <div
        class="flex flex-col items-center gap-2 rounded-[10px] border border-dashed text-center text-sm text-muted-foreground"
        :class="compact ? 'p-6' : 'px-6 py-12'"
    >
        <slot name="icon" />
        <p v-if="title" class="font-medium text-foreground">{{ title }}</p>
        <p v-if="$slots.default"><slot /></p>
        <slot name="action" />
    </div>
</template>
