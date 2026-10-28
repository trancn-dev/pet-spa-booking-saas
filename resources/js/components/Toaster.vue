<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import { X } from 'lucide-vue-next';

const { toasts, dismiss } = useToast();
const tone = {
    info: 'border-info/40',
    success: 'border-success/40',
    warning: 'border-warning/50 bg-warning/10',
    danger: 'border-danger/50 bg-danger/10',
} as const;
</script>

<template>
    <div class="pointer-events-none fixed bottom-4 right-4 z-[100] flex w-[22rem] max-w-[calc(100vw-2rem)] flex-col gap-2" aria-live="polite" role="status">
        <div
            v-for="t in toasts"
            :key="t.id"
            class="pointer-events-auto flex items-start gap-3 rounded-[10px] border bg-card px-4 py-3 text-sm shadow-lg"
            :class="tone[t.tone]"
        >
            <span class="flex-1">{{ t.message }}</span>
            <button v-if="t.action" type="button" class="font-medium text-primary hover:underline" @click="t.action.run(); dismiss(t.id)">
                {{ t.action.label }}
            </button>
            <button type="button" aria-label="Đóng" class="text-muted-foreground" @click="dismiss(t.id)"><X class="size-4" /></button>
        </div>
    </div>
</template>
