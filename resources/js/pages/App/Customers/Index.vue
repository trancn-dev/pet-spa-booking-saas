<script setup lang="ts">
import DataTable, { type Column } from '@/components/pb/DataTable.vue';
import EmptyState from '@/components/pb/EmptyState.vue';
import FormField from '@/components/pb/FormField.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import Pagination from '@/components/pb/Pagination.vue';
import RingAvatar from '@/components/pb/RingAvatar.vue';
import SearchField from '@/components/pb/SearchField.vue';
import TabsBar from '@/components/pb/TabsBar.vue';
import PetAlertBadges from '@/components/PetAlertBadges.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay, formatMoney } from '@/lib/format';
import type { PetAlert, PetSummary, SpeciesOption } from '@/types/pets';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, PawPrint, Plus } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface CustomerRow {
    id: string;
    name: string;
    phone: string | null;
    pets_count: number;
    pets: { id: string; name: string; alerts: PetAlert[] }[];
    last_visit_on: string | null;
    total_spent: string;
}

const props = defineProps<{
    tab: 'customers' | 'strays';
    filters: { q: string };
    customers: {
        data: CustomerRow[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    strays: PetSummary[];
    branch: string | null;
    species: SpeciesOption[];
    can: { create: boolean; createStray: boolean };
}>();

const columns: Column[] = [
    { key: 'name', label: 'Khách', width: 'minmax(14rem,2fr)' },
    { key: 'phone', label: 'SĐT', width: '10rem' },
    { key: 'pets', label: 'Thú cưng', width: 'minmax(12rem,2fr)' },
    { key: 'last_visit_on', label: 'Lần đến gần nhất', width: '10rem' },
    { key: 'total_spent', label: 'Tổng chi', width: '9rem', align: 'right' },
];
const initials = (name: string) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(-2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();

const q = ref(props.filters.q);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('app.customers.index'), value ? { q: value } : {}, { preserveState: true, replace: true, only: ['customers', 'filters'] });
    }, 300);
});

const strayForm = useForm({ species_id: props.species[0]?.id ?? null, note: '' });
const showStrayForm = ref(false);
const createStray = () => strayForm.post(route('app.pets.strays.store'));
</script>

<template>
    <Head title="Khách & Thú cưng" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <PageHeader title="Khách & Thú cưng" subtitle="Tra theo SĐT, tên khách hoặc tên thú cưng. Hồ sơ dùng chung mọi cơ sở.">
                <template #actions>
                    <Button v-if="can.createStray" variant="outline" @click="showStrayForm = !showStrayForm">
                        <PawPrint class="size-4" /> Thú hoang
                    </Button>
                    <Button v-if="can.create" as-child>
                        <Link :href="route('app.customers.create')"><Plus class="size-4" /> Thêm khách</Link>
                    </Button>
                </template>
            </PageHeader>

            <form
                v-if="showStrayForm"
                class="grid gap-4 rounded-[10px] border bg-card p-4 sm:grid-cols-[12rem_1fr_auto] sm:items-end"
                @submit.prevent="createStray"
            >
                <FormField label="Loài" input-id="stray_species" :error="strayForm.errors.species_id"
                    ><select id="stray_species" v-model="strayForm.species_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="s in species" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select></FormField
                >
                <FormField label="Lưu ý an toàn (tùy chọn)" input-id="stray_note" :error="strayForm.errors.note"
                    ><Input id="stray_note" v-model="strayForm.note" maxlength="255" placeholder="vd: cắn người, bị thương ở chân"
                /></FormField>
                <Button type="submit" :disabled="strayForm.processing">
                    <LoaderCircle v-if="strayForm.processing" class="size-4 animate-spin" />
                    Tạo "Thú hoang #n" ở {{ branch }}
                </Button>
            </form>

            <TabsBar
                :model-value="tab"
                :items="[
                    { value: 'customers', label: 'Khách' },
                    { value: 'strays', label: branch ? `Thú hoang · ${branch}` : 'Thú hoang' },
                ]"
                label="Danh sách"
                @update:model-value="(v) => router.get(route('app.customers.index'), v === 'strays' ? { tab: 'strays' } : {})"
            />

            <template v-if="tab === 'customers'">
                <SearchField v-model="q" class="max-w-md" placeholder="Tìm tên, SĐT, tên pet" label="Tìm khách" />

                <DataTable :columns="columns" :rows="customers.data" row-key="id">
                    <template #cell-name="{ row }">
                        <Link :href="route('app.customers.show', row.id)" class="flex items-center gap-3 font-medium hover:underline">
                            <RingAvatar :initials="initials(row.name)" color="hsl(var(--border))" :size="36" />
                            <span class="truncate">{{ row.name }}</span>
                        </Link>
                    </template>
                    <template #cell-phone="{ row }"
                        ><span class="font-mono text-[13px] tabular-nums">{{ row.phone }}</span></template
                    >
                    <template #cell-pets="{ row }">
                        <span v-if="!row.pets_count" class="text-muted-foreground">Chưa có</span>
                        <div v-else class="flex flex-col gap-1">
                            <div v-for="p in row.pets" :key="p.id" class="flex flex-wrap items-center gap-2">
                                <Link :href="route('app.pets.show', p.id)" class="hover:underline">{{ p.name }}</Link>
                                <PetAlertBadges :alerts="p.alerts" compact />
                            </div>
                        </div>
                    </template>
                    <template #cell-last_visit_on="{ row }"
                        ><span class="tabular-nums">{{ formatDay(row.last_visit_on) }}</span></template
                    >
                    <template #cell-total_spent="{ row }"
                        ><span class="font-mono text-[13px] tabular-nums">{{ formatMoney(row.total_spent) }}</span></template
                    >
                    <template #empty>
                        <EmptyState compact :title="filters.q ? undefined : 'Chưa có khách nào'">
                            {{ filters.q ? `Không tìm thấy khách nào khớp "${filters.q}".` : 'Thêm khách đầu tiên để bắt đầu đặt lịch.' }}
                            <template v-if="can.create && !filters.q" #action
                                ><Button size="sm" as-child><Link :href="route('app.customers.create')">Thêm khách</Link></Button></template
                            >
                        </EmptyState>
                    </template>
                </DataTable>

                <div v-if="customers.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <span class="text-muted-foreground"
                        >Trang {{ customers.current_page }} / {{ customers.last_page }} · {{ customers.total }} khách</span
                    >
                    <Pagination :links="customers.links" />
                </div>
            </template>

            <template v-else>
                <p class="text-sm text-muted-foreground">
                    Thú không có chủ được nhận ở {{ branch ?? 'cơ sở đang chọn' }}. Tạo nhanh bằng nút "Thú hoang"; ai trả tiền thì ghi ở hóa đơn.
                </p>
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <li v-for="p in strays" :key="p.id" class="grid gap-2 rounded-[10px] border bg-card p-4">
                        <Link :href="route('app.pets.show', p.id)" class="font-medium hover:underline">{{ p.name }}</Link>
                        <span class="text-sm text-muted-foreground"
                            >{{ p.species }}<template v-if="p.breed"> · {{ p.breed }}</template> · cỡ {{ p.size_label }}</span
                        >
                        <PetAlertBadges :alerts="p.alerts" />
                    </li>
                    <li v-if="!strays.length" class="col-span-full"><EmptyState compact>Chưa có thú hoang nào ở cơ sở này.</EmptyState></li>
                </ul>
            </template>
        </div>
    </AppLayout>
</template>
