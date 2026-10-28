<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import { AlertCircle, CheckCircle2, Info, X } from 'lucide-vue-next';

/* Design K0 "Toast": dark pill bottom right, 5 s, optional underlined action ("Hoàn tác"). aria-live. */
const { toasts, dismiss } = useToast();
const icons = { info: Info, success: CheckCircle2, warning: AlertCircle, danger: AlertCircle } as const;
const iconTone = { info: 'text-sky-300', success: 'text-teal-300', warning: 'text-amber-300', danger: 'text-red-300' } as const;
</script>

<template>
    <div
        class="pointer-events-none fixed bottom-4 right-4 z-[100] flex w-[24rem] max-w-[calc(100vw-2rem)] flex-col gap-2"
        aria-live="polite"
        role="status"
    >
        <div
            v-for="t in toasts"
            :key="t.id"
            class="pointer-events-auto flex items-center gap-2.5 rounded-[10px] bg-stone-900 px-4 py-3 text-sm text-white shadow-lg dark:bg-stone-100 dark:text-stone-900"
        >
            <component :is="icons[t.tone]" class="size-[18px] shrink-0" :class="iconTone[t.tone]" aria-hidden="true" />
            <span class="flex-1">{{ t.message }}</span>
            <button
                v-if="t.action"
                type="button"
                class="font-semibold underline underline-offset-2"
                @click="
                    t.action.run();
                    dismiss(t.id);
                "
            >
                {{ t.action.label }}
            </button>
            <button type="button" aria-label="Đóng" class="opacity-70 hover:opacity-100" @click="dismiss(t.id)"><X class="size-4" /></button>
        </div>
    </div>
</template>
