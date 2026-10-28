<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppModal from '@/components/pb/AppModal.vue';
import SettingsLayout from '@/components/shell/SettingsLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Lock, Plus, ShieldAlert, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface RoleRow {
    id: number;
    name: string;
    display_name: string;
    is_owner: boolean;
    is_template: boolean;
    members_count: number;
    permissions: string[];
}

interface PermissionItem {
    code: string;
    label: string;
    description: string | null;
    sensitive: boolean;
    assignable: boolean;
    phase: string;
}

const props = defineProps<{
    roles: RoleRow[];
    catalog: { key: string; label: string; permissions: PermissionItem[] }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Cài đặt › Phân quyền', href: route('app.settings.roles') }];

const page = usePage<{ flash?: { status?: string }; errors: Record<string, string> }>();
const initialId = Number(new URLSearchParams(window.location.search).get('role')) || props.roles[0]?.id;
const selectedId = ref<number | 'new'>(initialId);
const selected = computed(() => (selectedId.value === 'new' ? null : (props.roles.find((r) => r.id === selectedId.value) ?? null)));
const locked = computed(() => selected.value?.is_owner ?? false);

const form = useForm({ display_name: '', permissions: [] as string[], confirm_sensitive: false });

const load = () => {
    form.clearErrors();
    form.display_name = selected.value?.display_name ?? '';
    form.permissions = [...(selected.value?.permissions ?? [])];
    form.confirm_sensitive = false;
};
watch(selectedId, load, { immediate: true });
watch(() => props.roles, load);

const allPermissions = computed(() => props.catalog.flatMap((m) => m.permissions));
const newlySensitive = computed(() =>
    allPermissions.value.filter((p) => p.sensitive && form.permissions.includes(p.code) && !(selected.value?.permissions ?? []).includes(p.code)),
);

const toggle = (code: string, on: boolean) => {
    form.permissions = on ? [...new Set([...form.permissions, code])] : form.permissions.filter((c) => c !== code);
};

const confirmOpen = ref(false);

const submit = () => {
    if (newlySensitive.value.length && !form.confirm_sensitive) {
        confirmOpen.value = true;
        return;
    }
    const options = { preserveScroll: true, onFinish: () => (form.confirm_sensitive = false) };
    if (selected.value) {
        form.put(route('app.settings.roles.update', selected.value.id), options);
    } else {
        form.post(route('app.settings.roles.store'), options);
    }
};

const confirmSensitive = () => {
    form.confirm_sensitive = true;
    confirmOpen.value = false;
    submit();
};

const destroy = () => {
    if (!selected.value || !confirm(`Xóa role "${selected.value.display_name}"?`)) return;
    router.delete(route('app.settings.roles.destroy', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => (selectedId.value = props.roles[0]?.id),
    });
};
</script>

<template>
    <Head title="Phân quyền" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <SettingsLayout>
            <div class="flex flex-col gap-6 lg:flex-row">
                <!-- Role list -->
                <aside class="w-full shrink-0 lg:w-64">
                    <ul class="flex flex-col gap-1">
                        <li v-for="role in roles" :key="role.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm"
                                :class="selectedId === role.id ? 'bg-accent font-medium' : 'hover:bg-accent/60'"
                                @click="selectedId = role.id"
                            >
                                <span class="flex items-center gap-2">
                                    <Lock v-if="role.is_owner" class="size-3.5 text-muted-foreground" />
                                    {{ role.display_name }}
                                </span>
                                <span class="text-xs tabular-nums text-muted-foreground">{{ role.members_count }}</span>
                            </button>
                        </li>
                    </ul>
                    <Button variant="outline" class="mt-3 w-full" @click="selectedId = 'new'"><Plus /> Tạo role</Button>
                </aside>

                <!-- Editor -->
                <section class="min-w-0 flex-1">
                    <div
                        v-if="page.props.flash?.status"
                        role="status"
                        class="mb-4 rounded-md border border-teal-200 bg-teal-50 px-4 py-2 text-sm text-teal-800"
                    >
                        {{ page.props.flash.status }}
                    </div>
                    <InputError :message="page.props.errors.role" class="mb-4" />

                    <div v-if="locked" class="mb-4 flex items-start gap-2 rounded-md border bg-muted px-4 py-3 text-sm text-muted-foreground">
                        <Lock class="mt-0.5 size-4 shrink-0" />
                        Role Chủ shop luôn có mọi quyền (trừ việc chuyên môn của Bác sĩ) và không sửa, xóa được.
                    </div>

                    <form class="grid gap-6" @submit.prevent="submit">
                        <div class="grid max-w-sm gap-2">
                            <Label for="display_name">Tên hiển thị</Label>
                            <Input id="display_name" v-model="form.display_name" maxlength="50" :disabled="locked" required />
                            <p v-if="selected" class="font-mono text-xs text-muted-foreground">Mã: {{ selected.name }}</p>
                            <InputError :message="form.errors.display_name" />
                        </div>

                        <InputError :message="form.errors.permissions" />

                        <fieldset v-for="module in catalog" :key="module.key" class="rounded-lg border">
                            <legend class="px-2 text-sm font-semibold">{{ module.label }}</legend>
                            <ul class="divide-y">
                                <li v-for="p in module.permissions" :key="p.code" class="flex items-start gap-3 px-4 py-2.5">
                                    <input
                                        :id="`perm-${p.code}`"
                                        type="checkbox"
                                        class="mt-1 size-4 accent-teal-600"
                                        :checked="form.permissions.includes(p.code)"
                                        :disabled="locked || !p.assignable"
                                        @change="toggle(p.code, ($event.target as HTMLInputElement).checked)"
                                    />
                                    <label :for="`perm-${p.code}`" class="flex-1 text-sm">
                                        <span class="font-medium">{{ p.label }}</span>
                                        <span
                                            v-if="p.sensitive"
                                            class="ml-2 inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800"
                                        >
                                            <ShieldAlert class="size-3" /> Nhạy cảm
                                        </span>
                                        <span v-if="!p.assignable" class="ml-2 inline-flex items-center gap-1 text-xs text-muted-foreground">
                                            <Lock class="size-3" /> Chỉ Chủ shop
                                        </span>
                                        <span v-if="p.phase !== 'MVP'" class="ml-2 text-xs text-muted-foreground">({{ p.phase }})</span>
                                        <span v-if="p.description" class="block text-xs text-muted-foreground">{{ p.description }}</span>
                                    </label>
                                </li>
                            </ul>
                        </fieldset>

                        <div v-if="!locked" class="flex items-center gap-3">
                            <Button type="submit" :disabled="form.processing">{{ selected ? 'Lưu thay đổi' : 'Tạo role' }}</Button>
                            <Button v-if="selected" type="button" variant="ghost" class="text-red-600" @click="destroy"><Trash2 /> Xóa role</Button>
                        </div>
                    </form>
                </section>
            </div>

            <AppModal v-model:open="confirmOpen" title="Giao quyền nhạy cảm?">
                <p class="text-sm">Nhân viên có role "{{ form.display_name }}" sẽ được:</p>
                <ul class="mt-2 list-disc pl-5 text-sm">
                    <li v-for="p in newlySensitive" :key="p.code">{{ p.label }}</li>
                </ul>
                <p class="mt-2 text-sm text-muted-foreground">Thay đổi này được ghi vào Nhật ký.</p>
                <template #footer>
                    <Button variant="outline" @click="confirmOpen = false">Hủy</Button>
                    <Button @click="confirmSensitive">Xác nhận giao quyền</Button>
                </template>
            </AppModal>
        </SettingsLayout>
    </AppLayout>
</template>
