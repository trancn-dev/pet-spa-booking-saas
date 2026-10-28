<script setup lang="ts">
/** Design "two": main text (500) over a caption, the standard table cell for a person, pet or service. */
defineProps<{ title: string; caption?: string | null }>();
</script>

<template>
    <div class="min-w-0">
        <div class="truncate font-medium">{{ title }}</div>
        <div v-if="caption" class="truncate text-xs leading-4 text-muted-foreground">{{ caption }}</div>
    </div>
</template>
