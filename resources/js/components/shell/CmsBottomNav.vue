<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';
import type { ShopSharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, Menu, PawPrint, Users } from 'lucide-vue-next';
import { computed } from 'vue';

/*
| Design 3.4 / K1g: below 640px a 4-item bottom nav replaces the sidebar; "Thêm" opens the full menu (sidebar sheet).
| Items follow the permissions of the person, like the sidebar.
*/
const page = usePage<ShopSharedData>();
const { setOpenMobile } = useSidebar();
const can = (p: string) => page.props.permissions?.includes(p) ?? false;
const items = computed(() => [
    ...(can('appointments.view') ? [{ title: 'Lịch hẹn', href: route('app.appointments.index'), icon: CalendarDays, active: !!route().current('app.appointments.*') }] : []),
    ...(can('customers.view') ? [{ title: 'Khách', href: route('app.customers.index'), icon: PawPrint, active: !!(route().current('app.customers.*') || route().current('app.pets.*')) }] : []),
    { title: 'Lịch trực', href: route('app.rosters.index'), icon: Users, active: !!route().current('app.rosters.*') },
]);
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t bg-card pb-[env(safe-area-inset-bottom)] sm:hidden" aria-label="Điều hướng nhanh">
        <Link
            v-for="i in items"
            :key="i.title"
            :href="i.href"
            class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :class="i.active ? 'font-semibold text-primary' : 'text-muted-foreground'"
            :aria-current="i.active ? 'page' : undefined"
        >
            <component :is="i.icon" class="size-5" />{{ i.title }}
        </Link>
        <button type="button" class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" @click="setOpenMobile(true)">
            <Menu class="size-5" />Thêm
        </button>
    </nav>
</template>
