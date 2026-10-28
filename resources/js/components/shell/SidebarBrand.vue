<script setup lang="ts">
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/vue3';
import { PawPrint } from 'lucide-vue-next';

/** Design K1: "PawBook" mark at the top of the sidebar; it always leads to the shop's start page. */
defineProps<{ href: string }>();
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <SidebarMenuButton size="lg" as-child>
                <Link :href="href" aria-label="PawBook: về trang tổng quan">
                    <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-primary text-primary-foreground"
                        ><PawPrint class="size-4"
                    /></span>
                    <span class="text-base font-bold">PawBook</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
