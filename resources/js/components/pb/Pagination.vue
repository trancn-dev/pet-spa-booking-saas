<script setup lang="ts">
import { router } from '@inertiajs/vue3';

/** Laravel paginator links (Inertia): previous / numbers / next, keeps scroll and the query string. */
defineProps<{ links: { url: string | null; label: string; active: boolean }[] }>();
const go = (url: string | null) => url && router.get(url, {}, { preserveScroll: true, preserveState: true });
const text = (label: string) =>
    label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace(/&[a-z]+;/g, '')
        .replace('Previous', 'Trước')
        .replace('Next', 'Sau');
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center gap-1" aria-label="Phân trang">
        <button
            v-for="(l, i) in links"
            :key="i"
            type="button"
            class="min-w-9 rounded-[10px] border px-2.5 py-1.5 text-sm tabular-nums transition-colors disabled:cursor-not-allowed disabled:opacity-40"
            :class="l.active ? 'border-primary bg-primary-soft font-semibold text-primary' : 'hover:bg-surface-muted'"
            :disabled="!l.url"
            :aria-current="l.active ? 'page' : undefined"
            @click="go(l.url)"
        >
            {{ text(l.label) }}
        </button>
    </nav>
</template>
