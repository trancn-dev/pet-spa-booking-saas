<script setup lang="ts">
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';

/*
| Design K0 "Drawer phải" (480px): header 18/600 + sub 13px, scrolling body, sticky `footer` with a soft top shadow.
| Detail of an appointment or pet without leaving the screen; Esc / overlay / X close it.
*/
defineProps<{ title?: string; description?: string }>();
const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:max-w-[480px]">
            <SheetHeader class="border-b px-6 py-5 text-left">
                <SheetTitle class="text-lg font-semibold leading-7"
                    ><slot name="header">{{ title }}</slot></SheetTitle
                >
                <SheetDescription v-if="description || $slots.description" class="text-[13px] leading-[18px]"
                    ><slot name="description">{{ description }}</slot></SheetDescription
                >
                <SheetDescription v-else class="sr-only">{{ title }}</SheetDescription>
            </SheetHeader>
            <div class="min-h-0 flex-1 overflow-y-auto"><slot /></div>
            <div v-if="$slots.footer" class="border-t bg-card px-6 py-4 shadow-[0_-4px_12px_rgb(28_25_23/0.06)]"><slot name="footer" /></div>
        </SheetContent>
    </Sheet>
</template>
