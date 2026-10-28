<script setup lang="ts">
import BookingDrawer from '@/components/appointments/BookingDrawer.vue';
import CalendarGrid, { type GridColumn } from '@/components/appointments/CalendarGrid.vue';
import DetailDrawer from '@/components/appointments/DetailDrawer.vue';
import PetAlertBadges from '@/components/PetAlertBadges.vue';
import Toaster from '@/components/Toaster.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { api } from '@/lib/api';
import { type CalendarBlock, type CalendarDayData, type Group, groupLabel, type ServiceOption } from '@/types/calendar';
import type { SpeciesOption } from '@/types/pets';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus, Search } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type View = 'staff' | 'day' | 'week';

const props = defineProps<{
    date: string;
    view: View;
    today: string;
    timezone: string;
    range: { from: string; to: string };
    branch: { id: number; public_id: string; name: string };
    day: CalendarDayData | null;
    week: { date: string; blocks: CalendarBlock[] }[] | null;
    quota: { used: number; max: number | null; full: boolean };
    services: ServiceOption[];
    species: SpeciesOption[];
    can: { book: boolean; createCustomer: boolean; createPet: boolean };
}>();

const groupFilter = ref<Group | 'all'>('all');
const q = ref('');
const search = ref<InstanceType<typeof Input> | null>(null);

const go = (params: { date?: string; view?: View }) =>
    router.get(route('app.appointments.index'), { date: params.date ?? props.date, view: params.view ?? props.view }, { preserveScroll: true });
const shift = (days: number) => {
    const d = new Date(`${props.date}T00:00:00`);
    d.setDate(d.getDate() + days * (props.view === 'week' ? 7 : 1));
    go({ date: d.toLocaleDateString('sv-SE') });
};
const refresh = () => router.reload({ only: ['day', 'week', 'quota'] });

