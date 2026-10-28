<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import SidebarBrand from '@/components/shell/SidebarBrand.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import type { NavGroup, NavItem, ShopSharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CalendarClock, CalendarDays, LayoutGrid, PanelLeftClose, PawPrint, Scissors, Settings2, Users } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<ShopSharedData>();
const { toggleSidebar } = useSidebar();
const can = (permission: string) => page.props.permissions?.includes(permission) ?? false;
const isRoute = (name: string) => route().current(name) || route().current(`${name}.*`);

/*
| Sidebar of design 3.4. Items appear as their screens are built; an item the person has no
| permission for is not shown (the server checks again on every request).
*/
const groups = computed<NavGroup[]>(() => {
    if (!page.props.tenant) return [];

    // Design 3.4: one "Cài đặt" entry; the sections live in the settings sub-navigation.
    const settingsHref = can('branches.manage')
        ? route('app.settings.branches')
        : can('roles.manage')
          ? route('app.settings.roles')
          : can('settings.manage')
            ? route('app.settings.support-access')
            : null;
    const settings: NavItem[] = settingsHref ? [{ title: 'Cài đặt', href: settingsHref, icon: Settings2, isActive: isRoute('app.settings') }] : [];

    return [
        {
            items: [
                { title: 'Tổng quan', href: route('app.dashboard'), icon: LayoutGrid, isActive: isRoute('app.dashboard') },
                ...(can('appointments.view')
                    ? [{ title: 'Lịch hẹn', href: route('app.appointments.index'), icon: CalendarDays, isActive: isRoute('app.appointments') }]
                    : []),
                ...(can('customers.view')
                    ? [
                          {
                              title: 'Khách & Thú cưng',
                              href: route('app.customers.index'),
                              icon: PawPrint,
                              isActive: isRoute('app.customers') || isRoute('app.pets'),
                          },
                      ]
                    : []),
                ...(can('services.view')
                    ? [{ title: 'Dịch vụ', href: route('app.services.index'), icon: Scissors, isActive: isRoute('app.services') }]
                    : []),
                { title: 'Lịch trực', href: route('app.rosters.index'), icon: CalendarClock, isActive: isRoute('app.rosters') },
                ...(can('staff.view') ? [{ title: 'Nhân viên', href: route('app.staff.index'), icon: Users, isActive: isRoute('app.staff') }] : []),
                ...settings,
            ],
        },
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarBrand :href="page.props.tenant ? route('app.dashboard') : '/'" />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="groups" />
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton tooltip="Thu gọn" @click="toggleSidebar"><PanelLeftClose /><span>Thu gọn</span></SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
