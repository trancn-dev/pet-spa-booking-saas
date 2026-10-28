<script setup lang="ts">
/** Design Site "kv": label / value row (confirm step, appointment details). */
defineProps<{ label: string; labelWidth?: string }>();
</script>

<template>
    <div class="flex gap-3 text-[15px]">
        <dt class="flex-none text-muted-foreground" :style="{ width: labelWidth ?? '6rem' }">{{ label }}</dt>
        <dd class="min-w-0 flex-1"><slot /></dd>
    </div>
</template>
