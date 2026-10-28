<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BackLink from '@/components/pb/BackLink.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    customer: { id: string; name: string; phone: string | null; email: string | null; address: string | null; notes: string | null } | null;
}>();

const form = useForm({
    name: props.customer?.name ?? '',
    phone: props.customer?.phone ?? '',
    email: props.customer?.email ?? '',
    address: props.customer?.address ?? '',
    notes: props.customer?.notes ?? '',
});

const submit = () => {
    if (props.customer) form.put(route('app.customers.update', props.customer.id));
    else form.post(route('app.customers.store'));
};
const back = props.customer ? route('app.customers.show', props.customer.id) : route('app.customers.index');
</script>

<template>
    <Head :title="customer ? customer.name : 'Thêm khách'" />

    <AppLayout>
        <form class="flex max-w-2xl flex-col gap-6 p-4 md:p-6" @submit.prevent="submit">
            <PageHeader :title="customer ? 'Sửa hồ sơ khách' : 'Thêm khách'" subtitle="SĐT là mã khách, dùng chung mọi cơ sở. Không cần CCCD.">
                <template #before
                    ><BackLink :href="back">{{ customer ? customer.name : 'Khách & Thú cưng' }}</BackLink></template
                >
            </PageHeader>

            <section class="grid gap-4 rounded-[10px] border bg-card p-6 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Họ tên</Label>
                    <Input id="name" v-model="form.name" required maxlength="191" autocomplete="off" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="phone">Số điện thoại</Label>
                    <Input id="phone" v-model="form.phone" type="tel" required inputmode="tel" placeholder="0901 234 567" autocomplete="off" />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">Email (tùy chọn)</Label>
                    <Input id="email" v-model="form.email" type="email" maxlength="191" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="address">Địa chỉ (tùy chọn)</Label>
                    <Input id="address" v-model="form.address" maxlength="255" />
                    <InputError :message="form.errors.address" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="notes">Ghi chú</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        maxlength="2000"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </section>

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                    {{ customer ? 'Lưu' : 'Thêm khách' }}
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="back">Hủy</Link>
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
