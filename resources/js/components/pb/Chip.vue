<script setup lang="ts">
/** Selectable chip (pet chips, slot chips): `selected` = primary, `dashed` = "add" action. */
withDefaults(defineProps<{ selected?: boolean; dashed?: boolean; tone?: 'default' | 'warning' }>(), {
    selected: false,
    dashed: false,
    tone: 'default',
});
</script>

<template>
    <button
        type="button"
        class="rounded-full border px-3 py-1 text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
        :class="[
            selected
                ? 'border-primary bg-primary-soft text-primary'
                : tone === 'warning'
                  ? 'border-warning/60 bg-warning/10'
                  : 'hover:bg-surface-muted',
            dashed ? 'border-dashed text-muted-foreground' : '',
        ]"
        :aria-pressed="selected"
    >
        <slot />
    </button>
</template>
