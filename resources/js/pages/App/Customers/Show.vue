<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BackLink from '@/components/pb/BackLink.vue';
import DataTable, { type Column } from '@/components/pb/DataTable.vue';
import EmptyState from '@/components/pb/EmptyState.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import StatusBadge from '@/components/pb/StatusBadge.vue';
import TabsBar from '@/components/pb/TabsBar.vue';
import PetAlertBadges from '@/components/PetAlertBadges.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime, formatDay, formatMoney } from '@/lib/format';
import type { PetSummary } from '@/types/pets';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Lock, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    customer: {
        id: string;
        name: string;
        phone: string | null;
        email: string | null;
        address: string | null;
        notes: string | null;
        is_system: boolean;
    };
    pets: PetSummary[];
    appointments: { id: string; starts_at: string; branch: string; status: string; status_label: string; items_count: number }[];
    invoices: { id: string; code: string | null; business_date: string; branch: string; status: string; status_label: string; total: string }[];
    timezone: string | null;
    can: { update: boolean; delete: boolean; viewPets: boolean; createPet: boolean; viewAppointments: boolean; viewInvoices: boolean };
}>();

const page = usePage();
const deleteError = computed(() => (page.props.errors as Record<string, string | undefined>).customer);

const destroy = () => {
    if (!confirm(`Xóa hồ sơ ${props.customer.name} và các bé của khách? Hóa đơn, lịch sử vẫn được giữ.`)) return;
    router.delete(route('app.customers.destroy', props.customer.id));
};

const appointmentColumns: Column[] = [
    { key: 'starts_at', label: 'Giờ hẹn', width: '12rem' },
    { key: 'branch', label: 'Cơ sở', width: 'minmax(8rem,1fr)' },
    { key: 'items_count', label: 'Số dịch vụ', width: '8rem' },
    { key: 'status', label: 'Trạng thái', width: '10rem' },
];
const invoiceColumns: Column[] = [
    { key: 'code', label: 'Mã', width: '9rem' },
    { key: 'business_date', label: 'Ngày', width: '9rem' },
    { key: 'branch', label: 'Cơ sở', width: 'minmax(8rem,1fr)' },
    { key: 'status', label: 'Trạng thái', width: '11rem' },
    { key: 'total', label: 'Tổng', width: '9rem', align: 'right' },
];

const tab = ref<'pets' | 'appointments' | 'invoices'>(props.can.viewPets ? 'pets' : props.can.viewAppointments ? 'appointments' : 'invoices');
const tabItems = computed(() => [
    ...(props.can.viewPets ? [{ value: 'pets', label: 'Thú cưng', count: props.pets.length }] : []),
    ...(props.can.viewAppointments ? [{ value: 'appointments', label: 'Lịch hẹn', count: props.appointments.length }] : []),
    ...(props.can.viewInvoices ? [{ value: 'invoices', label: 'Hóa đơn', count: props.invoices.length }] : []),
]);
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout>
        <div class="flex max-w-5xl flex-col gap-6 p-4 md:p-6">
            <PageHeader :title="customer.name">
                <template #before><BackLink :href="route('app.customers.index')">Khách & Thú cưng</BackLink></template>
                <template v-if="customer.is_system" #subtitle>
                    <span class="flex items-center gap-1.5"><Lock class="size-4" /> Hồ sơ hệ thống của cơ sở: không sửa, không xóa.</span>
                </template>
                <template #actions>
                    <Button v-if="can.update" variant="outline" as-child>
                        <Link :href="route('app.customers.edit', customer.id)"><Pencil class="size-4" /> Sửa</Link>
                    </Button>
                    <Button v-if="can.delete" variant="outline" class="text-danger" @click="destroy"><Trash2 class="size-4" /> Xóa</Button>
                </template>
            </PageHeader>
            <InputError :message="deleteError" />

            <section v-if="!customer.is_system" class="grid gap-3 rounded-[10px] border bg-card p-6 sm:grid-cols-3">
                <div>
                    <div class="text-xs text-muted-foreground">SĐT</div>
                    <div class="tabular-nums">{{ customer.phone }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Email</div>
                    <div>{{ customer.email ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Địa chỉ</div>
                    <div>{{ customer.address ?? '—' }}</div>
                </div>
                <div v-if="customer.notes" class="sm:col-span-3">
                    <div class="text-xs text-muted-foreground">Ghi chú</div>
                    <div class="whitespace-pre-line">{{ customer.notes }}</div>
                </div>
            </section>

            <TabsBar v-model="tab" label="Hồ sơ khách" :items="tabItems" />

            <section v-if="can.viewPets && tab === 'pets'" class="grid gap-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold">Thú cưng</h2>
                    <Button v-if="can.createPet && !customer.is_system" size="sm" as-child>
                        <Link :href="route('app.pets.create', customer.id)"><Plus class="size-4" /> Thêm bé</Link>
                    </Button>
                </div>
                <ul class="grid gap-3 sm:grid-cols-2">
                    <li v-for="p in pets" :key="p.id" class="grid gap-2 rounded-[10px] border bg-card p-4">
                        <Link :href="route('app.pets.show', p.id)" class="font-medium hover:underline">{{ p.name }}</Link>
                        <span class="text-sm text-muted-foreground">
                            {{ p.species }}<template v-if="p.breed"> · {{ p.breed }}</template> · {{ p.sex_label
                            }}<template v-if="p.age"> · {{ p.age }}</template> · cỡ {{ p.size_label }}
                        </span>
                        <PetAlertBadges :alerts="p.alerts" />
                    </li>
                    <li v-if="!pets.length" class="col-span-full"><EmptyState compact>Khách chưa có bé nào.</EmptyState></li>
                </ul>
            </section>

            <section v-if="can.viewAppointments && tab === 'appointments'" class="grid gap-3">
                <DataTable :columns="appointmentColumns" :rows="appointments" row-key="id">
                    <template #cell-starts_at="{ row }"
                        ><span class="tabular-nums">{{ formatDateTime(row.starts_at, timezone) }}</span></template
                    >
                    <template #cell-status="{ row }"><StatusBadge :status="row.status" :label="row.status_label" /></template>
                    <template #empty><EmptyState compact>Chưa có lịch hẹn.</EmptyState></template>
                </DataTable>
            </section>

            <section v-if="can.viewInvoices && tab === 'invoices'" class="grid gap-3">
                <DataTable :columns="invoiceColumns" :rows="invoices" row-key="id">
                    <template #cell-code="{ row }"
                        ><span class="font-mono text-[13px]">{{ row.code ?? 'Nháp' }}</span></template
                    >
                    <template #cell-business_date="{ row }"
                        ><span class="tabular-nums">{{ formatDay(row.business_date) }}</span></template
                    >
                    <template #cell-status="{ row }"><StatusBadge :status="row.status" :label="row.status_label" /></template>
                    <template #cell-total="{ row }"
                        ><span class="font-mono text-[13px] tabular-nums">{{ formatMoney(row.total) }}</span></template
                    >
                    <template #empty><EmptyState compact>Chưa có hóa đơn.</EmptyState></template>
                </DataTable>
            </section>
        </div>
    </AppLayout>
</template>
