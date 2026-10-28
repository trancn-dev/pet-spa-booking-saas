<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Circle, LayoutGrid } from 'lucide-vue-next';
import { computed } from 'vue';

interface ChecklistStep {
    key: string;
    label: string;
    done: boolean | null;
    href: string | null;
}

const props = defineProps<{
    checklist: ChecklistStep[] | null;
}>();

const tracked = computed(() => (props.checklist ?? []).filter((s) => s.done !== null));
const doneCount = computed(() => tracked.value.filter((s) => s.done).length);
</script>

<template>
    <Head title="Tổng quan" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <h1 class="text-2xl font-semibold">Tổng quan</h1>

            <!-- S13: first-run checklist for the owner -->
            <section v-if="checklist" class="rounded-[10px] border bg-card p-6 shadow-sm dark:shadow-none">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-xl font-semibold">Bắt đầu với PawBook</h2>
                    <span class="text-sm tabular-nums text-muted-foreground">Đã xong {{ doneCount }}/{{ tracked.length }}</span>
                </div>
                <div class="mt-3 h-1.5 rounded-full bg-muted">
                    <div class="h-1.5 rounded-full bg-primary transition-all" :style="{ width: (tracked.length ? (doneCount / tracked.length) * 100 : 0) + '%' }" />
                </div>
                <ul class="mt-5 grid gap-3">
                    <li v-for="step in checklist" :key="step.key" class="flex items-center gap-3 text-sm">
                        <CheckCircle2 v-if="step.done" class="size-5 shrink-0 text-success" aria-label="Đã xong" />
                        <Circle v-else class="size-5 shrink-0 text-subtle" aria-label="Chưa xong" />
                        <Link v-if="step.href" :href="step.href" class="text-primary hover:underline" :class="{ 'line-through opacity-70': step.done }">
                            {{ step.label }}
                        </Link>
                        <span v-else :class="{ 'text-muted-foreground line-through': step.done }">{{ step.label }}</span>
                    </li>
                </ul>
            </section>

            <!-- KPI, today's schedule and alerts come with task 9.1 (S5). -->
            <div class="flex flex-col items-center gap-2 rounded-[10px] border border-dashed px-6 py-16 text-center">
                <LayoutGrid class="size-10 text-subtle" />
                <p class="font-medium">Số liệu hôm nay sẽ hiện ở đây</p>
                <p class="text-sm text-muted-foreground">Lịch hẹn, doanh thu và cảnh báo xuất hiện khi shop bắt đầu nhận lịch.</p>
            </div>
        </div>
    </AppLayout>
</template>