// Now, in the shop's timezone.
const nowTime = ref('');
const tick = () => (nowTime.value = new Intl.DateTimeFormat('en-GB', { timeZone: props.timezone, hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date()));
let clock: ReturnType<typeof setInterval> | undefined;

const matches = (b: CalendarBlock) => {
    const term = q.value.trim().toLowerCase();
    const groupOk = groupFilter.value === 'all' || b.group === groupFilter.value;
    return groupOk && (!term || b.customer.toLowerCase().includes(term) || b.pets.some((p) => p.name.toLowerCase().includes(term)));
};

const staffColors = computed(() => Object.fromEntries((props.day?.staff ?? []).map((s) => [s.id, s.color])));

const columns = computed<GridColumn[]>(() => {
    const day = props.day;
    if (!day) return [];
    const blocks = day.blocks.filter(matches);
    if (props.view === 'day') {
        return (['medical', 'grooming'] as Group[])
            .filter((g) => groupFilter.value === 'all' || groupFilter.value === g)
            .map((g) => ({ key: g, label: groupLabel[g], blocks: blocks.filter((b) => b.group === g) }));
    }
    const people = day.staff.filter((s) => groupFilter.value === 'all' || s.groups.includes(groupFilter.value) || blocks.some((b) => b.staff_id === s.id));
    return [
        { key: 'none', label: 'Chưa phân', sub: 'Lịch online, phân khi khách đến', blocks: blocks.filter((b) => b.staff_id === null) },
        ...people.map((s) => ({
            key: `s${s.id}`,
            label: s.name,
            sub: `${s.count} lịch · ${s.groups.map((g) => groupLabel[g]).join(', ') || 'Không tính'}`,
            color: s.color,
            staffId: s.id,
            duty: s.slots,
            off: s.off,
            blocks: blocks.filter((b) => b.staff_id === s.id),
        })),
    ];
});

// Drawers
const booking = ref({ open: false, time: null as string | null, staffId: null as number | null });
const openBooking = (time: string | null = null, staffId: number | null = null) => {
    if (!props.can.book) return;
    booking.value = { open: true, time, staffId };
};
const detail = ref<string | null>(null);
const onDuty = ref<{ id: number; name: string; groups: Group[] }[]>([]);
const openDetail = async (block: CalendarBlock) => {
    detail.value = block.appointment;
    const r = await api<{ data: typeof onDuty.value }>('GET', route('app.appointments.on-duty', { date: props.date }));
    onDuty.value = r.ok ? r.data.data : [];
};

// Shortcuts: N = new booking, / = search (not while typing).
const onKey = (e: KeyboardEvent) => {
    const typing = e.target instanceof HTMLElement && (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable);
    if (typing || e.metaKey || e.ctrlKey || e.altKey) return;
    if (e.key === 'n' || e.key === 'N') {
        e.preventDefault();
        openBooking();
    } else if (e.key === '/') {
        e.preventDefault();
        (search.value?.$el as HTMLInputElement | undefined)?.focus();
    }
};
onMounted(() => {
    tick();
    clock = setInterval(tick, 60_000);
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    clearInterval(clock);
    window.removeEventListener('keydown', onKey);
});

const title = computed(() => {
    const [y, m, d] = props.date.split('-');
    return `${['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][new Date(`${props.date}T00:00:00`).getDay()]} ${d}/${m}/${y}`;
});
const views: { value: View; label: string }[] = [
    { value: 'day', label: 'Ngày' },
    { value: 'week', label: 'Tuần' },
    { value: 'staff', label: 'Theo nhân viên' },
];
</script>

<template>
    <Head title="Lịch hẹn" />

    <AppLayout>
        <div class="flex flex-col gap-4 p-4 md:p-6">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="mr-2 text-2xl font-semibold">Lịch hẹn · {{ branch.name }}</h1>
                <div class="flex items-center gap-1">
                    <Button variant="outline" size="icon" aria-label="Trước" @click="shift(-1)"><ChevronLeft class="size-4" /></Button>
                    <Button variant="outline" size="sm" @click="go({ date: today })">Hôm nay</Button>
                    <Button variant="outline" size="icon" aria-label="Sau" @click="shift(1)"><ChevronRight class="size-4" /></Button>
                </div>
                <Input :model-value="date" type="date" class="w-40" aria-label="Chọn ngày" @update:model-value="(v) => v && go({ date: String(v) })" />
                <span class="text-sm text-muted-foreground">{{ title }}</span>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <div class="flex rounded-md border p-0.5" role="tablist" aria-label="Chế độ xem">
                        <button
                            v-for="v in views"
                            :key="v.value"
                            type="button"
                            role="tab"
                            :aria-selected="view === v.value"
                            class="rounded px-2.5 py-1 text-sm"
                            :class="view === v.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                            @click="go({ view: v.value })"
                        >
                            {{ v.label }}
                        </button>
                    </div>
                    <select v-model="groupFilter" class="h-9 rounded-md border border-input bg-background px-2 text-sm" aria-label="Lọc nhóm">
                        <option value="all">Khám + Spa</option>
                        <option value="medical">Khám</option>
                        <option value="grooming">Spa</option>
                    </select>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input ref="search" v-model="q" class="w-48 pl-8" placeholder="Tìm khách, bé ( / )" aria-label="Tìm trong lịch" />
                    </div>
                    <Button v-if="can.book" :disabled="quota.full" :title="quota.full ? `Đã đạt ${quota.used}/${quota.max} lịch tháng này` : 'Phím tắt N'" @click="openBooking()">
                        <Plus class="size-4" /> Đặt lịch
                    </Button>
                </div>
            </div>

            <div v-if="quota.full" class="rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger" role="alert">
                Đã đạt {{ quota.used }}/{{ quota.max }} lịch tháng này. Liên hệ để nâng gói.
            </div>

            <CalendarGrid
                v-if="day"
                :columns="columns"
                :from="range.from"
                :to="range.to"
                :open="day.open"
                :closed="day.closed"
                :capacity="day.capacity"
                :now="date === today ? nowTime : null"
                :staff-colors="staffColors"
                @cell="(c, t) => openBooking(t, c.staffId ?? null)"
                @block="openDetail"
            />

            <div v-else-if="week" class="grid gap-2 overflow-x-auto md:grid-cols-7">
                <section v-for="d in week" :key="d.date" class="min-w-[10rem] rounded-[10px] border bg-card p-2" :class="{ 'ring-2 ring-primary': d.date === today }">
                    <button type="button" class="mb-2 text-sm font-medium hover:underline" @click="go({ date: d.date, view: 'staff' })">
                        {{ ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][new Date(`${d.date}T00:00:00`).getDay()] }} {{ d.date.slice(8) }}/{{ d.date.slice(5, 7) }}
                    </button>
                    <ul class="space-y-1">
                        <li v-for="b in d.blocks.filter(matches)" :key="b.key">
                            <button
                                type="button"
                                class="w-full rounded border px-1.5 py-1 text-left text-xs"
                                :class="[b.group === 'medical' ? 'bg-info/10' : 'bg-coral/10', { 'opacity-50 line-through': b.line_status === 'cancelled' || b.line_status === 'no_show' }]"
                                @click="openDetail(b)"
                            >
                                <span class="font-medium">{{ b.start }}</span> {{ b.pets.map((p) => p.name).join(', ') }}
                                <PetAlertBadges v-for="(p, i) in b.pets.filter((p) => p.alerts.length)" :key="i" :alerts="p.alerts" compact />
                            </button>
                        </li>
                        <li v-if="!d.blocks.filter(matches).length" class="text-xs text-muted-foreground">Trống</li>
                    </ul>
                </section>
            </div>
        </div>

        <BookingDrawer
            v-model:open="booking.open"
            :date="date"
            :time="booking.time"
            :staff-id="booking.staffId"
            :services="services"
            :species="species"
            :quota="quota"
            :can="can"
            @booked="refresh"
        />
        <DetailDrawer v-model:appointment="detail" :today="today" :on-duty="onDuty" @changed="refresh" />
        <Toaster />
    </AppLayout>
</template>
