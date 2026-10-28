<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, LoaderCircle, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Slot {
    id: number;
    weekday: number;
    start_time: string;
    end_time: string;
    service_group: 'medical' | 'grooming' | null;
    branch_id: number;
    branch: string;
    effective_from: string | null;
    effective_to: string | null;
}
interface Person {
    id: number;
    name: string;
    title: string | null;
    color: string | null;
    slots: Slot[];
    time_off: { id: number; starts_at: string; ends_at: string; reason: string | null; affected: number }[];
}
interface Day {
    date: string;
    weekday: number;
    label: string;
    closed: { id: number; reason: string | null; whole_shop: boolean } | null;
    on_duty: { medical: number; grooming: number };
    max_online: { medical: number; grooming: number };
}

const props = defineProps<{
    branch: { id: number; public_id: string; name: string };
    week: { from: string; to: string; prev: string; next: string };
    days: Day[];
    people: Person[];
    rates: { group: string; label: string; default: string | null; overrides: number }[];
    groups: { value: string; label: string }[];
    can: { manage: boolean; closeShop: boolean; seeAll: boolean };
}>();

const tone = (group: string | null) =>
    group === 'medical'
        ? 'bg-info/10 text-info border-info/30'
        : group === 'grooming'
          ? 'bg-coral/10 text-coral border-coral/30'
          : 'bg-surface-muted text-muted-foreground';

const slotsOn = (p: Person, day: Day) =>
    p.slots.filter(
        (s) => s.weekday === day.weekday && (!s.effective_from || s.effective_from <= day.date) && (!s.effective_to || s.effective_to >= day.date),
    );
const offOn = (p: Person, day: Day) => p.time_off.filter((t) => t.starts_at.slice(0, 10) <= day.date && t.ends_at.slice(0, 10) >= day.date);

// Add / edit a slot.
const editing = ref<Slot | null>(null);
const slotOpen = ref(false);
const slotForm = useForm({
    staff_id: null as number | null,
    branch_id: props.branch.id,
    weekday: 1,
    start_time: '08:00',
    end_time: '12:00',
    service_group: null as string | null,
    effective_from: '',
    effective_to: '',
});
const slotErrors = computed(() => slotForm.errors as Record<string, string | undefined>);
const openSlot = (person: Person, day: Day, slot?: Slot) => {
    editing.value = slot ?? null;
    slotForm.clearErrors();
    slotForm.staff_id = person.id;
    slotForm.weekday = slot?.weekday ?? day.weekday;
    slotForm.start_time = slot?.start_time ?? '08:00';
    slotForm.end_time = slot?.end_time ?? '12:00';
    slotForm.service_group = slot ? slot.service_group : 'medical';
    slotForm.effective_from = slot?.effective_from ?? '';
    slotForm.effective_to = slot?.effective_to ?? '';
    slotOpen.value = true;
};
const saveSlot = () => {
    const options = { preserveScroll: true, onSuccess: () => (slotOpen.value = false) };
    if (editing.value) slotForm.put(route('app.rosters.slots.update', editing.value.id), options);
    else slotForm.post(route('app.rosters.slots.store'), options);
};
const deleteSlot = () => {
    if (!editing.value || !confirm('Xóa khung trực này?')) return;
    router.delete(route('app.rosters.slots.destroy', editing.value.id), { preserveScroll: true, onSuccess: () => (slotOpen.value = false) });
};
const personName = computed(() => props.people.find((p) => p.id === slotForm.staff_id)?.name ?? '');

// Time off, closed days.
const offForm = useForm({
    staff_id: props.people[0]?.id ?? null,
    starts_on: props.week.from,
    starts_time: '',
    ends_on: props.week.from,
    ends_time: '',
    reason: '',
});
const addOff = () =>
    offForm.post(route('app.rosters.time-off.store'), { preserveScroll: true, onSuccess: () => offForm.reset('reason', 'starts_time', 'ends_time') });
