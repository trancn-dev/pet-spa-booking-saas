<script setup lang="ts">
import { computed } from 'vue';

/** Design S15/S16: the big queue number "042" (mono, tabular). `emergency` = danger tint. */
const props = withDefaults(defineProps<{ no: number; size?: 'md' | 'lg'; emergency?: boolean }>(), { size: 'md' });
const text = computed(() => String(props.no).padStart(3, '0'));
</script>

<template>
    <span
        class="inline-block rounded-xl border-2 px-3 font-mono font-semibold tabular-nums"
        :class="[
            size === 'lg' ? 'text-6xl leading-[1.3]' : 'text-2xl leading-9',
            emergency ? 'border-danger text-danger' : 'border-primary text-primary',
        ]"
        :aria-label="`Số ${text}`"
        >{{ text }}</span
    >
</template>
