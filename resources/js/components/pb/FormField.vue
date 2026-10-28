<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next';
import { useId } from 'vue';

/*
| Design K0 "Input · Select · Textarea" frame: label on top (13/500), required star, helper under, error in red with
| an icon. The control goes in the default slot and receives `id` (slot prop) so the label points at it.
*/
defineProps<{ label?: string; required?: boolean; hint?: string; error?: string | null }>();
const id = useId();
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" :for="id" class="block text-[13px] font-medium leading-[18px]">
            {{ label }}<span v-if="required" class="ml-0.5 text-danger" aria-hidden="true">*</span>
        </label>
        <slot :id="id" :invalid="!!error" />
        <p v-if="error" class="flex items-center gap-1 text-xs leading-4 text-danger" role="alert">
            <AlertCircle class="size-3.5 shrink-0" /> {{ error }}
        </p>
        <p v-else-if="hint" class="text-xs leading-4 text-muted-foreground">{{ hint }}</p>
    </div>
</template>
