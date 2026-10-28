<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BackLink from '@/components/pb/BackLink.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock } from 'lucide-vue-next';
import { computed } from 'vue';

interface StaffData {
    id: number;
    display_name: string;
    email: string | null;
    has_account: boolean;
    phone: string | null;
    title: string | null;
    color: string | null;
    license_no: string | null;
    qualifications: string | null;
    status: 'active' | 'disabled';
    is_owner: boolean;
    roles: string[];
    primary_branch: string | null;
    branches: string[];
}

const props = defineProps<{
    staff: StaffData | null;
    roles: { name: string; label: string }[];
    branches: { id: string; code: string; name: string }[];
    colors: string[];
}>();

const editing = computed(() => props.staff !== null);
const hasAccount = computed(() => props.staff?.has_account ?? false);

const form = useForm({
    display_name: props.staff?.display_name ?? '',
    phone: props.staff?.phone ?? '',
    title: props.staff?.title ?? '',
    color: props.staff?.color ?? props.colors[0],
    license_no: props.staff?.license_no ?? '',
    qualifications: props.staff?.qualifications ?? '',
    roles: [...(props.staff?.roles ?? [])],
    primary_branch: props.staff?.primary_branch ?? props.branches[0]?.id ?? '',
    branches: [...(props.staff?.branches ?? [])],
});
const errors = computed(() => form.errors as Record<string, string | undefined>);

// Professional block only with the "Bác sĩ" role (design S11).
const isVet = computed(() => form.roles.includes('vet'));

const toggle = (list: string[], value: string) => {
    const i = list.indexOf(value);
    if (i >= 0) list.splice(i, 1);
    else list.push(value);
};

const submit = () => {
    if (props.staff) form.put(route('app.staff.update', props.staff.id));
    else form.post(route('app.staff.store'));
};
</script>

<template>
    <Head :title="editing ? staff!.display_name : 'Nhân viên không tài khoản'" />

    <AppLayout>
        <form class="flex max-w-3xl flex-col gap-8 p-4 md:p-6" @submit.prevent="submit">
            <PageHeader :title="editing ? staff!.display_name : 'Thêm nhân viên không tài khoản'">
                <template #before><BackLink :href="route('app.staff.index')">Nhân viên</BackLink></template>
                <template #subtitle>
                    <template v-if="!editing">Bảo vệ, lao công…: không đăng nhập, không tính vào giới hạn gói.</template>
                    <template v-else>{{ staff!.email ?? 'Không có tài khoản' }}</template>
                </template>
            </PageHeader>

            <section class="grid gap-4 rounded-[10px] border bg-card p-6">
                <h2 class="text-xl font-semibold">Thông tin</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="display_name">Họ tên hiển thị</Label>
                        <Input id="display_name" v-model="form.display_name" required />
                        <InputError :message="form.errors.display_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="phone">Điện thoại</Label>
                        <Input id="phone" v-model="form.phone" type="tel" />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="title">Chức danh hiển thị</Label>
                        <Input id="title" v-model="form.title" maxlength="50" placeholder="Lễ tân · Thu ngân" />
                        <p class="text-xs text-muted-foreground">Chỉ để hiển thị; quyền lấy từ role.</p>
                        <InputError :message="form.errors.title" />
                    </div>
                    <fieldset class="grid gap-2">
                        <legend class="text-sm font-medium">Màu trên lịch</legend>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="c in colors"
                                :key="c"
                                type="button"
                                class="size-7 rounded-full ring-offset-2 ring-offset-background"
                                :class="{ 'ring-2 ring-foreground': form.color === c }"
                                :style="{ backgroundColor: c }"
                                :aria-label="`Màu ${c}`"
                                :aria-pressed="form.color === c"
                                @click="form.color = c"
                            />
                        </div>
                    </fieldset>
                </div>
            </section>

            <section v-if="hasAccount" class="grid gap-3 rounded-[10px] border bg-card p-6">
                <h2 class="text-xl font-semibold">Role</h2>
                <p v-if="staff?.is_owner" class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Lock class="size-4" /> Chủ shop (giữ nguyên). Thêm role khác nếu kiêm nhiệm, vd Bác sĩ.
                </p>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="r in roles"
                        :key="r.name"
                        class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
                        :class="{ 'border-primary bg-primary-soft': form.roles.includes(r.name) }"
                    >
                        <input type="checkbox" class="accent-primary" :checked="form.roles.includes(r.name)" @change="toggle(form.roles, r.name)" />
                        {{ r.label }}
                    </label>
                </div>
                <InputError :message="form.errors.roles" />
            </section>

            <section v-if="isVet" class="grid gap-4 rounded-[10px] border bg-card p-6">
                <h2 class="text-xl font-semibold">Chuyên môn</h2>
                <div class="grid gap-2 sm:max-w-sm">
                    <Label for="license_no">Số chứng chỉ hành nghề</Label>
                    <Input id="license_no" v-model="form.license_no" maxlength="50" />
                    <InputError :message="form.errors.license_no" />
                </div>
                <div class="grid gap-2">
                    <Label for="qualifications">Bằng cấp, kinh nghiệm</Label>
                    <textarea
                        id="qualifications"
                        v-model="form.qualifications"
                        rows="3"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.qualifications" />
                </div>
            </section>

            <section class="grid gap-4 rounded-[10px] border bg-card p-6">
                <h2 class="text-xl font-semibold">Cơ sở</h2>
                <div class="grid gap-2 sm:max-w-sm">
                    <Label for="primary_branch">Cơ sở chính</Label>
                    <select id="primary_branch" v-model="form.primary_branch" class="h-9 rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.code }} · {{ b.name }}</option>
                    </select>
                    <InputError :message="errors.primary_branch" />
                </div>
                <fieldset v-if="branches.length > 1" class="grid gap-2">
                    <legend class="text-sm font-medium">Cơ sở phụ</legend>
                    <div class="flex flex-wrap gap-3 text-sm">
                        <label v-for="b in branches.filter((b) => b.id !== form.primary_branch)" :key="b.id" class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                class="accent-primary"
                                :checked="form.branches.includes(b.id)"
                                @change="toggle(form.branches, b.id)"
                            />
                            {{ b.name }}
                        </label>
                    </div>
                    <InputError :message="errors.branches" />
                </fieldset>
            </section>

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    {{ editing ? 'Lưu' : 'Thêm nhân viên' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('app.staff.index')">Hủy</Link>
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
