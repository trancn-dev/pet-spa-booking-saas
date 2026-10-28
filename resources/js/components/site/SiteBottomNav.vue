<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Briefcase, CalendarDays, Home, User } from 'lucide-vue-next';
import type { Component } from 'vue';

/*
| Design K2 bottom nav (customer logged in): Trang chủ · Dịch vụ · Lịch hẹn · Tài khoản. 64px, keeps the iOS safe area,
| each target >= 44px. Items are passed in so the Site can hide entries (e.g. no "Dịch vụ" when the shop has none).
*/
export interface SiteNavItem {
    key: string;
    label: string;
    href: string;
    icon?: Component;
}
const defaults: Record<string, Component> = { home: Home, services: Briefcase, appointments: CalendarDays, account: User };
defineProps<{ items: SiteNavItem[]; active: string }>();
</script>

<template>
    <nav class="grid h-16 flex-none grid-cols-4 border-t bg-card pb-[env(safe-area-inset-bottom)] pt-1" aria-label="Điều hướng chính">
        <Link
            v-for="i in items"
            :key="i.key"
            :href="i.href"
            class="flex flex-col items-center justify-center gap-0.5 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :class="active === i.key ? 'font-semibold text-primary' : 'text-muted-foreground'"
            :aria-current="active === i.key ? 'page' : undefined"
        >
            <component :is="i.icon ?? defaults[i.key]" class="size-6" />
            {{ i.label }}
        </Link>
    </nav>
</template>
