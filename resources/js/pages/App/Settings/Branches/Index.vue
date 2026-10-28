<script setup lang="ts">
import InputError from '@/components/InputError.vue';
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
</script>

<template>
    <Head title="Cơ sở" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Cơ sở</h1>
                    <p class="text-sm" :class="full ? 'text-danger' : near ? 'text-warning' : 'text-muted-foreground'">
                        Đang dùng {{ limit.used }}/{{ limit.max ?? '∞' }} cơ sở theo gói
                    </p>
                </div>
                <Button v-if="!full" as-child>
                    <Link :href="route('app.settings.branches.create')"><Plus /> Thêm cơ sở</Link>
                </Button>
                <Button v-else disabled title="Đã đủ số cơ sở của gói. Liên hệ PawBook để nâng gói."><Plus /> Thêm cơ sở</Button>
            </div>

            <InputError :message="errors.branch" />

            <div class="overflow-x-auto rounded-[10px] border bg-card">
                <table class="w-full text-sm">
                    <thead class="bg-muted text-left text-[13px] font-medium text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Mã</th>
                            <th class="px-4 py-3">Tên</th>
                            <th class="px-4 py-3">Địa chỉ</th>
                            <th class="px-4 py-3">Điện thoại</th>
                            <th class="px-4 py-3">Trạng thái</th>
                            <th class="px-4 py-3"><span class="sr-only">Thao tác</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in branches" :key="b.id" class="border-t hover:bg-muted/50">
                            <td class="px-4 py-3 font-mono">{{ b.code }}</td>
                            <td class="px-4 py-3 font-medium">{{ b.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ b.address ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ b.phone ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-md px-2 py-0.5 text-xs font-medium"
                                    :class="b.is_active ? 'bg-primary-soft text-primary' : 'bg-muted text-muted-foreground'"
                                >
                                    {{ b.is_active ? 'Đang hoạt động' : 'Tạm ngưng' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" as-child>
                                        <Link :href="route('app.settings.branches.edit', b.id)" :aria-label="`Sửa ${b.name}`"><Pencil /></Link>
                                    </Button>
                                    <Button variant="ghost" size="icon" :aria-label="`Xóa ${b.name}`" :disabled="branches.length === 1" @click="remove(b)">
                                        <Trash2 class="text-danger" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="!branches.length" class="flex flex-col items-center gap-2 px-6 py-16 text-center">
                    <Building2 class="size-10 text-subtle" />
                    <p class="font-medium">Chưa có cơ sở</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
