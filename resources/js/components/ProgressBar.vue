<script setup lang="ts">
import { computed } from 'vue';

/** Thin progress bar (checklists, quota usage). */
const props = defineProps<{ value: number; max: number; label?: string; dangerAtMax?: boolean }>();
const percent = computed(() => (props.max > 0 ? Math.min(100, (props.value / props.max) * 100) : 0));
</script>

<template>
    <div class="h-1.5 rounded-full bg-muted" role="progressbar" :aria-valuenow="value" :aria-valuemin="0" :aria-valuemax="max" :aria-label="label">
        <div
            class="h-1.5 rounded-full transition-all"
            :class="dangerAtMax && value >= max ? 'bg-danger' : 'bg-primary'"
            :style="{ width: `${percent}%` }"
        />
    </div>
</template>
