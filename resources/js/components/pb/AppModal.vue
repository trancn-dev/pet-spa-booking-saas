<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { X } from 'lucide-vue-next';

/*
| Design K0 "Modal": confirm cancel / reschedule / edit invoice. 480px, radius 16, title 18/600 + close, body,
| right-aligned footer (`footer` slot). Esc and the X always close it.
*/
withDefaults(defineProps<{ title: string; description?: string; width?: string }>(), { width: '30rem' });
const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="gap-0 rounded-2xl p-6 [&>button]:hidden" :style="{ maxWidth: width }">
            <div class="mb-4 flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    <DialogTitle class="text-lg font-semibold leading-7">{{ title }}</DialogTitle>
                    <DialogDescription v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</DialogDescription>
                    <DialogDescription v-else class="sr-only">{{ title }}</DialogDescription>
                </div>
                <button type="button" class="rounded p-1 text-muted-foreground hover:bg-surface-muted" aria-label="Đóng" @click="open = false">
                    <X class="size-5" />
                </button>
            </div>
            <slot />
            <div v-if="$slots.footer" class="mt-6 flex flex-wrap justify-end gap-2"><slot name="footer" /></div>
        </DialogContent>
    </Dialog>
</template>
