<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BackLink from '@/components/pb/BackLink.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, LoaderCircle, X } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface Choice {
    id: number;
    code: string;
    name: string;
    price: string;
    duration_min: number;
}

const props = defineProps<{
    service: {
        id: number;
        code: string;
        name: string;
        service_group: 'medical' | 'grooming';
        is_combo: boolean;
        duration_min: number;
        buffer_min: number;
        price: string;
        is_public: boolean;
        is_active: boolean;
        branch_ids: number[];
        item_ids: number[];
    } | null;
    branches: { id: number; code: string; name: string }[];
    groomingServices: Choice[];
}>();

const form = useForm({
    code: props.service?.code ?? '',
    name: props.service?.name ?? '',
    service_group: props.service?.service_group ?? 'medical',
    is_combo: props.service?.is_combo ?? false,
    duration_min: props.service?.duration_min ?? 30,
    buffer_min: props.service?.buffer_min ?? 0,
    price: props.service?.price ?? '',
    is_public: props.service?.is_public ?? true,
    is_active: props.service?.is_active ?? true,
    branch_ids: [...(props.service?.branch_ids ?? [])],
    item_ids: [...(props.service?.item_ids ?? [])],
});
const errors = computed(() => form.errors as Record<string, string | undefined>);

// Combos are spa only (design S9): ticking "Combo" switches the group to Spa.
watch(
    () => form.is_combo,
    (combo) => {
        if (combo) form.service_group = 'grooming';
    },
);

const byId = computed(() => new Map(props.groomingServices.map((s) => [s.id, s])));
const items = computed(() => form.item_ids.map((id) => byId.value.get(id)).filter((s): s is Choice => !!s));
const available = computed(() => props.groomingServices.filter((s) => !form.item_ids.includes(s.id)));
const itemsPrice = computed(() => items.value.reduce((sum, s) => sum + Number(s.price), 0));
const itemsDuration = computed(() => items.value.reduce((sum, s) => sum + s.duration_min, 0));

const addItem = (event: Event) => {
    const id = Number((event.target as HTMLSelectElement).value);
    if (id) form.item_ids.push(id);
    (event.target as HTMLSelectElement).value = '';
};
const move = (i: number, by: number) => {
    const j = i + by;
    if (j < 0 || j >= form.item_ids.length) return;
    [form.item_ids[i], form.item_ids[j]] = [form.item_ids[j], form.item_ids[i]];
};
const toggleBranch = (id: number) => {
    const i = form.branch_ids.indexOf(id);
    if (i >= 0) form.branch_ids.splice(i, 1);
    else form.branch_ids.push(id);
};

const submit = () => {
    if (props.service) form.put(route('app.services.update', props.service.id));
    else form.post(route('app.services.store'));
};
const back = props.service ? route('app.services.show', props.service.id) : route('app.services.index');
</script>

