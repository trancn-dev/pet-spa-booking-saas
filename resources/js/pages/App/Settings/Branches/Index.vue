<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import DataTable, { type Column } from '@/components/pb/DataTable.vue';
import EmptyState from '@/components/pb/EmptyState.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ShopSharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Building2, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface BranchRow {
    id: string;
    code: string;
    name: string;
    address: string | null;
    phone: string | null;
    is_active: boolean;
}

const props = defineProps<{
    branches: BranchRow[];
    limit: { used: number; max: number | null };
}>();

const page = usePage<ShopSharedData>();
const errors = computed(() => page.props.errors ?? {});
const full = computed(() => props.limit.max !== null && props.limit.used >= props.limit.max);
const near = computed(() => props.limit.max !== null && props.limit.used / props.limit.max >= 0.8);

const remove = (branch: BranchRow) => {
    if (confirm(`Xóa cơ sở ${branch.name}? Lịch sử của cơ sở vẫn được giữ.`)) {
        router.delete(route('app.settings.branches.destroy', branch.id), { preserveScroll: true });
    }
};

const columns: Column[] = [
    { key: 'code', label: 'Mã', width: '6rem' },
    { key: 'name', label: 'Tên', width: 'minmax(10rem,1.5fr)' },
    { key: 'address', label: 'Địa chỉ', width: 'minmax(12rem,2fr)' },
    { key: 'phone', label: 'Điện thoại', width: '9rem' },
    { key: 'status', label: 'Trạng thái', width: '10rem' },
    { key: 'actions', label: '', width: '6.5rem' },
];
</script>

<template>
    <Head title="Cơ sở" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <PageHeader title="Cơ sở">
                <template #subtitle>
                    <span :class="full ? 'text-danger' : near ? 'text-warning' : ''"
                        >Đang dùng {{ limit.used }}/{{ limit.max ?? '∞' }} cơ sở theo gói</span
                    >
                </template>
                <template #actions>
                    <Button v-if="!full" as-child>
                        <Link :href="route('app.settings.branches.create')"><Plus /> Thêm cơ sở</Link>
                    </Button>
                    <Button v-else disabled disabled-reason="Đã đủ số cơ sở của gói. Liên hệ PawBook để nâng gói."><Plus /> Thêm cơ sở</Button>
                </template>
            </PageHeader>

            <InputError :message="errors.branch" />

            <DataTable :columns="columns" :rows="branches" row-key="id">
                <template #cell-code="{ row }"
                    ><span class="font-mono">{{ row.code }}</span></template
                >
                <template #cell-name="{ row }"
                    ><span class="font-medium">{{ row.name }}</span></template
                >
                <template #cell-address="{ row }"
                    ><span class="text-muted-foreground">{{ row.address ?? '—' }}</span></template
                >
                <template #cell-phone="{ row }"
                    ><span class="tabular-nums">{{ row.phone ?? '—' }}</span></template
                >
                <template #cell-status="{ row }">
                    <Badge :tone="row.is_active ? 'success' : 'neutral'" dot>{{ row.is_active ? 'Đang hoạt động' : 'Tạm ngưng' }}</Badge>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-1">
                        <Button variant="ghost" size="icon" as-child>
                            <Link :href="route('app.settings.branches.edit', row.id)" :aria-label="`Sửa ${row.name}`"><Pencil /></Link>
                        </Button>
                        <Button
                            variant="ghostDanger"
                            size="icon"
                            :aria-label="`Xóa ${row.name}`"
                            :disabled="branches.length === 1"
                            @click="remove(row)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </template>
                <template #empty
                    ><EmptyState title="Chưa có cơ sở"
                        ><template #icon><Building2 /></template></EmptyState
                ></template>
            </DataTable>
        </div>
    </AppLayout>
</template>
