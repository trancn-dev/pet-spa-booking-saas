<script setup lang="ts">
import { nextTick, ref } from 'vue';

/** Design S21 / S6: 6 single-digit cells, paste a whole code, `autocomplete="one-time-code"`. v-model is the digits string. */
const props = withDefaults(defineProps<{ length?: number; invalid?: boolean }>(), { length: 6, invalid: false });
const model = defineModel<string>({ default: '' });
const cells = ref<HTMLInputElement[]>([]);

const set = async (index: number, raw: string) => {
    const digits = raw.replace(/\D/g, '');
    const chars = model.value.padEnd(props.length, ' ').split('');
    if (digits.length > 1) {
        digits
            .slice(0, props.length - index)
            .split('')
            .forEach((d, i) => (chars[index + i] = d));
    } else {
        chars[index] = digits || ' ';
    }
    model.value = chars.join('').trimEnd().replace(/ /g, '');
    await nextTick();
    cells.value[Math.min(props.length - 1, digits ? index + digits.length : index)]?.focus();
};
const back = (index: number, e: KeyboardEvent) => {
    if (!(e.target as HTMLInputElement).value && index > 0) cells.value[index - 1]?.focus();
};
</script>

<template>
    <div class="flex gap-2" role="group" aria-label="Mã xác minh">
        <input
            v-for="i in length"
            :key="i"
            :ref="(el) => (cells[i - 1] = el as HTMLInputElement)"
            :value="model[i - 1] ?? ''"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            :aria-label="`Chữ số ${i}`"
            class="size-12 rounded-[10px] border bg-background text-center text-xl font-semibold tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :class="invalid ? 'border-2 border-danger' : 'border-input'"
            @input="set(i - 1, ($event.target as HTMLInputElement).value)"
            @keydown.backspace="back(i - 1, $event)"
        />
    </div>
</template>
