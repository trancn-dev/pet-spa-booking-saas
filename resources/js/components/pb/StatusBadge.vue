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
    // Visit (design 3.1): waiting muted, in progress info, done success
    waiting: ['neutral', 'Đang chờ'],
    in_progress: ['info', 'Đang thực hiện'],
    done: ['success', 'Hoàn tất'],
    // Invoice: draft = unfinished (warning), completed = success, void = struck through
    draft: ['warning', 'Chưa hoàn thành'],
    completed: ['success', 'Hoàn thành'],
    void: ['neutral', 'Đã hủy'],
};
const tone = computed(() => tones[props.status]?.[0] ?? 'neutral');
const text = computed(() => props.label ?? tones[props.status]?.[1] ?? props.status);
</script>

<template>
    <Badge :tone="tone" :class="{ 'line-through': status === 'cancelled' || status === 'void' }">{{ text }}</Badge>
</template>
