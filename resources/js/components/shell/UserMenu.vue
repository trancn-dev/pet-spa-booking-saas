<script setup lang="ts">
import UserMenuContent from '@/components/UserMenuContent.vue';
import RingAvatar from '@/components/pb/RingAvatar.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { SharedData, User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Design K1: initials in a ring at the far right of the topbar; the menu has account settings and sign-out. */
const user = computed(() => usePage<SharedData>().props.auth.user as User);
const initials = computed(() =>
    user.value.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(-2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            :aria-label="`Tài khoản: ${user.name}`"
        >
            <RingAvatar :initials="initials" :size="32" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="min-w-56"><UserMenuContent :user="user" /></DropdownMenuContent>
    </DropdownMenu>
</template>
