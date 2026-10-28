<script setup lang="ts">
import GroupBadge from '@/components/GroupBadge.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import type { SpeciesOption } from '@/types/pets';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Calculator, LoaderCircle, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface Rule {
    id: number;
    species_id: number | null;
    breed_id: number | null;
    size: string | null;
    label: string;
    duration_min: number | null;
    price: string | null;
}

const props = defineProps<{
    service: {
        id: number;
        code: string;
        name: string;
        group: 'medical' | 'grooming';
        group_label: string;
        is_combo: boolean;
        duration_min: number;
        buffer_min: number;
        price: string;
        is_public: boolean;
        is_active: boolean;
        branches: string[];
        items: { code: string; name: string; price: string; duration_min: number }[];
    };
    rules: Rule[];
    species: SpeciesOption[];
    sizes: { value: string; label: string }[];
    can: { manage: boolean; delete: boolean };
}>();

const page = usePage();
const pageErrors = computed(() => page.props.errors as Record<string, string | undefined>);
const select = 'h-9 rounded-md border border-input bg-background px-3 text-sm';
const breedsOf = (speciesId: number | null) => props.species.find((s) => s.id === speciesId)?.breeds ?? [];

// Add / edit a rule.
const editing = ref<number | null>(null);
const ruleForm = useForm({
    species_id: null as number | null,
    breed_id: null as number | null,
    size: '',
    duration_min: '' as number | '',
    price: '',
});
const ruleErrors = computed(() => ruleForm.errors as Record<string, string | undefined>);
const showRuleForm = ref(false);
const startRule = (rule?: Rule) => {
    editing.value = rule?.id ?? null;
    ruleForm.clearErrors();
    ruleForm.species_id = rule?.species_id ?? props.species[0]?.id ?? null;
    ruleForm.breed_id = rule?.breed_id ?? null;
    ruleForm.size = rule?.size ?? '';
    ruleForm.duration_min = rule?.duration_min ?? '';
    ruleForm.price = rule?.price ?? '';
    showRuleForm.value = true;
};
const saveRule = () => {
    const options = { preserveScroll: true, onSuccess: () => (showRuleForm.value = false) };
    if (editing.value) ruleForm.put(route('app.services.rules.update', { service: props.service.id, rule: editing.value }), options);
    else ruleForm.post(route('app.services.rules.store', props.service.id), options);
};
const deleteRule = (rule: Rule) => {
    if (!confirm(`Xóa quy tắc "${rule.label}"?`)) return;
    router.delete(route('app.services.rules.destroy', { service: props.service.id, rule: rule.id }), { preserveScroll: true });
};
const destroy = () => {
    if (!confirm(`Xóa dịch vụ ${props.service.name}?`)) return;
    router.delete(route('app.services.destroy', props.service.id));
};

