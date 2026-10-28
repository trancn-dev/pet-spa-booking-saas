<script setup lang="ts">
/** Design Site "ph": striped dashed box where a shop photo goes (service card, branch). Pass `src` to show the real image. */
withDefaults(defineProps<{ height?: number; label?: string; src?: string | null; alt?: string }>(), { height: 120, label: 'ảnh', alt: '' });
</script>

<template>
    <img v-if="src" :src="src" :alt="alt" class="w-full rounded-[14px] object-cover" :style="{ height: `${height}px` }" loading="lazy" />
    <div
        v-else
        class="flex items-center justify-center rounded-[14px] border border-dashed font-mono text-xs text-muted-foreground"
        :style="{
            height: `${height}px`,
            backgroundImage: 'repeating-linear-gradient(135deg, transparent 0 10px, hsl(var(--color-surface-muted)) 10px 11px)',
        }"
        role="img"
        :aria-label="alt || label"
    >
        {{ label }}
    </div>
</template>
