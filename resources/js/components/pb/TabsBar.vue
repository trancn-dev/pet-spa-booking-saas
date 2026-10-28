<script setup lang="ts">
/** Design K0 "Tabs": underlined tabs (record pages: Tổng quan · Lịch sử · Bệnh án · Tiêm phòng). `count` and `locked` supported. */
export interface TabItem {
    value: string;
    label: string;
    count?: number | null;
    locked?: boolean;
}
defineProps<{ items: TabItem[]; label?: string }>();
const model = defineModel<string>({ required: true });
</script>

<template>
    <div class="mb-4 flex gap-6 overflow-x-auto border-b" role="tablist" :aria-label="label">
        <button
            v-for="t in items"
            :key="t.value"
            type="button"
            role="tab"
            :aria-selected="model === t.value"
            class="-mb-px flex items-center gap-1.5 whitespace-nowrap border-b-2 py-2.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :class="
                model === t.value
                    ? 'border-primary font-semibold text-primary'
                    : 'border-transparent font-medium text-muted-foreground hover:text-foreground'
            "
            @click="model = t.value"
        >
            {{ t.label
            }}<span v-if="t.count != null" class="rounded-full bg-surface-muted px-1.5 text-xs tabular-nums text-muted-foreground">{{
                t.count
            }}</span>
            <slot v-if="t.locked" name="locked" />
        </button>
    </div>
</template>
