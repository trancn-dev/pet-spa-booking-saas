<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface PlanOption {
    id: number;
    code: string;
    name: string;
    max_branches: number | null;
    max_staff: number | null;
    max_appointments_per_month: number | null;
}

const props = defineProps<{
    plans: PlanOption[];
    timezones: string[];
    defaults: { timezone: string; trial_days: number };
    siteHost: string;
}>();

const today = new Date().toISOString().slice(0, 10);
const inAYear = (() => {
    const d = new Date();
    d.setFullYear(d.getFullYear() + 1);
    d.setDate(d.getDate() - 1);
    return d.toISOString().slice(0, 10);
})();

const form = useForm({
    name: '',
    slug: '',
    kind: 'both' as 'spa' | 'vet' | 'both',
    timezone: props.defaults.timezone,
    branch_code: '',
    branch_name: '',
    branch_address: '',
    owner_name: '',
    owner_email: '',
    owner_phone: '',
    plan_id: props.plans.find((p) => p.code === 'pro')?.id ?? props.plans[0]?.id ?? null,
    billing: 'trial' as 'trial' | 'paid',
    trial_days: props.defaults.trial_days,
    paid_from: today,
    paid_until: inAYear,
    amount: '',
    payment_ref: '',
    note: '',
});

const steps = [
    { n: 1, title: 'Shop & cơ sở đầu tiên' },
    { n: 2, title: 'Chủ shop' },
    { n: 3, title: 'Gói & thanh toán' },
];
const step = ref(1);
const stepFields: Record<number, (keyof typeof form)[]> = {
    1: ['name', 'slug', 'kind', 'timezone', 'branch_code', 'branch_name', 'branch_address'],
    2: ['owner_name', 'owner_email', 'owner_phone'],
    3: ['plan_id', 'billing', 'trial_days', 'paid_from', 'paid_until', 'amount', 'payment_ref', 'note'],
};

/** Vietnamese without accents, lower case, words joined by "-". */
const slugify = (text: string) =>
    text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/đ/gi, 'd')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 63);

const slugTouched = ref(false);
watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) form.slug = slugify(name);
    },
);

// Realtime slug check (debounced).
const slugState = ref<{ checking: boolean; available: boolean | null; message: string | null }>({ checking: false, available: null, message: null });
let timer: ReturnType<typeof setTimeout> | undefined;
watch(
    () => form.slug,
    (slug) => {
        clearTimeout(timer);
        slugState.value = { checking: !!slug, available: null, message: null };
        if (!slug) return;
        timer = setTimeout(async () => {
            const response = await fetch(route('admin.tenants.slug-check', { slug }), { headers: { Accept: 'application/json' } });
            const data = (await response.json()) as { available: boolean; message: string | null };
            if (form.slug === slug) slugState.value = { checking: false, ...data };
        }, 300);
    },
);

const plan = computed(() => props.plans.find((p) => p.id === form.plan_id));
const limit = (value: number | null) => (value === null ? 'không giới hạn' : value);

const canContinue = computed(() => {
    if (step.value === 1) return !!form.name && !!form.slug && slugState.value.available !== false && !!form.branch_code && !!form.branch_name;
    if (step.value === 2) return !!form.owner_name && /.+@.+\..+/.test(form.owner_email);
    return form.billing === 'trial' ? form.trial_days > 0 : !!form.paid_from && !!form.paid_until && form.amount !== '';
});

const stepOfError = computed(() => {
    const keys = Object.keys(form.errors);
    return steps.find((s) => stepFields[s.n].some((f) => keys.includes(f as string)))?.n;
});
watch(stepOfError, (n) => {
    if (n) step.value = n;
});

const submit = () => form.post(route('admin.tenants.store'));
</script>

