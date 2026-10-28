<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PetAlertBadges from '@/components/PetAlertBadges.vue';
import AppDrawer from '@/components/pb/AppDrawer.vue';
import LockedNote from '@/components/pb/LockedNote.vue';
import RingAvatar from '@/components/pb/RingAvatar.vue';
import StatusBadge from '@/components/pb/StatusBadge.vue';
import Tag from '@/components/pb/Tag.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useToast } from '@/composables/useToast';
import { api, errorMessage } from '@/lib/api';
import { formatMoney } from '@/lib/format';
import type { Group } from '@/types/calendar';
import type { PetAlert } from '@/types/pets';
import { Link } from '@inertiajs/vue3';
import { BellOff, Clock, LoaderCircle, Phone } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/* S3 Chi tiết lịch hẹn (task 5.7): lines per pet, actions by status, history, reminders. */

interface Line {
    id: number;
    pet: { id: string; name: string; alerts: PetAlert[] };
    service: string;
    group: Group;
    group_label: string;
    duration_min: number;
    reference_price: string;
    staff: string | null;
    staff_id: number | null;
    status: string;
    checked_in: boolean;
}
interface Detail {
    id: string;
    code: string;
    status: string;
    status_label: string;
    source: 'online' | 'staff';
    send_reminders: boolean;
    date: string;
    start: string;
    end: string;
    branch: string;
    note: string | null;
    cancel_reason: string | null;
    preferred: string | null;
    customer: { id: string; name: string; phone: string | null; tel: string | null };
    lines: Line[];
    history: { action: string; at: string; by: string; pet: string | null; reason: string | null; from: string | null; to: string | null }[];
    reminders: { purpose: string; status: string; send_at: string; deliveries: { channel: string; status: string; error: string | null }[] }[];
    medical: { pet: string; last_exam: string | null; diagnosis: string | null }[] | null;
    can: { checkIn: boolean; cancel: boolean; update: boolean };
    undo_seconds: number;
}

const appointmentId = defineModel<string | null>('appointment', { required: true });
const props = defineProps<{ today: string; onDuty: { id: number; name: string; groups: Group[] }[] }>();
const emit = defineEmits<{ changed: [] }>();
const { show } = useToast();

const open = computed({ get: () => appointmentId.value !== null, set: (v) => !v && (appointmentId.value = null) });
const detail = ref<Detail | null>(null);
const loading = ref(false);
const busy = ref(false);
const error = ref<string | null>(null);

const load = async () => {
    if (!appointmentId.value) return;
    loading.value = true;
    const r = await api<{ data: Detail }>('GET', route('app.appointments.show', appointmentId.value));
    detail.value = r.ok ? r.data.data : null;
    loading.value = false;
    resetForms();
};
watch(appointmentId, load);

const booked = computed(() => detail.value?.lines.filter((l) => l.status === 'booked') ?? []);
const isToday = computed(() => detail.value?.date === props.today);
const started = computed(
    () => !!detail.value && `${detail.value.date} ${detail.value.start}` <= `${props.today} ${new Date().toTimeString().slice(0, 5)}`,
);

// Check-in
const arrived = ref<number[]>([]);
const assign = ref<Record<number, number | null>>({});
const resetForms = () => {
    arrived.value = booked.value.map((l) => l.id);
    assign.value = Object.fromEntries(booked.value.filter((l) => !l.staff_id).map((l) => [l.id, null]));
    cancel.value = { open: false, scope: 'all', lines: [], reason: '', other: '', notify: true };
    reschedule.value = { open: false, date: detail.value?.date ?? props.today, time: detail.value?.start ?? '09:00' };
    error.value = null;
};

const run = async (url: string, body: unknown, after?: (data: any) => void) => {
    busy.value = true;
    error.value = null;
    const r = await api<any>('POST', url, body);
    busy.value = false;
    if (!r.ok) {
        error.value = errorMessage(r);
        if (r.status === 409) show(error.value, 'danger');
        return;
    }
    show(r.data.message, 'success');
    (r.data.warnings ?? []).forEach((w: string) => show(w, 'warning', { seconds: 8 }));
    after?.(r.data);
    emit('changed');
    await load();
};

const checkIn = () =>
    run(route('app.appointments.check-in', appointmentId.value!), {
        items: arrived.value,
        assign: Object.fromEntries(Object.entries(assign.value).filter(([, v]) => v !== null)),
    });

