<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';

/** Design S1 toolbar filter: a bordered select whose label sits inside ("Nhân viên: Tất cả"). Options as { value, label }. */
defineProps<{ label: string; options: { value: string | number; label: string }[] }>();
const model = defineModel<string | number>({ required: true });
</script>

<template>
    <label
        class="relative inline-flex h-10 items-center rounded-[10px] border bg-card pl-3 pr-8 text-sm focus-within:ring-2 focus-within:ring-primary"
    >
        <span class="mr-1 text-muted-foreground">{{ label }}:</span>
        <select v-model="model" class="appearance-none bg-transparent font-medium focus:outline-none" :aria-label="label">
            <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
        <ChevronDown class="pointer-events-none absolute right-2.5 size-4 text-muted-foreground" aria-hidden="true" />
    </label>
</template>
