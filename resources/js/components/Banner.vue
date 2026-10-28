<script setup lang="ts">
import { computed } from 'vue';

/** Design 3.5 "Banner": plan quota, warnings, errors. `danger` / `warning` are announced as alerts. */
const props = withDefaults(defineProps<{ tone?: 'info' | 'success' | 'warning' | 'danger' }>(), { tone: 'info' });

const classes = {
    info: 'border-info/30 bg-info/10 text-info',
    success: 'border-success/30 bg-success/10 text-success',
    warning: 'border-warning/40 bg-warning/10 text-warning',
    danger: 'border-danger/40 bg-danger/10 text-danger',
} as const;
const role = computed(() => (props.tone === 'danger' || props.tone === 'warning' ? 'alert' : 'status'));
</script>

<template>
    <div :role="role" class="rounded-md border px-3 py-2 text-sm" :class="classes[tone]"><slot /></div>
</template>
