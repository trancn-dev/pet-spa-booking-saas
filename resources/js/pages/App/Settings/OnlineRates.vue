<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Rate {
    id: number;
    service_group: string;
    ratio: string;
    weekday: number | null;
    start_time: string | null;
    end_time: string | null;
}

const props = defineProps<{
    branch: { id: string; name: string };
    rates: Rate[];
    groups: { value: string; label: string }[];
    weekdays: Record<number, string>;
}>();

const editing = ref<number | null>(null);
const open = ref(false);
const form = useForm({ service_group: 'medical', ratio: '0.5', weekday: null as number | null, start_time: '', end_time: '' });
const errors = computed(() => form.errors as Record<string, string | undefined>);

const start = (rate?: Rate, group?: string) => {
    editing.value = rate?.id ?? null;
    form.clearErrors();
    form.service_group = rate?.service_group ?? group ?? 'medical';
    form.ratio = rate?.ratio ?? '0.5';
    form.weekday = rate?.weekday ?? null;
    form.start_time = rate?.start_time ?? '';
    form.end_time = rate?.end_time ?? '';
    open.value = true;
};
const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (editing.value) form.put(route('app.settings.online-rates.update', { branch: props.branch.id, rate: editing.value }), options);
    else form.post(route('app.settings.online-rates.store', props.branch.id), options);
};
const remove = (rate: Rate) => confirm('Xóa tỉ lệ này?') && router.delete(route('app.settings.online-rates.destroy', { branch: props.branch.id, rate: rate.id }), { preserveScroll: true });

const scope = (r: Rate) => {
    const parts = [r.weekday ? props.weekdays[r.weekday] : null, r.start_time ? `${r.start_time}–${r.end_time}` : null].filter(Boolean);
    return parts.length ? parts.join(' · ') : 'Mặc định';
};
const percent = (ratio: string) => `${Math.round(Number(ratio) * 100)}%`;
const select = 'h-9 rounded-md border border-input bg-background px-3 text-sm';
</script>

<template>
    <Head :title="`Tỉ lệ đặt online · ${branch.name}`" />

    <AppLayout>
        <div class="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
            <div>
                <Link :href="route('app.rosters.index')" class="text-sm text-muted-foreground hover:underline">← Lịch trực</Link>
                <h1 class="mt-1 text-2xl font-semibold">Tỉ lệ đặt online · {{ branch.name }}</h1>
                <p class="text-sm text-muted-foreground">
                    Phần số người trực mà Site được nhận đặt, theo từng nhóm. Vd 0,5 với 2 bác sĩ trực = tối đa 1 lịch khám online cùng lúc. Không có tỉ lệ = Site không
                    nhận đặt online nhóm đó. Ghi đè theo thứ, khung giờ: cụ thể nhất thắng.
                </p>
            </div>

            <section v-for="g in groups" :key="g.value" class="grid gap-3 rounded-[10px] border bg-card p-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold">{{ g.label }}</h2>
                    <Button size="sm" variant="outline" @click="start(undefined, g.value)"><Plus class="size-4" /> Thêm</Button>
                </div>
                <ul class="grid gap-2 text-sm">
                    <li v-for="r in rates.filter((r) => r.service_group === g.value)" :key="r.id" class="flex items-center justify-between rounded-md border px-3 py-2">
                        <span><span class="font-medium">{{ scope(r) }}</span> · {{ percent(r.ratio) }} số người trực</span>
                        <span class="flex gap-1">
                            <button type="button" class="p-1" aria-label="Sửa" @click="start(r)"><Pencil class="size-4" /></button>
                            <button type="button" class="p-1 text-danger" aria-label="Xóa" @click="remove(r)"><Trash2 class="size-4" /></button>
                        </span>
                    </li>
                    <li v-if="!rates.some((r) => r.service_group === g.value)" class="text-muted-foreground">Chưa có: Site không nhận đặt online nhóm {{ g.label }}.</li>
                </ul>
            </section>

            <form v-if="open" class="grid gap-4 rounded-[10px] border bg-card p-6 sm:grid-cols-2" @submit.prevent="save">
                <h2 class="text-xl font-semibold sm:col-span-2">{{ editing ? 'Sửa tỉ lệ' : 'Thêm tỉ lệ' }}</h2>
                <div class="grid gap-2">
                    <Label for="rate_group">Nhóm</Label>
                    <select id="rate_group" v-model="form.service_group" :class="select">
                        <option v-for="g in groups" :key="g.value" :value="g.value">{{ g.label }}</option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="rate_ratio">Tỉ lệ (0 – 1)</Label>
                    <Input id="rate_ratio" v-model="form.ratio" type="number" min="0" max="1" step="0.05" required />
                    <InputError :message="errors.ratio" />
                </div>
                <div class="grid gap-2">
                    <Label for="rate_weekday">Thứ (trống = mọi ngày)</Label>
                    <select id="rate_weekday" v-model="form.weekday" :class="select">
                        <option :value="null">Mọi ngày</option>
                        <option v-for="(label, n) in weekdays" :key="n" :value="Number(n)">{{ label }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-2">
                        <Label for="rate_start">Từ giờ</Label>
                        <Input id="rate_start" v-model="form.start_time" type="time" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="rate_end">Đến giờ</Label>
                        <Input id="rate_end" v-model="form.end_time" type="time" />
                    </div>
                </div>
                <div class="sm:col-span-2"><InputError :message="errors.start_time ?? errors.end_time" /></div>
                <div class="flex gap-2 sm:col-span-2">
                    <Button type="submit" :disabled="form.processing"><LoaderCircle v-if="form.processing" class="size-4 animate-spin" /> Lưu</Button>
                    <Button type="button" variant="outline" @click="open = false">Hủy</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
