<script setup lang="ts">
/** Design "head": h1 24/32 600, optional sub line, actions on the right (`actions` slot), `before` for a back link. */
defineProps<{ title: string; subtitle?: string }>();
</script>

<template>
    <header class="flex flex-wrap items-center gap-3">
        <div class="min-w-0 flex-1">
            <slot name="before" />
            <h1 class="flex flex-wrap items-center gap-2 text-2xl font-semibold leading-8">
                <span class="min-w-0">{{ title }}</span
                ><slot name="badges" />
            </h1>
            <p v-if="subtitle || $slots.subtitle" class="mt-0.5 text-sm text-muted-foreground">
                <slot name="subtitle">{{ subtitle }}</slot>
            </p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2"><slot name="actions" /></div>
    </header>
</template>