// "Thử tính": ask the server's resolver for a species / breed / size.
const trial = reactive({ species_id: props.species[0]?.id ?? null, breed_id: null as number | null, size: 'm' });
const quote = ref<{ duration_min: number; buffer_min: number; price: string; explanation: string } | null>(null);
const quoting = ref(false);
watch(
    () => trial.species_id,
    () => {
        if (!breedsOf(trial.species_id).some((b) => b.id === trial.breed_id)) trial.breed_id = null;
    },
);
watch(
    () => ({ ...trial }),
    async () => {
        quoting.value = true;
        const params: Record<string, string> = { service: String(props.service.id) };
        if (trial.species_id) params.species_id = String(trial.species_id);
        if (trial.breed_id) params.breed_id = String(trial.breed_id);
        if (trial.size) params.size = trial.size;
        try {
            const res = await fetch(route('app.services.quote', params), { headers: { Accept: 'application/json' } });
            quote.value = res.ok ? (await res.json()).data : null;
        } finally {
            quoting.value = false;
        }
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <Head :title="service.name" />

    <AppLayout>
        <div class="flex max-w-5xl flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <Link :href="route('app.services.index')" class="text-sm text-muted-foreground hover:underline">← Dịch vụ</Link>
                    <h1 class="mt-1 flex flex-wrap items-center gap-2 text-2xl font-semibold">
                        {{ service.name }}
                        <GroupBadge :group="service.group" :label="service.group_label" />
                        <Badge v-if="service.is_combo" tone="primary">Combo</Badge>
                        <Badge v-if="!service.is_active">Ngừng hoạt động</Badge>
                    </h1>
                    <p class="font-mono text-sm text-muted-foreground">{{ service.code }}</p>
                </div>
                <div class="flex gap-2">
                    <Button v-if="can.manage" variant="outline" as-child>
                        <Link :href="route('app.services.edit', service.id)"><Pencil class="size-4" /> Sửa</Link>
                    </Button>
                    <Button v-if="can.delete" variant="outline" class="text-danger" @click="destroy"><Trash2 class="size-4" /> Xóa</Button>
                </div>
            </div>
            <InputError :message="pageErrors.service" />

            <section class="grid gap-3 rounded-[10px] border bg-card p-6 sm:grid-cols-4">
                <div>
                    <div class="text-xs text-muted-foreground">Thời lượng mặc định</div>
                    <div class="tabular-nums">{{ service.duration_min }} phút</div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Buffer</div>
                    <div class="tabular-nums">{{ service.buffer_min }} phút</div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Giá mặc định</div>
                    <div class="tabular-nums">{{ formatMoney(service.price) }}</div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Cơ sở</div>
                    <div>{{ service.branches.length ? service.branches.join(', ') : 'Mọi cơ sở' }}</div>
                </div>
                <div v-if="service.is_combo" class="sm:col-span-4">
                    <div class="text-xs text-muted-foreground">Gồm</div>
                    <ol class="mt-1 grid gap-1 text-sm">
                        <li v-for="(i, n) in service.items" :key="i.code">
                            {{ n + 1 }}. {{ i.name }}
                            <span class="text-muted-foreground">· {{ i.duration_min }} phút · {{ formatMoney(i.price) }}</span>
                        </li>
                    </ol>
                </div>
            </section>

            <section class="grid gap-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-xl font-semibold">Quy tắc thời lượng / giá</h2>
                        <p class="text-sm text-muted-foreground">Cụ thể nhất thắng: giống → loài + cỡ → loài → mặc định của dịch vụ.</p>
                    </div>
                    <Button v-if="can.manage" size="sm" @click="startRule()"><Plus class="size-4" /> Thêm quy tắc</Button>
                </div>

                <form v-if="showRuleForm" class="grid gap-4 rounded-[10px] border bg-card p-4 sm:grid-cols-5 sm:items-end" @submit.prevent="saveRule">
                    <div class="grid gap-2">
                        <Label for="rule_species">Loài</Label>
                        <select id="rule_species" v-model="ruleForm.species_id" :class="select">
                            <option :value="null">Mọi loài</option>
                            <option v-for="s in species" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="rule_breed">Giống</Label>
                        <select id="rule_breed" v-model="ruleForm.breed_id" :class="select" :disabled="!ruleForm.species_id">
                            <option :value="null">Mọi giống</option>
                            <option v-for="b in breedsOf(ruleForm.species_id)" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="rule_size">Cỡ</Label>
                        <select id="rule_size" v-model="ruleForm.size" :class="select">
                            <option value="">Mọi cỡ</option>
                            <option v-for="s in sizes" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="rule_duration">Phút</Label>
                        <Input
                            id="rule_duration"
                            v-model.number="ruleForm.duration_min"
                            type="number"
                            min="5"
                            max="1440"
                            :placeholder="String(service.duration_min)"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="rule_price">Giá (₫)</Label>
                        <Input id="rule_price" v-model="ruleForm.price" type="number" min="0" step="1000" :placeholder="service.price" />
                    </div>
                    <div class="flex flex-wrap items-center gap-3 sm:col-span-5">
                        <Button type="submit" size="sm" :disabled="ruleForm.processing">
                            <LoaderCircle v-if="ruleForm.processing" class="size-4 animate-spin" /> {{ editing ? 'Lưu quy tắc' : 'Thêm' }}
                        </Button>
                        <Button type="button" size="sm" variant="outline" @click="showRuleForm = false">Hủy</Button>
                        <span class="text-xs text-muted-foreground">Để trống phút hoặc giá = lấy từ quy tắc chung hơn.</span>
                    </div>
                    <div class="sm:col-span-5">
                        <InputError :message="ruleErrors.species_id ?? ruleErrors.breed_id ?? ruleErrors.duration_min ?? ruleErrors.price" />
                    </div>
                </form>

                <div class="overflow-x-auto rounded-[10px] border bg-card">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-surface-muted text-left text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 font-medium">Áp dụng cho</th>
                                <th class="px-4 py-2 text-right font-medium">Thời lượng</th>
                                <th class="px-4 py-2 text-right font-medium">Giá</th>
                                <th v-if="can.manage" class="px-4 py-2" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in rules" :key="r.id" class="border-b last:border-0">
                                <td class="px-4 py-3">{{ r.label }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ r.duration_min ? `${r.duration_min} phút` : '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ r.price ? formatMoney(r.price) : '—' }}</td>
                                <td v-if="can.manage" class="whitespace-nowrap px-4 py-3 text-right">
                                    <button type="button" class="p-1" aria-label="Sửa quy tắc" @click="startRule(r)">
                                        <Pencil class="size-4" />
                                    </button>
                                    <button type="button" class="p-1 text-danger" aria-label="Xóa quy tắc" @click="deleteRule(r)">
                                        <Trash2 class="size-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!rules.length">
                                <td :colspan="can.manage ? 4 : 3" class="px-4 py-6 text-center text-muted-foreground">
                                    Chưa có quy tắc: mọi pet dùng thời lượng, giá mặc định.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-4 rounded-[10px] border bg-card p-6">
                <h2 class="flex items-center gap-2 text-xl font-semibold"><Calculator class="size-5" /> Thử tính</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="trial_species">Loài</Label>
                        <select id="trial_species" v-model="trial.species_id" :class="select">
                            <option v-for="s in species" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="trial_breed">Giống</Label>
                        <select id="trial_breed" v-model="trial.breed_id" :class="select">
                            <option :value="null">Không rõ / lai</option>
                            <option v-for="b in breedsOf(trial.species_id)" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="trial_size">Cỡ</Label>
                        <select id="trial_size" v-model="trial.size" :class="select">
                            <option v-for="s in sizes" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                </div>
                <div v-if="quote" class="rounded-md bg-surface-muted p-4" aria-live="polite" :class="{ 'opacity-60': quoting }">
                    <div class="text-lg font-semibold tabular-nums">
                        {{ quote.duration_min }} phút<template v-if="quote.buffer_min"> + {{ quote.buffer_min }} phút buffer</template> ·
                        {{ formatMoney(quote.price) }}
                        <span class="text-sm font-normal text-muted-foreground">(tham khảo)</span>
                    </div>
                    <div class="text-sm text-muted-foreground">{{ quote.explanation }}</div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
