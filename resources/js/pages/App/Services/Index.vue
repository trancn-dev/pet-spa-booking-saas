<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import { Head, Link } from '@inertiajs/vue3';
import { Check, Plus } from 'lucide-vue-next';

interface ServiceRow {
    id: number;
    code: string;
    name: string;
    group: 'medical' | 'grooming';
    group_label: string;
    is_combo: boolean;
    items: string[];
    duration_min: number;
    buffer_min: number;
    price: string;
    is_public: boolean;
    is_active: boolean;
    branches: string[] | null;
    rules_count: number;
}

defineProps<{
    filters: { group: string | null };
    services: ServiceRow[];
    can: { manage: boolean };
}>();

const groups = [
    { value: null, label: 'Tất cả' },
    { value: 'medical', label: 'Khám' },
    { value: 'grooming', label: 'Spa' },
];
</script>

<template>
    <Head title="Dịch vụ" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Dịch vụ</h1>
                    <p class="text-sm text-muted-foreground">Giá dùng chung mọi cơ sở. Giá trên lịch hẹn là giá tham khảo; tính tiền theo giá lúc thêm vào Visit.</p>
                </div>
                <Button v-if="can.manage" as-child>
                    <Link :href="route('app.services.create')"><Plus class="size-4" /> Thêm dịch vụ</Link>
                </Button>
            </div>

            <nav class="flex gap-1 border-b" aria-label="Nhóm dịch vụ">
                <Link
                    v-for="g in groups"
                    :key="g.label"
                    :href="route('app.services.index', g.value ? { group: g.value } : {})"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium"
                    :class="filters.group === g.value ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground'"
                    >{{ g.label }}</Link
                >
            </nav>

            <div class="overflow-x-auto rounded-[10px] border bg-card">
                <table class="w-full text-sm">
                    <thead class="border-b bg-surface-muted text-left text-xs uppercase text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Mã</th>
                            <th class="px-4 py-2 font-medium">Tên</th>
                            <th class="px-4 py-2 font-medium">Nhóm</th>
                            <th class="px-4 py-2 text-right font-medium">Thời lượng</th>
                            <th class="px-4 py-2 text-right font-medium">Buffer</th>
                            <th class="px-4 py-2 text-right font-medium">Giá</th>
                            <th class="px-4 py-2 font-medium">Cơ sở</th>
                            <th class="px-4 py-2 text-center font-medium">Trên Site</th>
                            <th class="px-4 py-2 text-center font-medium">Hoạt động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in services" :key="s.id" class="border-b last:border-0 hover:bg-surface-muted/50" :class="{ 'text-muted-foreground': !s.is_active }">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ s.code }}</td>
                            <td class="px-4 py-3">
                                <Link :href="route('app.services.show', s.id)" class="font-medium hover:underline">{{ s.name }}</Link>
                                <span v-if="s.is_combo" class="ml-2 rounded-full bg-primary-soft px-2 py-0.5 text-xs font-medium text-primary">Combo</span>
                                <div v-if="s.is_combo" class="text-xs text-muted-foreground">{{ s.items.join(' + ') }}</div>
                                <div v-if="s.rules_count" class="text-xs text-muted-foreground">{{ s.rules_count }} quy tắc theo pet</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="s.group === 'medical' ? 'bg-info/10 text-info' : 'bg-coral/10 text-coral'">{{
                                    s.group_label
                                }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ s.duration_min }} phút</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ s.buffer_min ? `${s.buffer_min} phút` : '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ formatMoney(s.price) }}</td>
                            <td class="px-4 py-3">{{ s.branches ? s.branches.join(', ') : 'Mọi cơ sở' }}</td>
                            <td class="px-4 py-3 text-center"><Check v-if="s.is_public" class="mx-auto size-4 text-success" aria-label="Có" /></td>
                            <td class="px-4 py-3 text-center"><Check v-if="s.is_active" class="mx-auto size-4 text-success" aria-label="Có" /></td>
                        </tr>
                        <tr v-if="!services.length">
                            <td colspan="9" class="px-4 py-10 text-center text-muted-foreground">Chưa có dịch vụ nào.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
