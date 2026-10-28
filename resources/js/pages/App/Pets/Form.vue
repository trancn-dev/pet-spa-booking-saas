<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BackLink from '@/components/pb/BackLink.vue';
import FormField from '@/components/pb/FormField.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SpeciesOption } from '@/types/pets';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Sparkles } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface PetData {
    id: string;
    name: string;
    species_id: number;
    breed_id: number | null;
    sex: string;
    birth_date: string | null;
    weight_kg: string | null;
    size: string;
    is_neutered: boolean;
    allergies: string | null;
    chronic_conditions: string | null;
    behavior_notes: string | null;
}

const props = defineProps<{
    customer: { id: string; name: string; is_system: boolean };
    pet: PetData | null;
    species: SpeciesOption[];
    sizes: { value: string; label: string }[];
    sexes: { value: string; label: string }[];
}>();

const form = useForm({
    name: props.pet?.name ?? '',
    species_id: props.pet?.species_id ?? props.species[0]?.id ?? null,
    breed_id: props.pet?.breed_id ?? null,
    sex: props.pet?.sex ?? 'unknown',
    birth_date: props.pet?.birth_date ?? '',
    weight_kg: props.pet?.weight_kg ?? '',
    size: props.pet?.size ?? '',
    is_neutered: props.pet?.is_neutered ?? false,
    allergies: props.pet?.allergies ?? '',
    chronic_conditions: props.pet?.chronic_conditions ?? '',
    behavior_notes: props.pet?.behavior_notes ?? '',
});

const breeds = computed(() => props.species.find((s) => s.id === form.species_id)?.breeds ?? []);
watch(
    () => form.species_id,
    () => {
        if (!breeds.value.some((b) => b.id === form.breed_id)) form.breed_id = null;
    },
);

// Size hint from the server (PetSizeSuggester): the weight if known, else the breed's usual size.
const hint = ref<{ size: string; label: string; reason: string } | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;
watch(
    () => [form.species_id, form.breed_id, form.weight_kg],
    () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const params: Record<string, string> = {};
            if (form.species_id) params.species_id = String(form.species_id);
            if (form.breed_id) params.breed_id = String(form.breed_id);
            if (form.weight_kg) params.weight_kg = String(form.weight_kg);
            try {
                const res = await fetch(route('app.pets.size-suggestion', params), { headers: { Accept: 'application/json' } });
                hint.value = res.ok ? ((await res.json()).data ?? null) : null;
            } catch {
                hint.value = null;
            }
        }, 250);
    },
    { immediate: true },
);

const submit = () => {
    if (props.pet) form.put(route('app.pets.update', props.pet.id));
    else form.post(route('app.pets.store', props.customer.id));
};
const back = props.pet ? route('app.pets.show', props.pet.id) : route('app.customers.show', props.customer.id);
const textarea = 'rounded-md border border-input bg-background px-3 py-2 text-sm';
const select = 'h-9 rounded-md border border-input bg-background px-3 text-sm';
</script>

<template>
    <Head :title="pet ? pet.name : 'Thêm bé'" />

    <AppLayout>
        <form class="flex max-w-3xl flex-col gap-6 p-4 md:p-6" @submit.prevent="submit">
            <PageHeader :title="pet ? 'Sửa hồ sơ bé' : `Thêm bé cho ${customer.name}`">
                <template #before
                    ><BackLink :href="back">{{ pet ? pet.name : customer.name }}</BackLink></template
                >
            </PageHeader>

            <section class="grid gap-4 rounded-[10px] border bg-card p-6 sm:grid-cols-2">
                <FormField label="Tên bé" input-id="name" :error="form.errors.name"
                    ><Input id="name" v-model="form.name" required maxlength="100" autocomplete="off"
                /></FormField>
                <FormField label="Giới tính" input-id="sex" :error="form.errors.sex"
                    ><select id="sex" v-model="form.sex" :class="select">
                        <option v-for="s in sexes" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select></FormField
                >
                <FormField label="Loài" input-id="species_id" :error="form.errors.species_id"
                    ><select id="species_id" v-model="form.species_id" :class="select" required>
                        <option v-for="s in species" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select></FormField
                >
                <FormField label="Giống" input-id="breed_id" :error="form.errors.breed_id"
                    ><select id="breed_id" v-model="form.breed_id" :class="select">
                        <option :value="null">Không rõ / lai</option>
                        <option v-for="b in breeds" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select></FormField
                >
                <FormField label="Ngày sinh (ước lượng được)" input-id="birth_date" :error="form.errors.birth_date"
                    ><Input id="birth_date" v-model="form.birth_date" type="date"
                /></FormField>
                <FormField label="Cân nặng (kg)" input-id="weight_kg" :error="form.errors.weight_kg"
                    ><Input id="weight_kg" v-model="form.weight_kg" type="number" step="0.01" min="0.01" max="999.99" inputmode="decimal"
                /></FormField>
                <div class="grid gap-2">
                    <Label for="size">Cỡ</Label>
                    <select id="size" v-model="form.size" :class="select">
                        <option value="">{{ hint ? `Theo gợi ý: ${hint.label}` : 'Chọn cỡ' }}</option>
                        <option v-for="s in sizes" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <p v-if="hint" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Sparkles class="size-3.5" /> Gợi ý {{ hint.label }}: {{ hint.reason }}.
                        <button v-if="form.size !== hint.size" type="button" class="text-primary hover:underline" @click="form.size = hint!.size">
                            Dùng
                        </button>
                    </p>
                    <p class="text-xs text-muted-foreground">Giá và thời lượng dịch vụ tính theo cỡ.</p>
                    <InputError :message="form.errors.size" />
                </div>
                <label class="flex items-center gap-2 self-center text-sm">
                    <input v-model="form.is_neutered" type="checkbox" class="accent-primary" /> Đã triệt sản
                </label>
            </section>

            <section class="grid gap-4 rounded-[10px] border bg-card p-6">
                <div>
                    <h2 class="text-xl font-semibold">Cảnh báo an toàn</h2>
                    <p class="text-sm text-muted-foreground">Hiện thành badge ở mọi nơi có bé, mọi nhân viên đều thấy.</p>
                </div>
                <div class="grid gap-2">
                    <Label for="allergies">Dị ứng</Label>
                    <textarea
                        id="allergies"
                        v-model="form.allergies"
                        rows="2"
                        maxlength="2000"
                        :class="textarea"
                        placeholder="vd: dị ứng penicillin"
                    />
                    <InputError :message="form.errors.allergies" />
                </div>
                <div class="grid gap-2">
                    <Label for="chronic_conditions">Bệnh nền</Label>
                    <textarea
                        id="chronic_conditions"
                        v-model="form.chronic_conditions"
                        rows="2"
                        maxlength="2000"
                        :class="textarea"
                        placeholder="vd: tim, động kinh"
                    />
                    <InputError :message="form.errors.chronic_conditions" />
                </div>
                <FormField label="Lưu ý hành vi" input-id="behavior_notes" :error="form.errors.behavior_notes"
                    ><Input id="behavior_notes" v-model="form.behavior_notes" maxlength="255" placeholder="vd: cắn người, sợ máy sấy"
                /></FormField>
            </section>

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    {{ pet ? 'Lưu' : 'Thêm bé' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="back">Hủy</Link>
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
