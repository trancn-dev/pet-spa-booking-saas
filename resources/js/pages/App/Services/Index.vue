<script setup lang="ts">
import DataTable, { type Column } from '@/components/pb/DataTable.vue';
import EmptyState from '@/components/pb/EmptyState.vue';
import GroupBadge from '@/components/pb/GroupBadge.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import TabsBar from '@/components/pb/TabsBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/vue3';
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

const columns: Column[] = [
    { key: 'code', label: 'Mã', width: '5rem' },
    { key: 'name', label: 'Tên', width: 'minmax(14rem,3fr)' },
    { key: 'group', label: 'Nhóm', width: '5.5rem' },
    { key: 'duration_min', label: 'Thời lượng', width: '6.5rem', align: 'right' },
    { key: 'buffer_min', label: 'Buffer', width: '5.5rem', align: 'right' },
    { key: 'price', label: 'Giá', width: '8rem', align: 'right' },
    { key: 'branches', label: 'Cơ sở', width: 'minmax(6rem,1fr)' },
    { key: 'is_public', label: 'Trên Site', width: '5.5rem', align: 'center' },
    { key: 'is_active', label: 'Hoạt động', width: '6rem', align: 'center' },
];

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
            <PageHeader
                title="Dịch vụ"
                subtitle="Giá dùng chung mọi cơ sở. Giá trên lịch hẹn là giá tham khảo; tính tiền theo giá lúc thêm vào Visit."
            >
                <template v-if="can.manage" #actions>
                    <Button as-child>
                        <Link :href="route('app.services.create')"><Plus class="size-4" /> Thêm dịch vụ</Link>
                    </Button>
                </template>
            </PageHeader>

            <TabsBar
                :model-value="filters.group ?? 'all'"
                :items="groups.map((g) => ({ value: g.value ?? 'all', label: g.label }))"
                label="Nhóm dịch vụ"
                @update:model-value="(v) => router.get(route('app.services.index'), v === 'all' ? {} : { group: v })"
            />

            <DataTable :columns="columns" :rows="services" row-key="id" :row-class="(s) => (s.is_active ? undefined : 'text-muted-foreground')">
                <template #cell-code="{ row }"
                    ><span class="font-mono text-xs">{{ row.code }}</span></template
                >
                <template #cell-name="{ row }">
                    <Link :href="route('app.services.show', row.id)" class="font-medium hover:underline">{{ row.name }}</Link>
                    <Badge v-if="row.is_combo" tone="primary" class="ml-2">Combo</Badge>
                    <div v-if="row.is_combo" class="text-xs text-muted-foreground">{{ row.items.join(' + ') }}</div>
                    <div v-if="row.rules_count" class="text-xs text-muted-foreground">{{ row.rules_count }} quy tắc theo pet</div>
                </template>
                <template #cell-group="{ row }"><GroupBadge :group="row.group" :label="row.group_label" /></template>
                <template #cell-duration_min="{ row }"
                    ><span class="tabular-nums">{{ row.duration_min }} phút</span></template
                >
                <template #cell-buffer_min="{ row }"
                    ><span class="tabular-nums">{{ row.buffer_min ? `${row.buffer_min} phút` : '—' }}</span></template
                >
                <template #cell-price="{ row }"
                    ><span class="font-mono text-[13px] tabular-nums">{{ formatMoney(row.price) }}</span></template
                >
                <template #cell-branches="{ row }">{{ row.branches ? row.branches.join(', ') : 'Mọi cơ sở' }}</template>
                <template #cell-is_public="{ row }"><Check v-if="row.is_public" class="mx-auto size-4 text-success" aria-label="Có" /></template>
                <template #cell-is_active="{ row }"><Check v-if="row.is_active" class="mx-auto size-4 text-success" aria-label="Có" /></template>
                <template #empty>
                    <EmptyState compact title="Chưa có dịch vụ nào">
                        Thêm dịch vụ khám hoặc spa để bắt đầu nhận lịch.
                        <template v-if="can.manage" #action
                            ><Button size="sm" as-child><Link :href="route('app.services.create')">Thêm dịch vụ</Link></Button></template
                        >
                    </EmptyState>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
