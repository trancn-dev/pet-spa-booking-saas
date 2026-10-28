<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next';
import { useId } from 'vue';

/** Design Site field: label 14/500 and a 44px, 16px-text input (no iOS zoom). Phone fields: pass `prefix="+84"` and `inputmode="tel"`. */
defineProps<{ label?: string; error?: string | null; hint?: string; prefix?: string; suffix?: string }>();
const model = defineModel<string>({ default: '' });
defineOptions({ inheritAttrs: false });
const id = useId();
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" :for="id" class="block text-sm font-medium">{{ label }}</label>
        <div
            class="flex min-h-11 items-center gap-2 rounded-[10px] border bg-background px-3.5 focus-within:ring-2 focus-within:ring-primary"
            :class="error ? 'border-2 border-danger' : 'border-input'"
        >
            <span v-if="prefix" class="text-muted-foreground">{{ prefix }}</span>
            <input
                :id="id"
                v-model="model"
                class="min-w-0 flex-1 bg-transparent py-2 text-base focus:outline-none"
                :aria-invalid="!!error"
                v-bind="$attrs"
            />
            <span v-if="suffix" class="text-muted-foreground">{{ suffix }}</span>
        </div>
        <p v-if="error" class="flex items-center gap-1 text-xs text-danger" role="alert"><AlertCircle class="size-3.5" /> {{ error }}</p>
        <p v-else-if="hint" class="text-xs text-muted-foreground">{{ hint }}</p>
    </div>
</template>