const deleteOff = (id: number) => confirm('Xóa ngày nghỉ này?') && router.delete(route('app.rosters.time-off.destroy', id), { preserveScroll: true });

const closeForm = useForm({ date: props.week.from, reason: '', whole_shop: false });
const addClosure = () => closeForm.post(route('app.rosters.closures.store'), { preserveScroll: true, onSuccess: () => closeForm.reset('reason') });
const deleteClosure = (id: number) =>
    confirm('Bỏ ngày nghỉ này?') && router.delete(route('app.rosters.closures.destroy', id), { preserveScroll: true });

const select = 'h-9 rounded-md border border-input bg-background px-3 text-sm';
const percent = (ratio: string | null) => (ratio === null ? 'không nhận online' : `${Math.round(Number(ratio) * 100)}% số người trực`);
</script>

<template>
    <Head title="Lịch trực" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4 md:p-6">
            <PageHeader :title="`Lịch trực · ${branch.name}`">
                <template #subtitle>
                    Mỗi khung chọn trực nhóm nào. Người có cả role bác sĩ và spa vẫn là một người trực trong khung đó.
                    <template v-if="!can.seeAll"> Bạn đang xem lịch của chính mình.</template>
                </template>
                <template #actions>
                    <Button variant="outline" size="icon" as-child>
                        <Link :href="route('app.rosters.index', { week: week.prev })" aria-label="Tuần trước"><ChevronLeft class="size-4" /></Link>
                    </Button>
                    <span class="text-sm tabular-nums">{{ days[0].label }} – {{ days[6].label }}</span>
                    <Button variant="outline" size="icon" as-child>
                        <Link :href="route('app.rosters.index', { week: week.next })" aria-label="Tuần sau"><ChevronRight class="size-4" /></Link>
                    </Button>
                </template>
            </PageHeader>

            <form v-if="slotOpen" class="grid gap-4 rounded-[10px] border bg-card p-4 md:grid-cols-4 md:items-end" @submit.prevent="saveSlot">
                <div class="flex items-center justify-between md:col-span-4">
                    <h2 class="font-semibold">{{ editing ? 'Sửa khung trực' : 'Thêm khung trực' }} · {{ personName }}</h2>
                    <button type="button" aria-label="Đóng" @click="slotOpen = false"><X class="size-4" /></button>
                </div>
                <div class="grid gap-2">
                    <Label for="slot_weekday">Thứ</Label>
                    <select id="slot_weekday" v-model.number="slotForm.weekday" :class="select">
                        <option v-for="d in days" :key="d.weekday" :value="d.weekday">{{ d.label.split(' ')[0] }}</option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="slot_start">Từ</Label>
                    <Input id="slot_start" v-model="slotForm.start_time" type="time" required />
                </div>
                <div class="grid gap-2">
                    <Label for="slot_end">Đến</Label>
                    <Input id="slot_end" v-model="slotForm.end_time" type="time" required />
                </div>
                <fieldset class="grid gap-2">
                    <legend class="text-sm font-medium">Trực nhóm</legend>
                    <div class="flex flex-wrap gap-3 text-sm">
                        <label v-for="g in groups" :key="g.value" class="flex items-center gap-1.5">
                            <input v-model="slotForm.service_group" type="radio" :value="g.value" class="accent-primary" /> {{ g.label }}
                        </label>
                        <label class="flex items-center gap-1.5"
                            ><input v-model="slotForm.service_group" type="radio" :value="null" class="accent-primary" /> Không tính</label
                        >
                    </div>
                </fieldset>
                <div class="grid gap-2">
                    <Label for="slot_from">Áp dụng từ ngày</Label>
                    <Input id="slot_from" v-model="slotForm.effective_from" type="date" />
                </div>
                <div class="grid gap-2">
                    <Label for="slot_to">Đến ngày (tùy chọn)</Label>
                    <Input id="slot_to" v-model="slotForm.effective_to" type="date" />
                </div>
                <div class="flex gap-2 md:col-span-2">
                    <Button type="submit" :disabled="slotForm.processing"
                        ><LoaderCircle v-if="slotForm.processing" class="size-4 animate-spin" /> Lưu</Button
                    >
                    <Button v-if="editing" type="button" variant="outline" class="text-danger" @click="deleteSlot"
                        ><Trash2 class="size-4" /> Xóa</Button
                    >
                </div>
                <div class="md:col-span-4" role="alert" aria-live="polite">
                    <InputError :message="slotErrors.start_time ?? slotErrors.end_time ?? slotErrors.branch_id ?? slotErrors.effective_to" />
                </div>
            </form>

            <div class="overflow-x-auto rounded-[10px] border bg-card">
                <table class="w-full min-w-[56rem] table-fixed text-sm">
                    <thead class="border-b bg-surface-muted text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="w-44 px-3 py-2 font-medium">Nhân viên</th>
                            <th v-for="d in days" :key="d.date" class="px-2 py-2 font-medium" :class="{ 'bg-danger/5': d.closed }">
                                {{ d.label }}
                                <div v-if="d.closed" class="font-normal text-danger">
                                    Nghỉ{{ d.closed.whole_shop ? ' cả shop' : '' }}<template v-if="d.closed.reason">: {{ d.closed.reason }}</template>
                                    <button
                                        v-if="can.manage && (!d.closed.whole_shop || can.closeShop)"
                                        type="button"
                                        class="ml-1 underline"
                                        @click="deleteClosure(d.closed.id)"
                                    >
                                        bỏ
                                    </button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in people" :key="p.id" class="border-b align-top last:border-0">
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <span class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: p.color ?? '#94a3b8' }" />
                                    <span class="truncate font-medium">{{ p.name }}</span>
                                </div>
                                <div v-if="p.title" class="truncate text-xs text-muted-foreground">{{ p.title }}</div>
                            </td>
                            <td v-for="d in days" :key="d.date" class="px-1.5 py-1.5" :class="{ 'bg-danger/5': d.closed }">
                                <div class="flex flex-col gap-1">
                                    <component
                                        :is="can.manage && s.branch_id === branch.id ? 'button' : 'div'"
                                        v-for="s in slotsOn(p, d)"
                                        :key="s.id"
                                        type="button"
                                        class="rounded border px-1.5 py-0.5 text-left text-xs tabular-nums"
                                        :class="[tone(s.service_group), { 'opacity-50': s.branch_id !== branch.id }]"
                                        :title="s.branch_id !== branch.id ? `Ở ${s.branch}` : undefined"
                                        @click="can.manage && s.branch_id === branch.id && openSlot(p, d, s)"
                                    >
                                        {{ s.start_time }}–{{ s.end_time }}<span v-if="s.branch_id !== branch.id"> · {{ s.branch }}</span>
                                    </component>
                                    <div
                                        v-for="t in offOn(p, d)"
                                        :key="t.id"
                                        class="rounded border border-dashed border-warning/50 bg-warning/10 px-1.5 py-0.5 text-xs text-warning"
                                    >
                                        Nghỉ<template v-if="t.affected"> · {{ t.affected }} lịch bị ảnh hưởng</template>
                                    </div>
                                    <button
                                        v-if="can.manage && !d.closed"
                                        type="button"
                                        class="grid h-6 place-items-center rounded text-muted-foreground opacity-0 hover:bg-surface-muted focus:opacity-100 [tr:hover_&]:opacity-100"
                                        :aria-label="`Thêm khung cho ${p.name}, ${d.label}`"
                                        @click="openSlot(p, d)"
                                    >
                                        <Plus class="size-3.5" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!people.length">
                            <td colspan="8" class="px-4 py-8 text-center text-muted-foreground">Chưa có nhân viên nào làm ở cơ sở này.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="can.seeAll" class="border-t bg-surface-muted text-xs">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Số người trực</th>
                            <td v-for="d in days" :key="d.date" class="px-2 py-2 tabular-nums">
                                <div>
                                    <span class="text-info">Khám {{ d.on_duty.medical }}</span> ·
                                    <span class="text-coral">Spa {{ d.on_duty.grooming }}</span>
                                </div>
                                <div class="text-muted-foreground">
                                    Online tối đa: Khám {{ d.max_online.medical }} · Spa {{ d.max_online.grooming }}
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div v-if="can.manage" class="grid gap-4 lg:grid-cols-3">
                <form class="grid content-start gap-3 rounded-[10px] border bg-card p-4" @submit.prevent="addOff">
                    <h2 class="font-semibold">Ngày nghỉ của nhân viên</h2>
                    <select v-model="offForm.staff_id" :class="select" aria-label="Nhân viên">
                        <option v-for="p in people" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <div class="grid grid-cols-2 gap-2">
                        <Input v-model="offForm.starts_on" type="date" aria-label="Từ ngày" required />
                        <Input v-model="offForm.starts_time" type="time" aria-label="Từ giờ (trống = cả ngày)" />
                        <Input v-model="offForm.ends_on" type="date" aria-label="Đến ngày" required />
                        <Input v-model="offForm.ends_time" type="time" aria-label="Đến giờ (trống = hết ngày)" />
                    </div>
                    <Input v-model="offForm.reason" placeholder="Lý do (tùy chọn)" maxlength="255" />
                    <p class="text-xs text-muted-foreground">Giờ theo giờ shop. Để trống giờ = cả ngày.</p>
                    <InputError :message="offForm.errors.ends_on ?? offForm.errors.starts_on" />
                    <Button type="submit" size="sm" :disabled="offForm.processing">Thêm ngày nghỉ</Button>
                    <ul class="grid gap-1 text-xs">
                        <template v-for="p in people" :key="p.id">
                            <li v-for="t in p.time_off" :key="t.id" class="flex items-center justify-between gap-2">
                                <span>
                                    {{ p.name }}: {{ t.starts_at.slice(5) }} → {{ t.ends_at.slice(5) }}
                                    <span v-if="t.affected" class="font-medium text-warning">· {{ t.affected }} lịch bị ảnh hưởng</span>
                                </span>
                                <button type="button" class="text-danger" aria-label="Xóa ngày nghỉ" @click="deleteOff(t.id)">
                                    <Trash2 class="size-3.5" />
                                </button>
                            </li>
                        </template>
                    </ul>
                </form>

                <form class="grid content-start gap-3 rounded-[10px] border bg-card p-4" @submit.prevent="addClosure">
                    <h2 class="font-semibold">Ngày cơ sở đóng cửa</h2>
                    <Input v-model="closeForm.date" type="date" aria-label="Ngày" required />
                    <Input v-model="closeForm.reason" placeholder="Lý do (vd Tết)" maxlength="255" />
                    <label v-if="can.closeShop" class="flex items-center gap-2 text-sm">
                        <input v-model="closeForm.whole_shop" type="checkbox" class="accent-primary" /> Cả shop (mọi cơ sở)
                    </label>
                    <InputError :message="closeForm.errors.date" />
                    <Button type="submit" size="sm" :disabled="closeForm.processing">Đánh dấu nghỉ</Button>
                </form>

                <section class="grid content-start gap-3 rounded-[10px] border bg-card p-4">
                    <h2 class="font-semibold">Tỉ lệ đặt online</h2>
                    <ul class="grid gap-1 text-sm">
                        <li v-for="r in rates" :key="r.group">
                            <span class="font-medium">{{ r.label }}:</span> {{ percent(r.default) }}
                            <span v-if="r.overrides" class="text-muted-foreground"> · {{ r.overrides }} ghi đè theo thứ/giờ</span>
                        </li>
                    </ul>
                    <p class="text-xs text-muted-foreground">Không có tỉ lệ = Site không nhận đặt online nhóm đó.</p>
                    <Button size="sm" variant="outline" as-child>
                        <Link :href="route('app.settings.online-rates', branch.public_id)">Sửa tỉ lệ</Link>
                    </Button>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
