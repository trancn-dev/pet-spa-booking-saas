<script setup lang="ts">
/** Design K0 "Segmented": Ngày / Tuần / Theo nhân viên. Generic over the value type. */
defineProps<{ options: { value: string; label: string }[]; label?: string }>();
const model = defineModel<string>({ required: true });
</script>

<template>
    <div class="inline-flex gap-0.5 rounded-[10px] bg-surface-muted p-[3px]" role="tablist" :aria-label="label">
        <button
            v-for="o in options"
            :key="o.value"
            type="button"
            role="tab"
            :aria-selected="model === o.value"
            class="rounded-lg px-3.5 py-1.5 text-[13px] font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :class="model === o.value ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            @click="model = o.value"
        >
            {{ o.label }}
        </button>
    </div>
</template>
