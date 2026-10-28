<script setup lang="ts">
import AuditLogTable from '@/components/admin/AuditLogTable.vue';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { auditActionLabel, type AuditLogRow, type Paginated } from '@/types/admin';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    logs: Paginated<AuditLogRow>;
    filters: { action: string; q: string };
    actions: string[];
}>();

const q = ref(props.filters.q);
const action = ref(props.filters.action);
const apply = () =>
    router.get(route('admin.audit-logs.index'), { q: q.value || undefined, action: action.value || undefined }, { preserveState: true, replace: true });
</script>

<template>
    <AdminLayout title="Nhật ký hoạt động">
        <Head title="Nhật ký hoạt động" />

        <form class="mb-4 flex flex-wrap items-center gap-3" @submit.prevent="apply">
            <div class="relative min-w-[240px] flex-1">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-stone-400" />
                <Input v-model="q" class="pl-9" placeholder="Tìm theo tên shop, slug" aria-label="Tìm shop" />
            </div>
            <select v-model="action" class="h-9 rounded-md border border-input bg-background px-3 text-sm" aria-label="Lọc hành động" @change="apply">
                <option value="">Mọi hành động</option>
                <option v-for="a in actions" :key="a" :value="a">{{ auditActionLabel(a) }}</option>
            </select>
        </form>

        <AuditLogTable :logs="logs" show-tenant />
    </AdminLayout>
</template>
