<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed } from 'vue';

/*
| Design K0 "Badge": pill, 12/16, weight 600. Status, service group, role, "Online", "Cấp cứu".
| Colour is never the only signal: keep the text, optionally a leading `dot`.
*/

export type BadgeTone = 'neutral' | 'primary' | 'info' | 'coral' | 'success' | 'warning' | 'danger' | 'violet' | 'dark';

const props = withDefaults(defineProps<{ tone?: BadgeTone; square?: boolean; dot?: boolean; class?: string }>(), { tone: 'neutral', square: false, dot: false });

const tones: Record<BadgeTone, string> = {
    neutral: 'bg-surface-muted text-stone-700 dark:text-stone-300',
    primary: 'bg-primary-soft text-teal-900 dark:text-teal-200',
    info: 'bg-sky-100 text-sky-900 dark:bg-sky-500/15 dark:text-sky-300',
    coral: 'bg-coral/20 text-orange-950 dark:bg-coral/15 dark:text-coral',
    success: 'bg-green-100 text-green-900 dark:bg-green-500/15 dark:text-green-300',
    warning: 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-300',
    danger: 'bg-red-100 text-red-900 dark:bg-red-500/15 dark:text-red-300',
    violet: 'bg-violet-100 text-violet-900 dark:bg-violet-500/15 dark:text-violet-300',
    dark: 'bg-stone-700 text-white dark:bg-stone-200 dark:text-stone-900',
};

const classes = computed(() => cn('inline-flex items-center gap-1.5 whitespace-nowrap px-2.5 py-0.5 text-xs font-semibold leading-4', props.square ? 'rounded-md' : 'rounded-full', tones[props.tone], props.class));
</script>

<template>
    <span :class="classes">
        <span v-if="dot" class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        <slot />
    </span>
</template>
