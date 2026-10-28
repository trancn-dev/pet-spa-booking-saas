<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed } from 'vue';

/*
| Design 3.5 "Badge": status, service group, role, "Online", "Cấp cứu"… Never colour alone: pass an
| icon in the default slot or keep the text.
*/

export type BadgeTone = 'neutral' | 'primary' | 'info' | 'coral' | 'success' | 'warning' | 'danger';

const props = withDefaults(defineProps<{ tone?: BadgeTone; square?: boolean; class?: string }>(), { tone: 'neutral', square: false });

const tones: Record<BadgeTone, string> = {
    neutral: 'bg-surface-muted text-muted-foreground',
    primary: 'bg-primary-soft text-primary',
    info: 'bg-info/10 text-info',
    coral: 'bg-coral/10 text-coral',
    success: 'bg-success/10 text-success',
    warning: 'bg-warning/10 text-warning',
    danger: 'bg-danger/10 text-danger',
};

const classes = computed(() => cn('inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium', props.square ? 'rounded-md' : 'rounded-full', tones[props.tone], props.class));
</script>

<template>
    <span :class="classes"><slot /></span>
</template>
