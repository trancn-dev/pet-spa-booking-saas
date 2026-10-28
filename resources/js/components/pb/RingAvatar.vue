<script setup lang="ts">
import { computed } from 'vue';

/** Design K0 "Avatar": initials in a ring of the staff colour (calendar, rosters, topbar). `active` thickens the ring. */
const props = withDefaults(defineProps<{ initials: string; color?: string | null; size?: number; active?: boolean }>(), {
    color: null,
    size: 36,
    active: false,
});
const style = computed(() => ({
    width: `${props.size}px`,
    height: `${props.size}px`,
    fontSize: `${props.size < 32 ? 10 : 12}px`,
    borderColor: props.color ?? 'hsl(var(--color-primary))',
    borderWidth: props.active ? '3px' : '2px',
}));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-full border-solid bg-surface-muted font-semibold"
        :style="style"
        aria-hidden="true"
        >{{ initials }}</span
    >
</template>
