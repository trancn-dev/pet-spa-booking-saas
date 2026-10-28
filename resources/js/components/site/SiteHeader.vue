<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/** Design K2 Site header (60px): shop logo + name, "Đặt lịch" CTA, "Đăng nhập" or the customer avatar (`account` slot). */
defineProps<{ name: string; logoUrl?: string | null; homeHref?: string }>();
</script>

<template>
    <header class="flex h-[60px] flex-none items-center gap-2 border-b bg-card px-4">
        <Link :href="homeHref ?? '#'" class="flex min-w-0 flex-1 items-center gap-2">
            <img v-if="logoUrl" :src="logoUrl" alt="" class="size-8 rounded-lg object-cover" />
            <span
                v-else
                class="grid size-8 place-items-center rounded-lg bg-coral/20 text-sm font-bold text-orange-900 dark:text-coral"
                aria-hidden="true"
                >{{ name.slice(0, 1) }}</span
            >
            <span class="truncate font-semibold">{{ name }}</span>
        </Link>
        <slot name="actions" />
    </header>
</template>
