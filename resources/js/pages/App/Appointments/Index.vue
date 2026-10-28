<script setup lang="ts">
import BookingDrawer from '@/components/appointments/BookingDrawer.vue';
import CalendarGrid, { type GridColumn } from '@/components/appointments/CalendarGrid.vue';
import DetailDrawer from '@/components/appointments/DetailDrawer.vue';
import PetAlertBadges from '@/components/PetAlertBadges.vue';
import Toaster from '@/components/Toaster.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { useToast } from '@/composables/useToast';
import { api, errorMessage } from '@/lib/api';
import { type CalendarBlock, type CalendarDayData, type Group, groupLabel, hhmm, minutes, type ServiceOption } from '@/types/calendar';
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

const { show } = useToast();
const toast = { success: (m: string) => show(m, 'success'), error: (m: string) => show(m, 'danger') };
const groupFilter = ref<Group | 'all'>('all');
const q = ref('');
const staffFilter = ref<number | 'all'>('all');
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
    const staffOk = staffFilter.value === 'all' || b.staff_id === staffFilter.value;
    return groupOk && staffOk && (!term || b.customer.toLowerCase().includes(term) || b.pets.some((p) => p.name.toLowerCase().includes(term)));
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
    const people = day.staff.filter((s) => (staffFilter.value === 'all' || s.id === staffFilter.value) && (groupFilter.value === 'all' || s.groups.includes(groupFilter.value) || blocks.some((b) => b.staff_id === s.id)));
    return [
        ...(staffFilter.value === 'all' ? [{ key: 'none', label: 'Chưa phân', sub: 'Lịch online, phân khi khách đến', blocks: blocks.filter((b) => b.staff_id === null) }] : []),
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

// Drag a block to a new time: the whole appointment shifts by the same amount.
const move = async (block: CalendarBlock, _column: GridColumn, time: string) => {
    const first = Math.min(...(props.day?.blocks ?? []).filter((b) => b.appointment === block.appointment).map((b) => minutes(b.start)));
    const target = minutes(time) - (minutes(block.start) - first);
    if (target === first) return;
    const newStart = hhmm(target);
    const r = await api<{ message?: string; warnings?: string[] }>('POST', route('app.appointments.reschedule', { appointment: block.appointment }), { date: props.date, time: newStart });
    if (r.ok) {
        toast.success(`Đã dời lịch sang ${newStart}.${r.data.warnings?.length ? ` ${r.data.warnings.join(' ')}` : ''}`);
        refresh();
    } else {
        toast.error(errorMessage(r));
    }
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
                    <select v-if="day" v-model="staffFilter" class="h-9 rounded-md border border-input bg-background px-2 text-sm" aria-label="Lọc nhân viên">
                        <option value="all">Mọi nhân viên</option>
                        <option v-for="s in day.staff" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
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

            <!-- phone: list by time -->
            <ul v-if="day" class="space-y-2 md:hidden" aria-label="Lịch hẹn theo giờ">
                <li v-if="day.closed" class="rounded-[10px] border bg-card p-3 text-sm font-medium">Cơ sở nghỉ<template v-if="day.closed.reason">: {{ day.closed.reason }}</template></li>
                <li v-else-if="!day.blocks.filter(matches).length" class="rounded-[10px] border bg-card p-3 text-sm text-muted-foreground">Chưa có lịch hẹn.</li>
                <li v-for="b in [...day.blocks.filter(matches)].sort((a, c) => minutes(a.start) - minutes(c.start))" :key="b.key">
                    <button
                        type="button"
                        class="w-full rounded-[10px] border border-l-4 bg-card p-3 text-left"
                        :class="[b.group === 'medical' ? 'border-l-info' : 'border-l-coral', { 'opacity-50': b.line_status === 'cancelled' || b.line_status === 'no_show' }]"
                        @click="openDetail(b)"
                    >
                        <div class="flex items-center justify-between text-sm font-medium">
                            <span class="tabular-nums">{{ b.start }}–{{ b.end }}</span>
                            <span class="text-xs text-muted-foreground">{{ groupLabel[b.group] }}<template v-if="b.source === 'online'"> · Online</template></span>
                        </div>
                        <div class="text-sm">{{ b.pets.map((p) => `${p.name} (${p.service})`).join(', ') }}</div>
                        <div class="text-xs text-muted-foreground">{{ b.customer }}<template v-if="!b.staff_id"> · Chưa phân</template></div>
                        <PetAlertBadges v-for="(p, i) in b.pets.filter((p) => p.alerts.length)" :key="i" :alerts="p.alerts" compact />
                    </button>
                </li>
            </ul>

            <CalendarGrid
                v-if="day"
                class="hidden md:block"
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
                @move="move"
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
