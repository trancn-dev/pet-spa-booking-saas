<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

/*
| Design 3.4 global search (customers, phone, pet name). Enter goes to the customer list with `q` (Inertia visit);
| "/" focuses it from any page, except where the page owns "/" (calendar search).
*/
const q = ref('');
const input = ref<HTMLInputElement | null>(null);
const canSearch = () => true;

const submit = () => {
    const term = q.value.trim();
    if (term) router.get(route('app.customers.index'), { q: term });
};
const onKey = (e: KeyboardEvent) => {
    const el = e.target as HTMLElement | null;
    const typing = !!el && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable);
    if (e.key !== '/' || typing || e.metaKey || e.ctrlKey || e.altKey) return;
    e.preventDefault();
    input.value?.focus();
};
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <form v-if="canSearch()" class="relative hidden w-80 md:block" role="search" @submit.prevent="submit">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        <input
            ref="input"
            v-model="q"
            type="search"
            aria-label="Tìm khách, SĐT, tên pet"
            placeholder="Tìm khách, SĐT, tên pet"
            class="h-9 w-full rounded-[10px] border bg-surface-muted pl-9 pr-9 text-sm placeholder:text-muted-foreground focus-visible:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        />
        <kbd
            class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 rounded border bg-background px-1.5 font-mono text-[11px] text-muted-foreground"
            >/</kbd
        >
    </form>
</template>
