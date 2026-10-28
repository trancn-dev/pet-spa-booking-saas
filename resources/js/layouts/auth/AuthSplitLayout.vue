<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PawPrint } from 'lucide-vue-next';

/*
| Design S36 / S37: brand panel on the left (tagline, pawbook.vn), form on the right. Below 1024px the panel collapses to
| a brand row on top of the form.
*/
withDefaults(defineProps<{ title?: string; description?: string; tagline?: string }>(), {
    tagline: 'Lịch hẹn, hồ sơ thú cưng và thu ngân cho spa và phòng khám thú y.',
});
</script>

<template>
    <div class="grid min-h-svh bg-card lg:grid-cols-[minmax(0,1fr)_minmax(0,0.64fr)]">
        <aside class="hidden flex-col justify-between bg-primary-soft p-14 lg:flex">
            <Link :href="route('home')" class="flex items-center gap-3 text-xl font-bold">
                <span class="grid size-10 place-items-center rounded-xl bg-primary text-primary-foreground"><PawPrint class="size-6" /></span>
                PawBook
            </Link>
            <p class="max-w-[28rem] text-[32px] font-bold leading-[44px] text-foreground">{{ tagline }}</p>
            <span class="text-sm text-foreground/70">pawbook.vn</span>
        </aside>

        <main class="flex flex-col justify-center px-6 py-10 sm:px-14 lg:px-12">
            <Link :href="route('home')" class="mb-10 flex items-center gap-2.5 text-lg font-bold lg:hidden">
                <span class="grid size-8 place-items-center rounded-lg bg-primary text-primary-foreground"><PawPrint class="size-5" /></span>
                PawBook
            </Link>
            <div class="mx-auto w-full max-w-[29rem]">
                <h1 v-if="title" class="text-[28px] font-semibold leading-9">{{ title }}</h1>
                <p v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
                <div class="mt-8"><slot /></div>
            </div>
        </main>
    </div>
</template>
