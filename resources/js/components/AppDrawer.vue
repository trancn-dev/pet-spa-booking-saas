<script setup lang="ts">
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';

/**
 | Design 3.5 "Drawer phải" (480px): header (title or `header` slot), scrolling body, sticky `footer`.
 */
defineProps<{ title?: string; description?: string }>();
const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex w-full flex-col gap-0 p-0 sm:max-w-[480px]">
            <SheetHeader class="border-b px-5 py-4">
                <SheetTitle
                    ><slot name="header">{{ title }}</slot></SheetTitle
                >
                <SheetDescription v-if="description || $slots.description"
                    ><slot name="description">{{ description }}</slot></SheetDescription
                >
                <SheetDescription v-else class="sr-only">{{ title }}</SheetDescription>
            </SheetHeader>
            <div class="min-h-0 flex-1 overflow-y-auto"><slot /></div>
            <div v-if="$slots.footer" class="border-t bg-card px-5 py-3"><slot name="footer" /></div>
        </SheetContent>
    </Sheet>
</template>
