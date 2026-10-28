<script setup lang="ts">
import BranchSwitcher from '@/components/BranchSwitcher.vue';
import BookingButton from '@/components/shell/BookingButton.vue';
import GlobalSearch from '@/components/shell/GlobalSearch.vue';
import ShopChip from '@/components/shell/ShopChip.vue';
import UserMenu from '@/components/shell/UserMenu.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';

/** Design 3.4 topbar (56px): shop › branch, global search, "Đặt lịch", account. Used by every page inside a shop. */
</script>

<template>
    <header class="flex h-14 shrink-0 items-center gap-2 border-b bg-card px-4">
        <SidebarTrigger class="-ml-1 md:hidden" />
        <ShopChip />
        <span class="text-muted-foreground" aria-hidden="true">›</span>
        <BranchSwitcher />
        <div class="ml-2 hidden md:block"><GlobalSearch /></div>
        <div class="flex-1" />
        <BookingButton />
        <UserMenu />
    </header>
</template>
