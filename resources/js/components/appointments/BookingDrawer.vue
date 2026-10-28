<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppDrawer from '@/components/pb/AppDrawer.vue';
import Banner from '@/components/pb/Banner.vue';
import Chip from '@/components/pb/Chip.vue';
import GroupBadge from '@/components/pb/GroupBadge.vue';
import RingAvatar from '@/components/pb/RingAvatar.vue';
import SlotPicker, { type SlotOption } from '@/components/pb/SlotPicker.vue';
import PetAlertBadges from '@/components/PetAlertBadges.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useToast } from '@/composables/useToast';
import { api, errorMessage } from '@/lib/api';
import { formatMoney } from '@/lib/format';
import type { ShopSharedData } from '@/types';
import { type Group, groupLabel, hhmm, minutes, type ServiceOption } from '@/types/calendar';
import type { PetAlert, SpeciesOption } from '@/types/pets';
import { usePage } from '@inertiajs/vue3';
import { AlertTriangle, Check, LoaderCircle, Plus, Search, X } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

/*
| S2 Đặt lịch (task 5.7). One idempotency key per opened form, kept across retries, so a double
| submit or a retry after a network error never books twice. The front desk is warned, never
| refused, when a slot is over the people on duty.
*/

interface Pet {
    id: string;
    name: string;
    species: string;
    size: string;
    alerts: PetAlert[];
}
interface Customer {
    id: string;
    name: string;
    phone: string | null;
    pets: Pet[];
}
interface Quote {
    duration_min: number;
    buffer_min: number;
    price: string;
    explanation: string;
}
interface Line {
    serviceId: number;
    staffId: number | null;
}
interface Slot {
    time: string;
    over: boolean;
    groups: Record<string, { on_duty: number; taken: number }>;
}

const open = defineModel<boolean>('open', { required: true });
const props = defineProps<{
    date: string;
    time: string | null;
    staffId: number | null;
    services: ServiceOption[];
    species: SpeciesOption[];
    quota: { used: number; max: number | null; full: boolean };
    can: { createCustomer: boolean; createPet: boolean };
}>();
const emit = defineEmits<{ booked: [appointment: string] }>();
const { show } = useToast();

