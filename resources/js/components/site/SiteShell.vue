<script setup lang="ts">
/** Design K2: the Site is one column, max 480px, centred on desktop; header and bottom nav stay, content scrolls. */
</script>

<template>
    <div class="mx-auto flex min-h-dvh max-w-[480px] flex-col bg-background text-base leading-6 shadow-sm md:my-0 md:border-x">
        <slot name="header" />
        <main class="flex min-h-0 flex-1 flex-col overflow-y-auto"><slot /></main>
        <slot name="nav" />
    </div>
</template>
