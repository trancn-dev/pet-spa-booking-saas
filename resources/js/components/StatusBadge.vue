<script setup lang="ts">
import { Badge, type BadgeTone } from '@/components/ui/badge';
import { computed } from 'vue';

/** Appointment / line status (design 3.1): the label always comes with the colour. */
const props = defineProps<{ status: string; label?: string }>();

const tones: Record<string, [BadgeTone, string]> = {
    confirmed: ['primary', 'Đã xác nhận'],
    booked: ['primary', 'Đã đặt'],
    checked_in: ['info', 'Đã đến'],
    cancelled: ['neutral', 'Đã hủy'],
    no_show: ['danger', 'Không đến'],
};
const tone = computed(() => tones[props.status]?.[0] ?? 'neutral');
const text = computed(() => props.label ?? tones[props.status]?.[1] ?? props.status);
</script>

<template>
    <Badge :tone="tone" :class="{ 'line-through': status === 'cancelled' }">{{ text }}</Badge>
</template>