const key = ref('');
const customer = ref<Customer | null>(null);
const q = ref('');
const results = ref<Customer[]>([]);
const searching = ref(false);
const searchInput = ref<InstanceType<typeof Input> | null>(null);
const lines = ref<Record<string, Line[]>>({}); // pet id → lines
const quotes = ref<Record<string, Record<number, Quote>>>({});
const date = ref(props.date);
const time = ref<string | null>(props.time);
const currentBranch = usePage<ShopSharedData>().props.currentBranch;
const initialsOf = (name: string) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(-2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
const slots = ref<Slot[]>([]);
const loadingSlots = ref(false);
const onDuty = ref<{ id: number; name: string; groups: Group[] }[]>([]);
const note = ref('');
const noReminders = ref(false);
const error = ref<string | null>(null);
const suggestions = ref<{ date: string; time: string }[]>([]);
const submitting = ref(false);

const reset = () => {
    key.value = crypto.randomUUID();
    customer.value = null;
    q.value = '';
    results.value = [];
    lines.value = {};
    date.value = props.date;
    time.value = props.time;
    note.value = '';
    noReminders.value = false;
    error.value = null;
    suggestions.value = [];
};

watch(open, async (isOpen) => {
    if (!isOpen) return;
    reset();
    await loadOnDuty();
    await nextTick();
    (searchInput.value?.$el as HTMLInputElement | undefined)?.focus();
});

// 1. Customer
let timer: ReturnType<typeof setTimeout> | undefined;
watch(q, (value) => {
    clearTimeout(timer);
    if (value.trim().length < 2) {
        results.value = [];
        return;
    }
    timer = setTimeout(async () => {
        searching.value = true;
        const r = await api<{ data: Customer[] }>('GET', route('app.customers.search', { q: value }));
        results.value = r.ok ? r.data.data : [];
        searching.value = false;
    }, 250);
});
const pick = (c: Customer) => {
    customer.value = c;
    results.value = [];
    lines.value = {};
    if (c.pets.length === 1) togglePet(c.pets[0]);
};

const quick = ref({ open: false, name: '', phone: '', error: null as string | null, saving: false });
const createCustomer = async () => {
    quick.value.saving = true;
    const r = await api<{ data: Customer }>('POST', route('app.customers.store'), { name: quick.value.name, phone: quick.value.phone });
    quick.value.saving = false;
    if (!r.ok) return (quick.value.error = errorMessage(r));
    quick.value = { open: false, name: '', phone: '', error: null, saving: false };
    pick(r.data.data);
};

const quickPet = ref({ open: false, name: '', species_id: props.species[0]?.id ?? null, weight_kg: '', error: null as string | null, saving: false });
const createPet = async () => {
    if (!customer.value) return;
    quickPet.value.saving = true;
    const r = await api<{ data: Pet }>('POST', route('app.pets.store', customer.value.id), {
        name: quickPet.value.name,
        species_id: quickPet.value.species_id,
        weight_kg: quickPet.value.weight_kg || null,
        size: quickPet.value.weight_kg ? null : 'm',
    });
    quickPet.value.saving = false;
    if (!r.ok) return (quickPet.value.error = errorMessage(r));
    customer.value.pets.push(r.data.data);
    togglePet(r.data.data);
    quickPet.value = { ...quickPet.value, open: false, name: '', weight_kg: '', error: null };
};

// 2. Pets & services
const selectedPets = computed(() => (customer.value?.pets ?? []).filter((p) => lines.value[p.id]));
const togglePet = async (pet: Pet) => {
    if (lines.value[pet.id]) {
        const rest = { ...lines.value };
        delete rest[pet.id];
        lines.value = rest;
        return;
    }
    lines.value = { ...lines.value, [pet.id]: [] };
    if (!quotes.value[pet.id]) {
        const r = await api<{ data: Record<number, Quote> }>(
            'GET',
            route('app.appointments.quotes', { pet: pet.id, services: props.services.map((s) => s.id) }),
        );
        if (r.ok) quotes.value = { ...quotes.value, [pet.id]: r.data.data };
    }
};
const addLine = (pet: Pet, event: Event) => {
    const id = Number((event.target as HTMLSelectElement).value);
    (event.target as HTMLSelectElement).value = '';
    if (!id || lines.value[pet.id].some((l) => l.serviceId === id)) return;
    lines.value[pet.id].push({ serviceId: id, staffId: props.staffId && staffFits(props.staffId, serviceOf(id)?.group) ? props.staffId : null });
};
const serviceOf = (id: number) => props.services.find((s) => s.id === id);
const quoteOf = (petId: string, serviceId: number): Quote | undefined => quotes.value[petId]?.[serviceId];
const staffFits = (staffId: number, group?: Group) => !!group && onDuty.value.some((s) => s.id === staffId && s.groups.includes(group));

const allLines = computed(() =>
    selectedPets.value.flatMap((p) =>
        lines.value[p.id].map((l) => ({ pet: p, line: l, service: serviceOf(l.serviceId)!, quote: quoteOf(p.id, l.serviceId) })),
    ),
);

// 3. Date & slots (front-desk view: everything in opening hours, with load per group)
const loadSlots = async () => {
    if (!allLines.value.length) return (slots.value = []);
    loadingSlots.value = true;
    const params: Record<string, string | number> = { mode: 'staff', from: date.value, to: date.value };
    allLines.value.forEach((l, i) => {
        params[`lines[${i}][service_id]`] = l.service.id;
        params[`lines[${i}][pet]`] = l.pet.id;
    });
    const r = await api<{ data: Record<string, Slot[]> }>('GET', route('app.availability', params));
    slots.value = r.ok ? (r.data.data[date.value] ?? []) : [];
    loadingSlots.value = false;
};
const loadOnDuty = async () => {
    const r = await api<{ data: { id: number; name: string; groups: Group[] }[] }>('GET', route('app.appointments.on-duty', { date: date.value }));
    onDuty.value = r.ok ? r.data.data : [];
};
watch(() => JSON.stringify(allLines.value.map((l) => [l.pet.id, l.service.id])) + date.value, loadSlots);
watch(date, loadOnDuty);
const slotOptions = computed<SlotOption[]>(() =>
    slots.value.map((s) => ({
        time: s.time,
        over: s.over,
        caption: Object.entries(s.groups)
            .map(([g, v]) => `${groupLabel[g as Group][0]} ${v.taken}/${v.on_duty}`)
            .join(' · '),
        title: Object.entries(s.groups)
            .map(([g, v]) => `${groupLabel[g as Group]} ${v.taken}/${v.on_duty}`)
            .join(' · '),
    })),
);
const chosen = computed(() => slots.value.find((s) => s.time === time.value) ?? null);

// Summary
const span = computed(() => Math.max(0, ...allLines.value.map((l) => (l.quote ? l.quote.duration_min + l.quote.buffer_min : 0))));
const total = computed(() => allLines.value.reduce((sum, l) => sum + Number(l.quote?.price ?? l.service.price), 0));
const weekday = computed(() => ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][new Date(`${date.value}T00:00:00`).getDay()]);
const summary = computed(() => {
    if (!allLines.value.length || !time.value) return null;
    const [, m, d] = date.value.split('-');
    return `${selectedPets.value.map((p) => p.name).join(', ')} · ${allLines.value.length} dịch vụ · ${time.value}–${hhmm(minutes(time.value) + span.value)} ${weekday.value} ${d}/${m} · ${formatMoney(total.value)} (tham khảo)`;
});
const canSubmit = computed(() => !!customer.value && allLines.value.length > 0 && !!time.value && !submitting.value && !props.quota.full);