<template>
    <Head :title="service ? service.name : 'Thêm dịch vụ'" />

    <AppLayout>
        <form class="flex max-w-3xl flex-col gap-6 p-4 md:p-6" @submit.prevent="submit">
            <PageHeader :title="service ? 'Sửa dịch vụ' : 'Thêm dịch vụ'">
                <template #before
                    ><BackLink :href="back">{{ service ? service.name : 'Dịch vụ' }}</BackLink></template
                >
            </PageHeader>

            <section class="grid gap-4 rounded-[10px] border bg-card p-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="code">Mã</Label>
                    <Input id="code" v-model="form.code" required maxlength="30" class="font-mono uppercase" placeholder="KHAM-TQ" />
                    <InputError :message="form.errors.code" />
                </div>
                <div class="grid gap-2">
                    <Label for="name">Tên</Label>
                    <Input id="name" v-model="form.name" required maxlength="191" />
                    <InputError :message="form.errors.name" />
                </div>
                <fieldset class="grid gap-2">
                    <legend class="text-sm font-medium">Nhóm</legend>
                    <div class="flex gap-4 text-sm">
                        <label class="flex items-center gap-2" :class="{ 'opacity-50': form.is_combo }">
                            <input v-model="form.service_group" type="radio" value="medical" class="accent-primary" :disabled="form.is_combo" /> Khám
                        </label>
                        <label class="flex items-center gap-2"
                            ><input v-model="form.service_group" type="radio" value="grooming" class="accent-primary" /> Spa</label
                        >
                    </div>
                    <p class="text-xs text-muted-foreground">Khám, tiêm, xét nghiệm, chẩn đoán hình ảnh là Khám; tắm, cắt tỉa là Spa.</p>
                    <InputError :message="form.errors.service_group" />
                </fieldset>
                <label class="flex items-center gap-2 self-start text-sm">
                    <input v-model="form.is_combo" type="checkbox" class="accent-primary" /> Combo spa (gồm nhiều dịch vụ spa, giá riêng)
                </label>
                <div class="grid gap-2">
                    <Label for="duration_min">Thời lượng mặc định (phút)</Label>
                    <Input id="duration_min" v-model.number="form.duration_min" type="number" min="5" max="1440" required />
                    <p v-if="form.is_combo && items.length" class="text-xs text-muted-foreground">
                        Gợi ý: {{ itemsDuration }} phút (tổng dịch vụ con).
                        <button
                            v-if="form.duration_min !== itemsDuration"
                            type="button"
                            class="text-primary hover:underline"
                            @click="form.duration_min = itemsDuration"
                        >
                            Dùng
                        </button>
                    </p>
                    <InputError :message="form.errors.duration_min" />
                </div>
                <div class="grid gap-2">
                    <Label for="buffer_min">Buffer sau dịch vụ (phút)</Label>
                    <Input id="buffer_min" v-model.number="form.buffer_min" type="number" min="0" max="240" />
                    <p class="text-xs text-muted-foreground">Thời gian dọn dẹp, giữ chỗ trên lịch nhưng không tính vào thời lượng.</p>
                    <InputError :message="form.errors.buffer_min" />
                </div>
                <div class="grid gap-2">
                    <Label for="price">{{ form.is_combo ? 'Giá combo' : 'Giá mặc định' }} (₫)</Label>
                    <Input id="price" v-model="form.price" type="number" min="0" step="1000" required inputmode="numeric" />
                    <p v-if="form.is_combo && items.length" class="text-xs text-muted-foreground">
                        Tổng giá lẻ: {{ formatMoney(itemsPrice) }} (tham khảo).
                    </p>
                    <InputError :message="form.errors.price" />
                </div>
                <div class="grid content-start gap-2 text-sm">
                    <label class="flex items-center gap-2"
                        ><input v-model="form.is_public" type="checkbox" class="accent-primary" /> Hiện trên Site</label
                    >
                    <label class="flex items-center gap-2"
                        ><input v-model="form.is_active" type="checkbox" class="accent-primary" /> Đang hoạt động</label
                    >
                </div>
            </section>

            <section v-if="form.is_combo" class="grid gap-3 rounded-[10px] border bg-card p-6">
                <div>
                    <h2 class="text-xl font-semibold">Dịch vụ trong combo</h2>
                    <p class="text-sm text-muted-foreground">
                        Chỉ dịch vụ spa, không chứa combo khác. Khám + spa là các dịch vụ riêng, gom chung hóa đơn.
                    </p>
                </div>
                <ol class="grid gap-2">
                    <li v-for="(s, i) in items" :key="s.id" class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm">
                        <span class="w-5 tabular-nums text-muted-foreground">{{ i + 1 }}.</span>
                        <span class="flex-1"
                            ><span class="font-mono text-xs text-muted-foreground">{{ s.code }}</span> {{ s.name }}</span
                        >
                        <span class="tabular-nums text-muted-foreground">{{ s.duration_min }} phút · {{ formatMoney(s.price) }}</span>
                        <button type="button" class="p-1 disabled:opacity-30" :disabled="i === 0" aria-label="Lên" @click="move(i, -1)">
                            <ArrowUp class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="p-1 disabled:opacity-30"
                            :disabled="i === items.length - 1"
                            aria-label="Xuống"
                            @click="move(i, 1)"
                        >
                            <ArrowDown class="size-4" />
                        </button>
                        <button type="button" class="p-1 text-danger" aria-label="Bỏ" @click="form.item_ids.splice(i, 1)">
                            <X class="size-4" />
                        </button>
                    </li>
                </ol>
                <select
                    class="h-9 max-w-md rounded-md border border-input bg-background px-3 text-sm"
                    aria-label="Thêm dịch vụ spa"
                    @change="addItem"
                >
                    <option value="">+ Thêm dịch vụ spa…</option>
                    <option v-for="s in available" :key="s.id" :value="s.id">{{ s.code }} · {{ s.name }}</option>
                </select>
                <InputError :message="errors.item_ids" />
            </section>

            <section v-if="branches.length > 1" class="grid gap-3 rounded-[10px] border bg-card p-6">
                <div>
                    <h2 class="text-xl font-semibold">Cơ sở áp dụng</h2>
                    <p class="text-sm text-muted-foreground">Không chọn cơ sở nào = có ở mọi cơ sở.</p>
                </div>
                <div class="flex flex-wrap gap-3 text-sm">
                    <label v-for="b in branches" :key="b.id" class="flex items-center gap-2">
                        <input type="checkbox" class="accent-primary" :checked="form.branch_ids.includes(b.id)" @change="toggleBranch(b.id)" />
                        {{ b.name }}
                    </label>
                </div>
                <InputError :message="errors.branch_ids" />
            </section>

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    {{ service ? 'Lưu' : 'Thêm dịch vụ' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="back">Hủy</Link>
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
