<script setup lang="ts">
import PetAlertBadges from '@/components/PetAlertBadges.vue';
import type { CalendarBlock, Group } from '@/types/calendar';
import { BellOff, Check, Link2 } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 | Design 3.5 "Calendar block": left border = staff colour, fill = service group, line 1 time + pets,
 | line 2 customer, hatched tail = buffer. Position and size come from the grid through `style`.
 */
const props = defineProps<{ block: CalendarBlock; color?: string | null; bufferPx: number; draggable?: boolean }>();

const tone: Record<Group, string> = { medical: 'bg-primary-soft border-primary/30', grooming: 'bg-coral/15 border-coral/40' };
const done = computed(() => props.block.line_status === 'cancelled' || props.block.line_status === 'no_show');
const alerts = computed(() => props.block.pets.filter((p) => p.alerts.length));
</script>

<template>
    <button
        type="button"
        class="absolute overflow-hidden rounded-md border border-l-4 px-1.5 py-1 text-left text-xs shadow-sm transition hover:z-10 hover:shadow-md focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary"
        :class="[tone[block.group], { 'line-through opacity-50': done }]"
        :style="{ borderLeftColor: color || 'hsl(var(--muted-foreground))' }"
        :draggable="draggable"
        :title="block.pets.map((p) => `${p.name}: ${p.service}`).join('\n')"
    >
        <span v-if="bufferPx > 0" class="hatched pointer-events-none absolute inset-x-0 bottom-0" :style="{ height: `${bufferPx}px` }" />
        <span class="relative flex items-center gap-1 font-medium">
            <Check v-if="block.line_status === 'checked_in'" class="size-3 shrink-0 text-success" aria-label="Đã đến" />
            <span class="truncate">{{ block.start }} · {{ block.pets.map((p) => p.name).join(', ') }}</span>
            <Link2 v-if="block.linked" class="size-3 shrink-0" aria-label="Lịch có cả khám và spa" />
            <BellOff v-if="block.no_reminders" class="size-3 shrink-0" aria-label="Không nhắc" />
        </span>
        <span class="relative block truncate text-muted-foreground">{{ block.customer }}</span>
        <span v-if="block.source === 'online'" class="relative mr-1 inline-block rounded bg-primary-soft px-1 text-[10px] font-medium text-primary"
            >Online</span
        >
        <span v-if="block.preferred && !block.staff_id" class="relative block truncate text-[10px] text-muted-foreground"
            >Mong muốn: {{ block.preferred }}</span
        >
        <PetAlertBadges v-for="(p, i) in alerts" :key="i" class="relative mt-0.5" :alerts="p.alerts" compact />
    </button>
</template>

<style scoped>
.hatched {
    background-image: repeating-linear-gradient(45deg, hsl(var(--muted-foreground) / 0.18) 0 4px, transparent 4px 8px);
}
</style>
