<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BackLink from '@/components/pb/BackLink.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ShopSharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

type Hours = Record<string, [string, string][]>;

interface BranchData {
    id: string;
    code: string;
    name: string;
    address: string | null;
    phone: string | null;
    is_active: boolean;
    opening_hours: Hours;
}

const props = defineProps<{
    branch: BranchData | null;
    defaults: { opening_hours: Hours };
}>();

const editing = computed(() => props.branch !== null);
const canRates = computed(() => usePage<ShopSharedData>().props.permissions?.includes('rosters.manage') ?? false);
const weekdays = [
    { key: '1', label: 'Thứ 2' },
    { key: '2', label: 'Thứ 3' },
    { key: '3', label: 'Thứ 4' },
    { key: '4', label: 'Thứ 5' },
    { key: '5', label: 'Thứ 6' },
    { key: '6', label: 'Thứ 7' },
    { key: '7', label: 'Chủ nhật' },
];

const startHours = props.branch?.opening_hours ?? props.defaults.opening_hours;
const hours: Hours = Object.fromEntries(weekdays.map((d) => [d.key, (startHours[d.key] ?? []).map((r) => [r[0], r[1]] as [string, string])]));

const form = useForm({
    code: props.branch?.code ?? '',
    name: props.branch?.name ?? '',
    address: props.branch?.address ?? '',
    phone: props.branch?.phone ?? '',
    is_active: props.branch?.is_active ?? true,
    opening_hours: hours,
});

const errors = computed(() => form.errors as Record<string, string | undefined>);
const dayError = (day: string) => Object.entries(errors.value).find(([key]) => key.startsWith(`opening_hours.${day}.`))?.[1];

const toggleDay = (day: string, open: boolean) => {
    form.opening_hours[day] = open ? [['08:00', '20:00']] : [];
};
const addRange = (day: string) => form.opening_hours[day].push(['13:00', '17:00']);
const removeRange = (day: string, index: number) => form.opening_hours[day].splice(index, 1);

const submit = () => {
    if (props.branch) form.put(route('app.settings.branches.update', props.branch.id));
    else form.post(route('app.settings.branches.store'));
};
</script>

<template>
    <Head :title="editing ? `Sửa ${branch?.name}` : 'Thêm cơ sở'" />

    <AppLayout>
        <form class="flex max-w-3xl flex-col gap-8 p-4 md:p-6" @submit.prevent="submit">
            <PageHeader :title="editing ? (branch?.name ?? '') : 'Thêm cơ sở'">
                <template #before><BackLink :href="route('app.settings.branches')">Cơ sở</BackLink></template>
                <template v-if="branch && canRates" #subtitle>
                    <Link :href="route('app.settings.online-rates', branch.id)" class="text-primary hover:underline"
                        >Tỉ lệ đặt online của cơ sở →</Link
                    >
                </template>
            </PageHeader>

            <section class="grid gap-4 rounded-[10px] border bg-card p-6">
                <div class="grid gap-4 sm:grid-cols-[140px_1fr]">
                    <div class="grid gap-2">
                        <Label for="code">Mã cơ sở</Label>
                        <Input id="code" v-model="form.code" class="font-mono uppercase" maxlength="20" :disabled="editing" required />
                        <p class="text-xs text-muted-foreground">{{ editing ? 'Không đổi được (đầu số chứng từ).' : 'Đầu số chứng từ, vd Q3.' }}</p>
                        <InputError :message="form.errors.code" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="name">Tên cơ sở</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="address">Địa chỉ</Label>
                    <Input id="address" v-model="form.address" />
                    <InputError :message="form.errors.address" />
                </div>
                <div class="grid gap-2 sm:max-w-xs">
                    <Label for="phone">Điện thoại</Label>
                    <Input id="phone" v-model="form.phone" type="tel" placeholder="+842839300001" />
                    <InputError :message="form.errors.phone" />
                </div>
                <label v-if="editing" class="flex items-center gap-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="size-4 accent-primary" />
                    Đang hoạt động
                </label>
                <InputError :message="errors.is_active" />
            </section>

            <section class="rounded-[10px] border bg-card p-6">
                <h2 class="mb-4 text-xl font-semibold">Giờ mở cửa</h2>
                <div class="grid gap-3">
                    <div v-for="day in weekdays" :key="day.key" class="grid items-start gap-2 sm:grid-cols-[120px_1fr]">
                        <label class="flex h-9 items-center gap-2 text-sm font-medium">
                            <input
                                type="checkbox"
                                class="size-4 accent-primary"
                                :checked="form.opening_hours[day.key].length > 0"
                                @change="toggleDay(day.key, ($event.target as HTMLInputElement).checked)"
                            />
                            {{ day.label }}
                        </label>
                        <div class="grid gap-2">
                            <span v-if="!form.opening_hours[day.key].length" class="flex h-9 items-center text-sm text-muted-foreground">Nghỉ</span>
                            <div v-for="(range, index) in form.opening_hours[day.key]" :key="index" class="flex items-center gap-2">
                                <Input v-model="range[0]" type="time" class="w-32" :aria-label="`${day.label} mở cửa`" />
                                <span class="text-muted-foreground">–</span>
                                <Input v-model="range[1]" type="time" class="w-32" :aria-label="`${day.label} đóng cửa`" />
                                <Button v-if="index > 0" type="button" variant="ghost" size="sm" @click="removeRange(day.key, index)">Bỏ</Button>
                                <Button
                                    v-else-if="form.opening_hours[day.key].length < 3"
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="addRange(day.key)"
                                    >+ Ca</Button
                                >
                            </div>
                            <InputError :message="dayError(day.key)" />
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    {{ editing ? 'Lưu' : 'Thêm cơ sở' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('app.settings.branches')">Hủy</Link>
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