// Cancel + undo
const REASONS = ['Khách đổi ý', 'Khách bận', 'Bé ốm', 'Shop không sắp xếp được', 'Khác'];
const cancel = ref({ open: false, scope: 'all' as 'all' | 'lines', lines: [] as number[], reason: '', other: '', notify: true });
const confirmCancel = () => {
    const reason = cancel.value.reason === 'Khác' ? cancel.value.other : cancel.value.reason;
    const id = appointmentId.value!;
    cancel.value.open = false;
    run(
        route('app.appointments.cancel', id),
        { reason, notify: cancel.value.notify, items: cancel.value.scope === 'lines' ? cancel.value.lines : null },
        (data) =>
            show('Đã hủy.', 'info', {
                seconds: data.undo_seconds ?? 5,
                action: { label: 'Hoàn tác', run: () => run(route('app.appointments.restore', id), {}) },
            }),
    );
};

const noShow = () => run(route('app.appointments.no-show', appointmentId.value!), {});

const reschedule = ref({ open: false, date: props.today, time: '09:00' });
const doReschedule = () =>
    run(route('app.appointments.reschedule', appointmentId.value!), { date: reschedule.value.date, time: reschedule.value.time });

const purpose: Record<string, string> = { appointment_confirm: 'Xác nhận', appointment_24h: 'Nhắc 24h', appointment_2h: 'Nhắc 2h' };
const reminderStatus: Record<string, string> = { pending: 'Chờ gửi', processing: 'Đang gửi', sent: 'Đã gửi', failed: 'Lỗi', skipped: 'Bỏ qua' };
const lineStatus: Record<string, string> = { booked: 'Đã đặt', checked_in: 'Đã đến', cancelled: 'Đã hủy', no_show: 'Không đến' };
const showCheckIn = ref(false);
const total = computed(() =>
    (detail.value?.lines ?? [])
        .filter((l) => l.status !== 'cancelled' && l.status !== 'no_show')
        .reduce((sum, l) => sum + Number(l.reference_price), 0),
);
const weekdays = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
const longDate = (day: string) => `${weekdays[new Date(`${day}T00:00:00`).getDay()]}, ${day.split('-').reverse().join('/')}`;
const initialsOf = (name: string) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(-2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
const select = 'h-8 w-full rounded-md border border-input bg-background px-2 text-sm';
</script>

<template>
    <AppDrawer v-model:open="open" title="Chi tiết lịch hẹn" :description="detail?.branch">
        <div v-if="loading && !detail" class="p-6 text-sm text-muted-foreground"><LoaderCircle class="inline size-4 animate-spin" /> Đang tải…</div>
        <div v-else-if="detail" class="space-y-4 px-6 py-5">
            <div class="flex flex-wrap items-center gap-2">
                <Tag class="font-mono">{{ detail.code }}</Tag>
                <StatusBadge :status="detail.status" :label="detail.status_label" dot />
                <Badge :tone="detail.source === 'online' ? 'primary' : 'neutral'">{{ detail.source === 'online' ? 'Online' : 'Lễ tân đặt' }}</Badge>
                <Badge v-if="!detail.send_reminders"><BellOff class="size-3" /> Không nhắc</Badge>
            </div>

            <p class="flex items-center gap-2 text-[15px] font-semibold">
                <Clock class="size-4 text-muted-foreground" aria-hidden="true" />
                {{ detail.start }}–{{ detail.end }}, {{ longDate(detail.date) }}
            </p>

            <section class="flex items-center gap-3 rounded-xl border p-3">
                <RingAvatar :initials="initialsOf(detail.customer.name)" :size="36" />
                <div class="min-w-0 flex-1">
                    <Link :href="route('app.customers.show', detail.customer.id)" class="block truncate font-semibold hover:underline">{{
                        detail.customer.name
                    }}</Link>
                    <span v-if="detail.customer.tel" class="font-mono text-xs text-muted-foreground">{{ detail.customer.phone }}</span>
                </div>
                <template v-if="detail.customer.tel">
                    <a :href="`tel:${detail.customer.tel}`" class="flex items-center gap-1 text-sm font-semibold text-primary"
                        ><Phone class="size-3.5" /> Gọi</a
                    >
                    <a
                        :href="`https://zalo.me/${detail.customer.tel.replace('+84', '0')}`"
                        target="_blank"
                        rel="noopener"
                        class="text-sm font-semibold text-primary"
                        >Nhắn Zalo</a
                    >
                </template>
            </section>
            <p v-if="detail.preferred" class="text-sm text-muted-foreground">Mong muốn: {{ detail.preferred }}</p>
            <p v-if="detail.note" class="whitespace-pre-line rounded-[10px] bg-surface-muted p-3 text-sm">{{ detail.note }}</p>
            <p v-if="detail.cancel_reason" class="text-sm text-danger">Lý do hủy: {{ detail.cancel_reason }}</p>

            <section class="space-y-3">
                <article
                    v-for="l in detail.lines"
                    :key="l.id"
                    class="space-y-1.5 rounded-xl border p-3 text-sm"
                    :class="{ 'opacity-60': l.status === 'cancelled' || l.status === 'no_show' }"
                >
                    <div class="flex items-start gap-3">
                        <RingAvatar :initials="l.pet.name.slice(0, 2)" :size="36" />
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold">{{ l.pet.name }}</div>
                            <PetAlertBadges :alerts="l.pet.alerts" class="mt-1" />
                        </div>
                        <Badge>{{ lineStatus[l.status] }}</Badge>
                    </div>
                    <p>
                        <span class="font-semibold">{{ l.service }}</span>
                        <span class="text-muted-foreground"> · {{ l.duration_min }} phút · </span>
                        <span class="font-mono text-[13px]">{{ formatMoney(l.reference_price) }}</span>
                    </p>
                    <p class="text-[13px] text-muted-foreground">
                        Người làm:
                        <span class="font-medium" :class="l.staff ? 'text-foreground' : 'text-amber-800 dark:text-amber-300'">{{
                            l.staff ?? 'Chưa phân'
                        }}</span>
                    </p>
                </article>
                <div class="flex items-baseline justify-between text-sm">
                    <span class="text-muted-foreground">Tạm tính (tham khảo)</span>
                    <span class="font-mono font-semibold tabular-nums">{{ formatMoney(total) }}</span>
                </div>
            </section>

            <!-- Check-in: pick who arrived and who works -->
            <section v-if="showCheckIn && detail.can.checkIn && isToday" class="space-y-3 rounded-xl border border-primary/40 bg-primary-soft/40 p-3">
                <h3 class="text-sm font-semibold">Check-in: ai đã đến?</h3>
                <label v-for="l in booked" :key="l.id" class="flex items-center gap-2 text-sm">
                    <input v-model="arrived" type="checkbox" class="accent-primary" :value="l.id" /> {{ l.pet.name }} · {{ l.service }}
                    <select
                        v-if="!l.staff_id"
                        v-model="assign[l.id]"
                        :class="select"
                        class="ml-auto w-40"
                        :aria-label="`Người làm cho ${l.pet.name}`"
                    >
                        <option :value="null">Chưa phân</option>
                        <option v-for="s in onDuty.filter((s) => s.groups.includes(l.group))" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </label>
                <Button size="sm" :loading="busy" :disabled="!arrived.length" @click="checkIn">Xác nhận check-in {{ arrived.length }} dòng</Button>
            </section>

            <div v-if="reschedule.open" class="grid grid-cols-[1fr_7rem_auto] gap-2 rounded-xl border p-3">
                <Input v-model="reschedule.date" type="date" aria-label="Ngày mới" />
                <Input v-model="reschedule.time" type="time" step="900" aria-label="Giờ mới" />
                <Button size="sm" :loading="busy" @click="doReschedule">Dời</Button>
            </div>
            <InputError :message="error ?? undefined" />

            <!-- Medical, by permission -->
            <section v-if="detail.medical" class="space-y-1.5 rounded-xl bg-surface-muted p-3 text-sm">
                <h3 class="text-[13px] font-semibold text-muted-foreground">Thông tin y tế</h3>
                <p v-for="m in detail.medical" :key="m.pet">
                    <span class="font-medium">{{ m.pet }}</span
                    >: lần khám gần nhất {{ m.last_exam ?? 'chưa có' }}<template v-if="m.diagnosis"> · {{ m.diagnosis }}</template>
                </p>
                <LockedNote v-if="detail.medical.every((m) => m.diagnosis === null)" class="border-0 bg-transparent p-0 text-xs"
                    >Nội dung bệnh án chỉ bác sĩ xem.</LockedNote
                >
            </section>

            <section class="space-y-1.5 text-sm">
                <h3 class="font-semibold">Lịch sử</h3>
                <ol class="space-y-1 text-xs">
                    <li v-for="(h, i) in detail.history" :key="i">
                        <span class="tabular-nums text-muted-foreground">{{ h.at }}</span> · {{ h.action
                        }}<template v-if="h.pet"> ({{ h.pet }})</template> <template v-if="h.from"> · {{ h.from }} → {{ h.to }}</template> · {{ h.by
                        }}<template v-if="h.reason"> · {{ h.reason }}</template>
                    </li>
                </ol>
            </section>

            <section class="space-y-1.5 text-sm">
                <h3 class="font-semibold">Tin nhắn</h3>
                <p v-if="!detail.reminders.length" class="text-xs text-muted-foreground">Chưa có tin nhắc.</p>
                <p v-for="(r, i) in detail.reminders" :key="i" class="text-xs">
                    {{ purpose[r.purpose] ?? r.purpose }} · {{ r.send_at }} · {{ reminderStatus[r.status] ?? r.status }}
                    <template v-for="(d, j) in r.deliveries" :key="j">
                        · {{ d.channel === 'zalo' ? 'Zalo' : 'SMS' }}
                        {{ d.status === 'failed' ? 'lỗi' : d.status === 'sent' ? 'đã gửi' : 'đang gửi' }}</template
                    >
                </p>
            </section>
        </div>

        <template v-if="detail" #footer>
            <div class="flex flex-wrap items-center gap-2">
                <Button v-if="booked.length && detail.can.checkIn && isToday" @click="showCheckIn = !showCheckIn">Check-in</Button>
                <Button v-if="detail.status === 'checked_in'" variant="outline" disabled disabled-reason="Màn Visit có ở task 5.9">Mở Visit</Button>
                <Button
                    v-if="booked.length && detail.can.update && detail.status === 'confirmed'"
                    variant="outline"
                    @click="reschedule.open = !reschedule.open"
                    >Dời</Button
                >
                <Button v-if="booked.length && detail.can.update && started" variant="outline" :loading="busy" @click="noShow">Không đến</Button>
                <Button v-if="booked.length && detail.can.cancel" variant="ghostDanger" @click="cancel.open = true">Hủy</Button>
            </div>
        </template>
    </AppDrawer>

    <Dialog v-model:open="cancel.open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Hủy lịch</DialogTitle>
                <DialogDescription>Dòng bị hủy nhả chỗ ngay. Có thể hoàn tác trong vài giây.</DialogDescription>
            </DialogHeader>
            <div class="space-y-3 text-sm">
                <div class="flex gap-4">
                    <label class="flex items-center gap-2"
                        ><input v-model="cancel.scope" type="radio" value="all" class="accent-primary" /> Cả lịch</label
                    >
                    <label class="flex items-center gap-2"
                        ><input v-model="cancel.scope" type="radio" value="lines" class="accent-primary" /> Từng pet</label
                    >
                </div>
                <div v-if="cancel.scope === 'lines'" class="space-y-1">
                    <label v-for="l in booked" :key="l.id" class="flex items-center gap-2"
                        ><input v-model="cancel.lines" type="checkbox" class="accent-primary" :value="l.id" /> {{ l.pet.name }} ·
                        {{ l.service }}</label
                    >
                </div>
                <select v-model="cancel.reason" class="h-9 w-full rounded-md border border-input bg-background px-2" aria-label="Lý do hủy">
                    <option value="" disabled>Chọn lý do…</option>
                    <option v-for="r in REASONS" :key="r" :value="r">{{ r }}</option>
                </select>
                <Input v-if="cancel.reason === 'Khác'" v-model="cancel.other" placeholder="Lý do" aria-label="Lý do khác" />
                <label class="flex items-center gap-2"><input v-model="cancel.notify" type="checkbox" class="accent-primary" /> Báo khách</label>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="cancel.open = false">Thôi</Button>
                <Button
                    variant="destructive"
                    :disabled="!cancel.reason || (cancel.reason === 'Khác' && !cancel.other) || (cancel.scope === 'lines' && !cancel.lines.length)"
                    @click="confirmCancel"
                >
                    Hủy lịch
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
