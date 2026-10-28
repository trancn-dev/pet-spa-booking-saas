<script setup lang="ts">
import PageHeader from '@/components/pb/PageHeader.vue';
import type { ShopSharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/*
| Design S12: "Cài đặt" is one page with a left sub-navigation (Cơ sở, Phân quyền, …); each section is a route.
| Items follow the person's permissions. Online rates belong to a branch, so they keep "Cơ sở" highlighted.
*/
const page = usePage<ShopSharedData>();
const can = (permission: string) => page.props.permissions?.includes(permission) ?? false;
const current = (...names: string[]) => names.some((n) => !!route().current(n));

const items = computed(() => [
    ...(can('branches.manage')
        ? [
              {
                  title: 'Cơ sở',
                  href: route('app.settings.branches'),
                  active: current('app.settings.branches', 'app.settings.branches.*', 'app.settings.online-rates'),
              },
          ]
        : []),
    ...(can('roles.manage')
        ? [{ title: 'Phân quyền', href: route('app.settings.roles'), active: current('app.settings.roles', 'app.settings.roles.*') }]
        : []),
    ...(can('settings.manage')
        ? [{ title: 'Truy cập hỗ trợ', href: route('app.settings.support-access'), active: current('app.settings.support-access') }]
        : []),
]);
</script>

<template>
    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader title="Cài đặt" />
        <div class="grid gap-6 md:grid-cols-[13rem_minmax(0,1fr)] md:items-start">
            <nav class="flex gap-1 overflow-x-auto md:flex-col" aria-label="Mục cài đặt">
                <Link
                    v-for="i in items"
                    :key="i.title"
                    :href="i.href"
                    class="whitespace-nowrap rounded-[10px] px-3 py-2 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    :class="
                        i.active
                            ? 'bg-primary-soft font-semibold text-foreground'
                            : 'text-muted-foreground hover:bg-surface-muted hover:text-foreground'
                    "
                    :aria-current="i.active ? 'page' : undefined"
                >
                    {{ i.title }}
                </Link>
            </nav>
            <div class="min-w-0"><slot /></div>
        </div>
    </div>
</template>
