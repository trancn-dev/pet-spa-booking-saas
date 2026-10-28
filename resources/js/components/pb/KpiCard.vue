<script setup lang="ts">
import { computed } from 'vue';

/*
| Design K0 "Card · KPI": caption, big number (optionally "/ max"), delta line, tiny sparkline from `series`.
| Money and counts are formatted by the caller (tabular-nums).
*/
const props = defineProps<{
    label: string;
    value: string | number;
    max?: string | number;
    delta?: string;
    deltaTone?: 'up' | 'down' | 'flat';
    series?: number[];
}>();

const path = computed(() => {
    const s = props.series ?? [];
    if (s.length < 2) return '';
    const lo = Math.min(...s);
    const hi = Math.max(...s);
    const range = hi - lo || 1;
    return s.map((v, i) => `${i ? 'L' : 'M'}${(i / (s.length - 1)) * 100},${28 - ((v - lo) / range) * 24}`).join(' ');
});
const tone = { up: 'text-success', down: 'text-danger', flat: 'text-muted-foreground' } as const;
</script>

<template>
    <div class="rounded-xl border bg-card p-5">
        <div class="text-sm text-muted-foreground">{{ label }}</div>
        <div class="mt-1 flex items-baseline gap-1 tabular-nums">
            <span class="text-[32px] font-bold leading-10">{{ value }}</span>
            <span v-if="max != null" class="text-lg text-muted-foreground">/{{ max }}</span>
            <span v-if="delta" class="ml-1 text-xs font-medium" :class="tone[deltaTone ?? 'flat']">{{ delta }}</span>
        </div>
        <svg v-if="path" class="mt-2 h-7 w-full text-primary" viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true">
            <path
                :d="path"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                vector-effect="non-scaling-stroke"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
        </svg>
    </div>
</template>