<template>
    <AdminLayout title="Tạo shop">
        <Head title="Tạo shop" />

        <ol class="mb-6 flex flex-wrap gap-2" aria-label="Các bước">
            <li
                v-for="s in steps"
                :key="s.n"
                class="flex items-center gap-2 rounded-full px-3 py-1 text-sm"
                :class="s.n === step ? 'bg-teal-600 text-white' : s.n < step ? 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-200' : 'bg-stone-100 text-stone-500 dark:bg-slate-800'"
            >
                <Check v-if="s.n < step" class="size-4" />
                <span v-else class="tabular-nums">{{ s.n }}</span>
                {{ s.title }}
            </li>
        </ol>

        <form class="max-w-2xl rounded-xl border border-stone-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="submit">
            <!-- Step 1 -->
            <div v-show="step === 1" class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="name">Tên shop</Label>
                    <Input id="name" v-model="form.name" required autofocus placeholder="Paw House Spa & Vet" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="slug">Địa chỉ (slug)</Label>
                    <Input id="slug" v-model="form.slug" class="font-mono" required @input="slugTouched = true" />
                    <p class="text-sm text-stone-500">
                        Site khách: <span class="font-mono">{{ siteHost }}/{{ form.slug || 'ten-shop' }}</span>
                    </p>
                    <p v-if="slugState.checking" class="text-sm text-stone-500">Đang kiểm tra…</p>
                    <p v-else-if="slugState.available" class="text-sm text-green-600">Địa chỉ dùng được.</p>
                    <InputError :message="form.errors.slug ?? slugState.message ?? undefined" />
                </div>
                <fieldset class="grid gap-2">
                    <legend class="text-sm font-medium">Loại hình</legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input v-model="form.kind" type="radio" value="spa" /> Spa</label>
                        <label class="flex items-center gap-2"><input v-model="form.kind" type="radio" value="vet" /> Thú y</label>
                        <label class="flex items-center gap-2"><input v-model="form.kind" type="radio" value="both" /> Cả hai</label>
                    </div>
                    <p class="text-xs text-stone-500">Chỉ để gợi ý dịch vụ mẫu.</p>
                </fieldset>
                <div class="grid gap-2">
                    <Label for="timezone">Múi giờ</Label>
                    <select id="timezone" v-model="form.timezone" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                    </select>
                    <InputError :message="form.errors.timezone" />
                </div>
                <div class="grid gap-4 sm:grid-cols-[120px_1fr]">
                    <div class="grid gap-2">
                        <Label for="branch_code">Mã cơ sở</Label>
                        <Input id="branch_code" v-model="form.branch_code" class="font-mono uppercase" required maxlength="20" placeholder="Q3" />
                        <InputError :message="form.errors.branch_code" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="branch_name">Tên cơ sở đầu tiên</Label>
                        <Input id="branch_name" v-model="form.branch_name" required placeholder="CN Quận 3" />
                        <InputError :message="form.errors.branch_name" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="branch_address">Địa chỉ cơ sở</Label>
                    <Input id="branch_address" v-model="form.branch_address" />
                </div>
            </div>

            <!-- Step 2 -->
            <div v-show="step === 2" class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="owner_name">Họ tên chủ shop</Label>
                    <Input id="owner_name" v-model="form.owner_name" required />
                    <InputError :message="form.errors.owner_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="owner_email">Email</Label>
                    <Input id="owner_email" v-model="form.owner_email" type="email" required />
                    <p class="text-sm text-stone-500">Chủ shop sẽ nhận email mời.</p>
                    <InputError :message="form.errors.owner_email" />
                </div>
                <div class="grid gap-2">
                    <Label for="owner_phone">Số điện thoại</Label>
                    <Input id="owner_phone" v-model="form.owner_phone" type="tel" placeholder="+84903000111" />
                    <InputError :message="form.errors.owner_phone" />
                </div>
            </div>

            <!-- Step 3 -->
            <div v-show="step === 3" class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="plan_id">Gói</Label>
                    <select id="plan_id" v-model="form.plan_id" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <p v-if="plan" class="text-sm text-stone-500">
                        {{ limit(plan.max_branches) }} cơ sở · {{ limit(plan.max_staff) }} nhân viên · {{ limit(plan.max_appointments_per_month) }} lịch/tháng
                    </p>
                    <InputError :message="form.errors.plan_id" />
                </div>
                <fieldset class="grid gap-3">
                    <legend class="text-sm font-medium">Thanh toán</legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input v-model="form.billing" type="radio" value="trial" /> Dùng thử</label>
                        <label class="flex items-center gap-2"><input v-model="form.billing" type="radio" value="paid" /> Đã bán</label>
                    </div>
                </fieldset>
                <div v-if="form.billing === 'trial'" class="grid gap-2">
                    <Label for="trial_days">Số ngày dùng thử</Label>
                    <Input id="trial_days" v-model.number="form.trial_days" type="number" min="1" max="90" class="w-32" />
                    <InputError :message="form.errors.trial_days" />
                </div>
                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="paid_from">Từ ngày</Label>
                        <Input id="paid_from" v-model="form.paid_from" type="date" />
                        <InputError :message="form.errors.paid_from" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="paid_until">Đến ngày</Label>
                        <Input id="paid_until" v-model="form.paid_until" type="date" />
                        <InputError :message="form.errors.paid_until" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="amount">Số tiền (VND)</Label>
                        <Input id="amount" v-model="form.amount" type="number" min="0" step="1000" />
                        <InputError :message="form.errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="payment_ref">Mã chuyển khoản</Label>
                        <Input id="payment_ref" v-model="form.payment_ref" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="note">Ghi chú</Label>
                    <textarea id="note" v-model="form.note" rows="3" class="rounded-md border border-input bg-background px-3 py-2 text-sm" />
                </div>
            </div>

            <div class="mt-8 flex items-center justify-between">
                <Button v-if="step > 1" type="button" variant="outline" @click="step--">Quay lại</Button>
                <Link v-else :href="route('admin.tenants.index')" class="text-sm text-stone-500 hover:underline">Hủy</Link>

                <Button v-if="step < 3" type="button" :disabled="!canContinue" @click="step++">Tiếp tục</Button>
                <Button v-else type="submit" :disabled="!canContinue || form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    Tạo shop
                </Button>
            </div>
        </form>
    </AdminLayout>
</template>