const submit = async () => {
    if (!canSubmit.value || !customer.value) return;
    submitting.value = true;
    error.value = null;
    suggestions.value = [];
    const r = await api<any>('POST', route('app.appointments.store'), {
        customer_id: customer.value.id,
        date: date.value,
        time: time.value,
        items: allLines.value.map((l) => ({ pet_id: l.pet.id, service_id: l.service.id, assigned_staff_id: l.line.staffId })),
        note: note.value || null,
        send_reminders: !noReminders.value,
        idempotency_key: key.value, // same key on retry: never two bookings
    });
    submitting.value = false;

    if (r.ok) {
        show(r.data.message, 'success');
        (r.data.warnings ?? []).forEach((w: string) => show(w, 'warning', { seconds: 8 }));
        open.value = false;
        emit('booked', r.data.appointment);
        return;
    }
    if (r.status === 409) {
        error.value = r.data?.message ?? 'Khung giờ này vừa hết chỗ.';
        suggestions.value = r.data?.suggestions ?? [];
        show(error.value!, 'danger');
        await loadSlots(); // form stays as it is
        return;
    }
    error.value = errorMessage(r);
};

const select = 'h-9 w-full rounded-md border border-input bg-background px-2 text-sm';
</script>

<template>
    <AppDrawer v-model:open="open" title="Đặt lịch" :description="`${currentBranch?.name ?? ''} · lễ tân đặt không tính vào chỗ đặt online`">
        <Banner v-if="quota.full" tone="danger" class="m-4">Đã đạt {{ quota.used }}/{{ quota.max }} lịch tháng này. Liên hệ để nâng gói.</Banner>

        <form class="space-y-6 px-5 py-4" @submit.prevent="submit">
            <!-- 1. Customer -->
            <section class="space-y-2">
                <h3 class="text-sm font-semibold">1. Khách</h3>
                <div v-if="customer" class="flex items-center gap-3 rounded-xl border bg-card px-3 py-2.5">
                    <RingAvatar :initials="initialsOf(customer.name)" :size="36" />
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-semibold">{{ customer.name }}</div>
                        <div class="text-xs text-muted-foreground">
                            <span class="font-mono">{{ customer.phone }}</span> · {{ customer.pets.length }} thú cưng
                        </div>
                    </div>
                    <button type="button" class="text-sm font-semibold text-primary hover:underline" @click="customer = null">Đổi</button>
                </div>
                <template v-else>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            ref="searchInput"
                            v-model="q"
                            class="pl-9"
                            placeholder="SĐT, 4 số cuối hoặc tên"
                            aria-label="Tìm khách"
                            role="combobox"
                            :aria-expanded="results.length > 0"
                        />
                    </div>
                    <ul v-if="results.length" class="max-h-56 overflow-y-auto rounded-md border" role="listbox">
                        <li v-for="c in results" :key="c.id" role="option">
                            <button type="button" class="w-full px-3 py-2 text-left text-sm hover:bg-surface-muted" @click="pick(c)">
                                <span class="font-medium">{{ c.name }}</span> <span class="tabular-nums text-muted-foreground">· {{ c.phone }}</span>
                                <span class="block text-xs text-muted-foreground">{{ c.pets.map((p) => p.name).join(', ') || 'Chưa có bé' }}</span>
                            </button>
                        </li>
                    </ul>
                    <p v-else-if="q.length >= 2 && !searching" class="text-xs text-muted-foreground">Không thấy khách.</p>
                    <button
                        v-if="can.createCustomer && !quick.open"
                        type="button"
                        class="text-sm text-primary hover:underline"
                        @click="quick.open = true"
                    >
                        + Tạo khách mới
                    </button>
                    <div v-if="quick.open" class="grid gap-2 rounded-md border p-3">
                        <Input v-model="quick.name" placeholder="Họ tên" aria-label="Họ tên khách" />
                        <Input v-model="quick.phone" type="tel" placeholder="0901 234 567" aria-label="SĐT khách" />
                        <InputError :message="quick.error ?? undefined" />
                        <Button type="button" size="sm" :disabled="quick.saving" @click="createCustomer">Tạo khách</Button>
                    </div>
                </template>
            </section>

            <!-- 2. Pets & services -->
            <section v-if="customer" class="space-y-3">
                <h3 class="text-sm font-semibold">2. Thú cưng & dịch vụ</h3>
                <div class="flex flex-wrap gap-2">
                    <Chip
                        v-for="p in customer.pets"
                        :key="p.id"
                        :selected="!!lines[p.id]"
                        class="inline-flex items-center gap-2"
                        @click="togglePet(p)"
                    >
                        <RingAvatar :initials="p.name.slice(0, 2)" :size="24" />{{ p.name }}<Check v-if="lines[p.id]" class="size-4" />
                    </Chip>
                    <Chip v-if="can.createPet" dashed @click="quickPet.open = !quickPet.open"><Plus class="inline size-3.5" /> Thêm pet</Chip>
                </div>
                <div v-if="quickPet.open" class="grid grid-cols-3 gap-2 rounded-md border p-3">
                    <Input v-model="quickPet.name" class="col-span-3" placeholder="Tên bé" aria-label="Tên bé" />
                    <select v-model="quickPet.species_id" :class="select" aria-label="Loài">
                        <option v-for="s in species" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <Input v-model="quickPet.weight_kg" type="number" step="0.1" min="0.1" placeholder="Cân (kg)" aria-label="Cân nặng" />
                    <Button type="button" size="sm" :disabled="quickPet.saving" @click="createPet">Thêm</Button>
                    <InputError class="col-span-3" :message="quickPet.error ?? undefined" />
                </div>

                <div v-for="p in selectedPets" :key="p.id" class="space-y-2 rounded-xl border bg-card p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ p.name }}</span>
                        <PetAlertBadges :alerts="p.alerts" />
                    </div>
                    <div v-for="(l, i) in lines[p.id]" :key="l.serviceId" class="grid gap-1 rounded bg-surface-muted p-2 text-sm">
                        <div class="flex items-start justify-between gap-2">
                            <span>
                                {{ serviceOf(l.serviceId)?.name }}
                                <GroupBadge v-if="serviceOf(l.serviceId)" :group="serviceOf(l.serviceId)!.group" />
                            </span>
                            <button type="button" aria-label="Bỏ dịch vụ" @click="lines[p.id].splice(i, 1)"><X class="size-4" /></button>
                        </div>
                        <span v-if="quoteOf(p.id, l.serviceId)" class="flex items-baseline justify-between gap-2 text-xs text-muted-foreground">
                            <span>{{ quoteOf(p.id, l.serviceId)!.explanation }}</span>
                            <span class="font-mono tabular-nums text-foreground">{{ formatMoney(quoteOf(p.id, l.serviceId)!.price) }}</span>
                        </span>
                        <select v-model="l.staffId" :class="select" :aria-label="`Người làm ${serviceOf(l.serviceId)?.name}`">
                            <option :value="null">Chưa phân, phân khi khách đến</option>
                            <option v-for="s in onDuty.filter((s) => s.groups.includes(serviceOf(l.serviceId)!.group))" :key="s.id" :value="s.id">
                                {{ s.name }}
                            </option>
                        </select>
                    </div>
                    <select :class="select" aria-label="Thêm dịch vụ" @change="addLine(p, $event)">
                        <option value="">+ Thêm dịch vụ cho {{ p.name }}…</option>
                        <option v-for="s in services" :key="s.id" :value="s.id">
                            {{ groupLabel[s.group] }} · {{ s.name }}{{ s.is_combo ? ' (combo)' : '' }}
                        </option>
                    </select>
                </div>
            </section>

            <!-- 3. Date & time -->
            <section v-if="allLines.length" class="space-y-2">
                <h3 class="text-sm font-semibold">3. Ngày & giờ</h3>
                <Input v-model="date" type="date" aria-label="Ngày hẹn" />
                <p v-if="loadingSlots" class="text-xs text-muted-foreground">
                    <LoaderCircle class="inline size-3 animate-spin" /> Đang tải khung giờ…
                </p>
                <SlotPicker v-else v-model="time" class="max-h-60 overflow-y-auto" label="Khung giờ" :slots="slotOptions">
                    <template #empty>Ngày này cơ sở không mở cửa.</template>
                </SlotPicker>
                <p v-if="chosen?.over" class="flex items-start gap-1.5 rounded-md bg-warning/10 p-2 text-xs text-warning" role="alert">
                    <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                    <span>
                        {{ time }} đã đủ người trực:
                        {{
                            Object.entries(chosen.groups)
                                .map(([g, v]) => `${v.taken} lịch ${groupLabel[g as Group].toLowerCase()} / ${v.on_duty} người trực`)
                                .join(', ')
                        }}. Vẫn lưu được.
                    </span>
                </p>
            </section>

            <!-- 4. Note -->
            <section v-if="allLines.length" class="space-y-2">
                <Label for="booking_note">Ghi chú</Label>
                <textarea
                    id="booking_note"
                    v-model="note"
                    rows="2"
                    maxlength="1000"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <label class="flex items-center gap-2 text-sm"
                    ><input v-model="noReminders" type="checkbox" class="accent-primary" /> Không gửi nhắc</label
                >
                <p class="text-xs text-muted-foreground">Mặc định nhắc khách qua Zalo, SMS dự phòng.</p>
            </section>

            <Banner v-if="error" tone="danger" class="space-y-2">
                <p>{{ error }}</p>
                <div v-if="suggestions.length" class="flex flex-wrap gap-2">
                    <span class="text-foreground">Giờ gần nhất còn chỗ:</span>
                    <button
                        v-for="s in suggestions"
                        :key="s.date + s.time"
                        type="button"
                        class="rounded border bg-card px-2 py-0.5 text-foreground"
                        @click="
                            date = s.date;
                            time = s.time;
                        "
                    >
                        {{ s.time }}<template v-if="s.date !== date"> ({{ s.date.slice(8) }}/{{ s.date.slice(5, 7) }})</template>
                    </button>
                </div>
            </Banner>
        </form>

        <template #footer>
            <div class="space-y-2">
                <p v-if="summary" class="text-sm font-medium">{{ summary }}</p>
                <p class="text-xs text-muted-foreground">Giá tham khảo, có thể thay đổi theo thực tế.</p>
                <Button class="w-full" :disabled="!canSubmit" @click="submit">
                    <LoaderCircle v-if="submitting" class="size-4 animate-spin" /> Xác nhận đặt lịch
                </Button>
            </div>
        </template>
    </AppDrawer>
</template>
