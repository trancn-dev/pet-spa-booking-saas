<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/** "← Nhân viên": the small link above a record page title (design head `before` slot). */
defineProps<{ href: string }>();
</script>

<template>
    <Link :href="href" class="text-sm text-muted-foreground hover:underline">← <slot /></Link>
</template>
