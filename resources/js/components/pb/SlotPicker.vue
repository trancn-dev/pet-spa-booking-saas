<script setup lang="ts">
/*
| Design K0 "Slot picker": 15-minute chips with "còn N chỗ"; a full slot is struck through and disabled; `over` (staff
| booking beyond duty count) gets the warning border but stays selectable. Site passes `touch` for the 44px size.
*/
export interface SlotOption {
    time: string;
    /** second line, e.g. "còn 2 chỗ" */
    caption?: string;
    disabled?: boolean;
    over?: boolean;
    title?: string;
}
defineProps<{ slots: SlotOption[]; touch?: boolean; label?: string }>();
const model = defineModel<string | null>({ default: null });
</script>

<template>
    <div class="grid grid-cols-4 gap-1.5" role="listbox" :aria-label="label ?? 'Giờ hẹn'">
        <button
            v-for="s in slots"
            :key="s.time"
            type="button"
            role="option"
            :aria-selected="model === s.time"
            :disabled="s.disabled"
            :title="s.title"
            class="flex flex-col items-center justify-center rounded-[10px] border px-1 text-xs tabular-nums transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-50"
            :class="[
                touch ? 'min-h-11 py-1.5 text-sm' : 'py-1',
                model === s.time
                    ? 'border-primary bg-primary-soft font-semibold text-primary ring-1 ring-primary'
                    : s.over
                      ? 'border-warning/60 bg-warning/10'
                      : 'hover:bg-surface-muted',
                { 'line-through': s.disabled },
            ]"
            @click="model = s.time"
        >
            <span class="font-medium">{{ s.time }}</span>
            <span v-if="s.caption" class="text-[10px] leading-3 opacity-80">{{ s.caption }}</span>
        </button>
        <p v-if="!slots.length" class="col-span-4 text-xs text-muted-foreground"><slot name="empty">Không có khung giờ.</slot></p>
    </div>
</template>
