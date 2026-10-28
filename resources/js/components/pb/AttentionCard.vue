<script setup lang="ts">
/** Design K0 "Card · cảnh báo": a coral-tinted card for "Cần xử lý" (unfinished invoices, overdue vaccines) with a link slot. */
defineProps<{ title: string }>();
</script>

<template>
    <div class="rounded-xl border border-coral/50 bg-coral/10 p-5">
        <div class="text-sm font-semibold">{{ title }}</div>
        <div class="mt-1 text-sm"><slot /></div>
        <div v-if="$slots.action" class="mt-3 text-sm font-semibold text-primary"><slot name="action" /></div>
    </div>
</template>
