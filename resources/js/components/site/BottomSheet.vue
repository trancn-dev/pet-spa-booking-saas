<script setup lang="ts">
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';

/** Design Site "sheet": secondary actions on mobile (cancel appointment, filters); radius 20 top, drag handle, sticky action. */
defineProps<{ title: string; description?: string }>();
const open = defineModel<boolean>('open', { required: true });
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="bottom" class="mx-auto max-w-[480px] rounded-t-[20px] px-4 pb-5 pt-3">
            <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-border" aria-hidden="true" />
            <SheetTitle class="text-lg font-semibold">{{ title }}</SheetTitle>
            <SheetDescription v-if="description" class="text-sm text-muted-foreground">{{ description }}</SheetDescription>
            <SheetDescription v-else class="sr-only">{{ title }}</SheetDescription>
            <div class="mt-3"><slot /></div>
            <div v-if="$slots.actions" class="mt-4 grid gap-2"><slot name="actions" /></div>
        </SheetContent>
    </Sheet>
</template>
