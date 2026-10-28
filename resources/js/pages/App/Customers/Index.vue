<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import EmptyState from '@/components/pb/EmptyState.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import PetAlertBadges from '@/components/PetAlertBadges.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDay, formatMoney } from '@/lib/format';
import type { PetAlert, PetSummary, SpeciesOption } from '@/types/pets';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, PawPrint, Plus, Search } from 'lucide-vue-next';
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
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    strays: PetSummary[];
    branch: string | null;
    species: SpeciesOption[];
    can: { create: boolean; createStray: boolean };
}>();

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
            <PageHeader title="Khách & Thú cưng" subtitle="Hồ sơ khách theo SĐT, dùng chung mọi cơ sở.">
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
                <div class="grid gap-2">
                    <Label for="stray_species">Loài</Label>
                    <select id="stray_species" v-model="strayForm.species_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="s in species" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <InputError :message="strayForm.errors.species_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="stray_note">Lưu ý an toàn (tùy chọn)</Label>
                    <Input id="stray_note" v-model="strayForm.note" maxlength="255" placeholder="vd: cắn người, bị thương ở chân" />
                    <InputError :message="strayForm.errors.note" />
                </div>
                <Button type="submit" :disabled="strayForm.processing">
                    <LoaderCircle v-if="strayForm.processing" class="size-4 animate-spin" />
                    Tạo "Thú hoang #n" ở {{ branch }}
                </Button>
            </form>

            <nav class="flex gap-1 border-b" aria-label="Danh sách">
                <Link
                    :href="route('app.customers.index')"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium"
                    :class="tab === 'customers' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground'"
                    >Khách</Link
                >
                <Link
                    :href="route('app.customers.index', { tab: 'strays' })"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium"
                    :class="tab === 'strays' ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground'"
                    >Thú hoang<span v-if="branch"> · {{ branch }}</span></Link
                >
            </nav>

            <template v-if="tab === 'customers'">
                <div class="relative max-w-md">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="q" class="pl-9" placeholder="Tên khách, tên bé, SĐT hoặc 4 số cuối" aria-label="Tìm khách" />
                </div>

                <div class="overflow-x-auto rounded-[10px] border bg-card">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-surface-muted text-left text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Khách</th>
                                <th class="px-4 py-2 font-medium">SĐT</th>
                                <th class="px-4 py-2 font-medium">Thú cưng</th>
                                <th class="px-4 py-2 font-medium">Lần đến gần nhất</th>
                                <th class="px-4 py-2 text-right font-medium">Tổng chi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in customers.data" :key="c.id" class="border-b last:border-0 hover:bg-surface-muted/50">
                                <td class="px-4 py-3">
                                    <Link :href="route('app.customers.show', c.id)" class="font-medium hover:underline">{{ c.name }}</Link>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums">{{ c.phone }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-1">
                                        <span v-if="!c.pets_count" class="text-muted-foreground">Chưa có</span>
                                        <div v-for="p in c.pets" :key="p.id" class="flex flex-wrap items-center gap-2">
                                            <Link :href="route('app.pets.show', p.id)" class="hover:underline">{{ p.name }}</Link>
                                            <PetAlertBadges :alerts="p.alerts" compact />
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">{{ formatDay(c.last_visit_on) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ formatMoney(c.total_spent) }}</td>
                            </tr>
                            <tr v-if="!customers.data.length">
                                <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">
                                    {{ filters.q ? `Không tìm thấy khách nào khớp "${filters.q}".` : 'Chưa có khách nào.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="customers.last_page > 1" class="flex items-center justify-between text-sm">
                    <span class="text-muted-foreground"
                        >Trang {{ customers.current_page }} / {{ customers.last_page }} · {{ customers.total }} khách</span
                    >
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" :disabled="!customers.prev_page_url" as-child>
                            <Link :href="customers.prev_page_url ?? '#'" preserve-scroll>Trước</Link>
                        </Button>
                        <Button variant="outline" size="sm" :disabled="!customers.next_page_url" as-child>
                            <Link :href="customers.next_page_url ?? '#'" preserve-scroll>Sau</Link>
                        </Button>
                    </div>
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
