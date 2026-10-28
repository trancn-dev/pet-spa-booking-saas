<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { ShopSharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted } from 'vue';

/*
| Design 3.4 topbar "+ Đặt lịch  N". On the calendar it opens the booking drawer in place (event `pb:book`); elsewhere it
| goes to the calendar with `?book=1`, which opens the drawer on arrival. "N" works from any page.
*/
const page = usePage<ShopSharedData>();
const visible = computed(() => page.props.permissions?.includes('appointments.manage') && !page.props.readOnly);

const open = () => {
    if (route().current('app.appointments.index')) window.dispatchEvent(new CustomEvent('pb:book'));
    else router.visit(route('app.appointments.index', { book: 1 }));
};
const onKey = (e: KeyboardEvent) => {
    const el = e.target as HTMLElement | null;
    const typing = !!el && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable);
    // the calendar page handles N itself
    if ((e.key !== 'n' && e.key !== 'N') || typing || e.metaKey || e.ctrlKey || e.altKey || !visible.value || route().current('app.appointments.index')) return;
    e.preventDefault();
    open();
};
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Button v-if="visible" size="sm" class="gap-1.5" @click="open">
        <Plus /> <span class="hidden sm:inline">Đặt lịch</span>
        <kbd class="hidden rounded bg-white/20 px-1.5 font-mono text-[11px] sm:inline">N</kbd>
    </Button>
</template>
