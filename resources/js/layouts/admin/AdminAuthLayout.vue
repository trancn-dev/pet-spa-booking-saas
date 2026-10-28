<script setup lang="ts">
import type { AdminSharedData } from '@/types/admin';
import { usePage } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{ title: string; description?: string }>();

const page = usePage<AdminSharedData>();
const environment = computed(() => page.props.environment);
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-slate-950 p-6 md:p-10">
        <div class="flex items-center gap-2 text-slate-100">
            <ShieldCheck class="size-6 text-teal-400" />
            <span class="text-lg font-semibold">PawBook Admin</span>
            <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="environment === 'Production' ? 'bg-red-600 text-white' : 'bg-slate-700 text-slate-200'"
            >
                {{ environment }}
            </span>
        </div>
        <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-lg dark:bg-slate-900">
            <h1 class="text-xl font-semibold text-stone-900 dark:text-slate-100">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-stone-600 dark:text-slate-400">{{ description }}</p>
            <div class="mt-6">
                <slot />
            </div>
        </div>
    </div>
</template>
