<script setup lang="ts">
import { Check } from 'lucide-vue-next';

/** Design K0 "Stepper": Site booking (5 steps), transfer slips. `current` is 1-based; earlier steps show a check. */
defineProps<{ steps: number; current: number; labels?: string[] }>();
</script>

<template>
    <ol class="flex items-center" :aria-label="`Bước ${current}/${steps}`">
        <li v-for="n in steps" :key="n" class="flex items-center" :class="{ 'flex-1': n < steps }" :aria-current="n === current ? 'step' : undefined">
            <span
                class="grid size-6 shrink-0 place-items-center rounded-full text-xs font-semibold"
                :class="
                    n < current
                        ? 'bg-primary text-primary-foreground'
                        : n === current
                          ? 'border-2 border-primary text-primary'
                          : 'border text-muted-foreground'
                "
                :title="labels?.[n - 1]"
            >
                <Check v-if="n < current" class="size-3.5" aria-label="Đã xong" /><template v-else>{{ n }}</template>
            </span>
            <span v-if="n < steps" class="mx-2 h-px flex-1" :class="n < current ? 'bg-primary' : 'bg-border'" aria-hidden="true" />
        </li>
    </ol>
</template>
