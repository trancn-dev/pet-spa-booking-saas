<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PetAlertBadges from '@/components/PetAlertBadges.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime, formatDay, formatMoney } from '@/lib/format';
import type { PetSummary } from '@/types/pets';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Lock, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    customer: { id: string; name: string; phone: string | null; email: string | null; address: string | null; notes: string | null; is_system: boolean };
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
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout>
        <div class="flex max-w-5xl flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <Link :href="route('app.customers.index')" class="text-sm text-muted-foreground hover:underline">← Khách & Thú cưng</Link>
                    <h1 class="mt-1 text-2xl font-semibold">{{ customer.name }}</h1>
                    <p v-if="customer.is_system" class="flex items-center gap-1.5 text-sm text-muted-foreground">
                        <Lock class="size-4" /> Hồ sơ hệ thống của cơ sở: không sửa, không xóa.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button v-if="can.update" variant="outline" as-child>
                        <Link :href="route('app.customers.edit', customer.id)"><Pencil class="size-4" /> Sửa</Link>
                    </Button>
                    <Button v-if="can.delete" variant="outline" class="text-danger" @click="destroy"><Trash2 class="size-4" /> Xóa</Button>
                </div>
            </div>
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

            <section v-if="can.viewPets" class="grid gap-3">
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
                            {{ p.species }}<template v-if="p.breed"> · {{ p.breed }}</template> · {{ p.sex_label }}<template v-if="p.age">
                                · {{ p.age }}</template
                            >
                            · cỡ {{ p.size_label }}
                        </span>
                        <PetAlertBadges :alerts="p.alerts" />
                    </li>
                    <li v-if="!pets.length" class="col-span-full rounded-[10px] border border-dashed p-6 text-center text-sm text-muted-foreground">
                        Khách chưa có bé nào.
                    </li>
                </ul>
            </section>

            <section v-if="can.viewAppointments" class="grid gap-3">
                <h2 class="text-xl font-semibold">Lịch hẹn</h2>
                <div class="overflow-x-auto rounded-[10px] border bg-card">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-surface-muted text-left text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Giờ hẹn</th>
                                <th class="px-4 py-2 font-medium">Cơ sở</th>
                                <th class="px-4 py-2 font-medium">Số dịch vụ</th>
                                <th class="px-4 py-2 font-medium">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in appointments" :key="a.id" class="border-b last:border-0">
                                <td class="whitespace-nowrap px-4 py-3">{{ formatDateTime(a.starts_at, timezone) }}</td>
                                <td class="px-4 py-3">{{ a.branch }}</td>
                                <td class="px-4 py-3">{{ a.items_count }}</td>
                                <td class="px-4 py-3">{{ a.status_label }}</td>
                            </tr>
                            <tr v-if="!appointments.length">
                                <td colspan="4" class="px-4 py-6 text-center text-muted-foreground">Chưa có lịch hẹn.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="can.viewInvoices" class="grid gap-3">
                <h2 class="text-xl font-semibold">Hóa đơn</h2>
                <div class="overflow-x-auto rounded-[10px] border bg-card">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-surface-muted text-left text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Mã</th>
                                <th class="px-4 py-2 font-medium">Ngày</th>
                                <th class="px-4 py-2 font-medium">Cơ sở</th>
                                <th class="px-4 py-2 font-medium">Trạng thái</th>
                                <th class="px-4 py-2 text-right font-medium">Tổng</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="i in invoices" :key="i.id" class="border-b last:border-0">
                                <td class="px-4 py-3 tabular-nums">{{ i.code ?? 'Nháp' }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ formatDay(i.business_date) }}</td>
                                <td class="px-4 py-3">{{ i.branch }}</td>
                                <td class="px-4 py-3">{{ i.status_label }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ formatMoney(i.total) }}</td>
                            </tr>
                            <tr v-if="!invoices.length">
                                <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">Chưa có hóa đơn.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
