<script setup lang="ts">
/** Design Site chip: 36px pill (date strip, species, service filters). Selected = primary-soft + bold. */
defineProps<{ selected?: boolean; disabled?: boolean }>();
</script>

<template>
    <button
        type="button"
        class="inline-flex h-9 flex-none items-center whitespace-nowrap rounded-full border px-3.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
        :class="selected ? 'border-primary bg-primary-soft font-semibold' : 'font-medium hover:bg-surface-muted'"
        :disabled="disabled"
        :aria-pressed="!!selected"
    >
        <slot />
    </button>
</template>
