<script setup lang="ts">
import type { CapacityCell } from '@/types/calendar';
import { computed } from 'vue';

/** Design 3.5 "Capacity bar": "Khám 2/3 · Spa 1/2" (bookings / people on duty); over = warning border. */
const props = defineProps<{ cell: CapacityCell }>();
const over = computed(() => props.cell.medical.taken > props.cell.medical.on_duty || props.cell.grooming.taken > props.cell.grooming.on_duty);
</script>

<template>
    <div
        class="mt-0.5 rounded border px-1 tabular-nums"
        :class="over ? 'border-warning bg-warning/10 font-semibold text-warning' : 'border-transparent text-muted-foreground'"
        :title="over ? 'Vượt số người trực' : 'Lịch đang chiếm chỗ / người trực'"
    >
        Khám {{ cell.medical.taken }}/{{ cell.medical.on_duty }}<br />Spa {{ cell.grooming.taken }}/{{ cell.grooming.on_duty }}
    </div>
</template>
