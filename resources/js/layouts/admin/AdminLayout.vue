<script setup lang="ts">
import type { AdminSharedData } from '@/types/admin';
import { roleLabels } from '@/types/admin';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, ScrollText, ShieldCheck, Store, Users } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{ title: string }>();

const page = usePage<AdminSharedData>();
const admin = computed(() => page.props.admin);
const environment = computed(() => page.props.environment);
const status = computed(() => page.props.flash?.status);

const nav = computed(() => [
    { title: 'Shop', href: route('admin.tenants.index'), icon: Store, active: route().current('admin.tenants.*') },
    { title: 'Nhật ký', href: route('admin.audit-logs.index'), icon: ScrollText, active: route().current('admin.audit-logs.*') },
    ...(admin.value?.is_super_admin
        ? [{ title: 'Quản lý admin', href: route('admin.admins.index'), icon: Users, active: route().current('admin.admins.*') }]
        : []),
]);

const logout = () => router.post(route('admin.logout'));
</script>

<template>
    <div class="flex min-h-svh bg-stone-50 text-stone-900 dark:bg-slate-950 dark:text-slate-100">
        <!-- Dark sidebar: never mistaken for a shop's CMS (design 3.4). -->
        <aside class="flex w-[248px] shrink-0 flex-col bg-slate-950 text-slate-300">
            <div class="flex items-center gap-2 px-5 py-5">
                <ShieldCheck class="size-5 text-teal-400" />
                <span class="font-semibold text-white">PawBook Admin</span>
            </div>
            <div class="px-5">
                <span
                    class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="environment === 'Production' ? 'bg-red-600 text-white' : 'bg-slate-700 text-slate-200'"
                >
                    {{ environment }}
                </span>
            </div>
            <nav class="mt-6 flex flex-col gap-1 px-3">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors"
                    :class="item.active ? 'bg-slate-800 text-white' : 'hover:bg-slate-900 hover:text-white'"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.title }}
                </Link>
            </nav>
            <div v-if="admin" class="mt-auto border-t border-slate-800 px-5 py-4 text-sm">
                <div class="font-medium text-white">{{ admin.name }}</div>
                <div class="text-xs text-slate-400">{{ roleLabels[admin.role] }} · {{ admin.email }}</div>
                <button type="button" class="mt-3 flex items-center gap-2 text-slate-400 hover:text-white" @click="logout">
                    <LogOut class="size-4" /> Đăng xuất
                </button>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <header class="flex h-14 items-center border-b border-stone-200 bg-white px-6 dark:border-slate-800 dark:bg-slate-900">
                <h1 class="text-lg font-semibold">{{ title }}</h1>
            </header>
            <div class="mx-auto max-w-[1280px] p-6">
                <div
                    v-if="status"
                    role="status"
                    class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-2 text-sm text-teal-800 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-200"
                >
                    {{ status }}
                </div>
                <slot />
            </div>
        </main>
    </div>
</template>
