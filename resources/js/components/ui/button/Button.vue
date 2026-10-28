<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Primitive, type PrimitiveProps } from 'radix-vue';
import { LoaderCircle } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { buttonVariants, type ButtonVariants } from '.';

interface Props extends PrimitiveProps {
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    class?: HTMLAttributes['class'];
    /** Shows a spinner, keeps the width and blocks double clicks (design 3.5). */
    loading?: boolean;
    /** Why the button is disabled: shown as a tooltip (design 3.5). */
    disabledReason?: string;
}

const props = withDefaults(defineProps<Props>(), {
    as: 'button',
});
</script>

<template>
    <Primitive
        :as="as"
        :as-child="asChild"
        :class="cn(buttonVariants({ variant, size }), props.class)"
        :disabled="as === 'button' && (loading || undefined)"
        :aria-busy="loading || undefined"
        :title="disabledReason"
    >
        <LoaderCircle v-if="loading" class="animate-spin" aria-hidden="true" />
        <slot />
    </Primitive>
</template>
