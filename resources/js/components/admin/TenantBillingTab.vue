<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SubscriptionRow } from '@/types/admin';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';


const props = defineProps<{
    tenantId: string;
    timezone: string;
    planExpiresAt: string | null;
    currentPlan: string | null;
    subscriptions: SubscriptionRow[];
    plans: { id: number; code: string; name: string }[];
    canManage: boolean;
}>();

const form = useForm({
    plan_id: props.plans.find((p) => p.name === props.currentPlan)?.id ?? props.plans[0]?.id ?? null,
    months: 12,
    amount: '',
    payment_ref: '',
    note: '',
});

const date = (iso: string | null) => (iso ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeZone: props.timezone }).format(new Date(iso)) : '—');
const money = new Intl.NumberFormat('vi-VN');

/** "Tự nối hạn": a still-valid plan continues from its expiry, an expired one from today. */
const preview = computed(() => {
    const current = props.planExpiresAt ? new Date(props.planExpiresAt) : null;
    const start = current && current > new Date() ? current : new Date();
    const end = new Date(start);
    end.setMonth(end.getMonth() + Number(form.months || 0));
    return { start: date(start.toISOString()), end: date(end.toISOString()) };
});

const submit = () => form.post(route('admin.tenants.extend', props.tenantId), { preserveScroll: true, onSuccess: () => form.reset('amount', 'payment_ref', 'note') });
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <section class="overflow-x-auto rounded-xl border border-stone-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-[13px] font-medium text-stone-500 dark:bg-slate-800">
                    <tr>
                        <th class="px-4 py-3">Gói</th>
                        <th class="px-4 py-3">Từ</th>
                        <th class="px-4 py-3">Đến</th>
                        <th class="px-4 py-3 text-right">Số tiền</th>
                        <th class="px-4 py-3">Mã CK</th>
                        <th class="px-4 py-3">Người ghi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in subscriptions" :key="s.id" class="border-t border-stone-100 dark:border-slate-800">
                        <td class="px-4 py-3 font-medium">{{ s.plan ?? '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ date(s.starts_at) }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ date(s.ends_at) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ money.format(Number(s.amount)) }} ₫</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ s.payment_ref ?? (s.note || '—') }}</td>
                        <td class="px-4 py-3">{{ s.by ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <form v-if="canManage" class="grid gap-4 self-start rounded-xl border border-stone-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="submit">
            <h3 class="font-medium">Gia hạn</h3>
            <div class="grid gap-2">
                <Label for="plan_id">Gói</Label>
                <select id="plan_id" v-model="form.plan_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                    <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>
            <div class="grid gap-2">
                <Label for="months">Số tháng</Label>
                <Input id="months" v-model.number="form.months" type="number" min="1" max="36" class="w-28" />
                <p class="text-xs text-stone-500">Tự nối hạn: {{ preview.start }} → {{ preview.end }}</p>
                <InputError :message="form.errors.months" />
            </div>
            <div class="grid gap-2">
                <Label for="amount">Số tiền (VND)</Label>
                <Input id="amount" v-model="form.amount" type="number" min="0" step="1000" required />
                <InputError :message="form.errors.amount" />
            </div>
            <div class="grid gap-2">
                <Label for="payment_ref">Mã chuyển khoản</Label>
                <Input id="payment_ref" v-model="form.payment_ref" />
            </div>
            <div class="grid gap-2">
                <Label for="note">Ghi chú</Label>
                <textarea id="note" v-model="form.note" rows="2" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
            </div>
            <Button type="submit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                Gia hạn
            </Button>
        </form>
    </div>
</template>
