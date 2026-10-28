<script setup lang="ts">
import { ChevronLeft } from 'lucide-vue-next';

/** Design S6 step header: 44px back target + the step title (e.g. "Chọn giờ") + "Bước 4/5". */
defineProps<{ title: string; step?: string; backLabel?: string }>();
defineEmits<{ back: [] }>();
</script>

<template>
    <header class="flex h-[60px] flex-none items-center gap-1 border-b bg-card px-2">
        <button
            type="button"
            class="grid size-11 place-items-center rounded-full hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :aria-label="backLabel ?? 'Quay lại'"
            @click="$emit('back')"
        >
            <ChevronLeft class="size-6" />
        </button>
        <h1 class="flex-1 text-lg font-semibold">{{ title }}</h1>
        <span v-if="step" class="pr-3 text-sm tabular-nums text-muted-foreground">{{ step }}</span>
    </header>
</template>
