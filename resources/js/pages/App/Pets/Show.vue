<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PetAlertBadges from '@/components/PetAlertBadges.vue';
import EmptyState from '@/components/pb/EmptyState.vue';
import GroupBadge from '@/components/pb/GroupBadge.vue';
import RingAvatar from '@/components/pb/RingAvatar.vue';
import TabsBar from '@/components/pb/TabsBar.vue';
import Timeline from '@/components/pb/Timeline.vue';
import TimelineItem from '@/components/pb/TimelineItem.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime, formatDay } from '@/lib/format';
import type { PetSummary } from '@/types/pets';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Lock, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type Tab = 'overview' | 'history' | 'medical' | 'vaccinations';

const props = defineProps<{
    pet: PetSummary & { birth_date: string | null; customer: { id: string; name: string; phone: string | null; is_system: boolean } };
    tab: Tab;
    upcoming: { starts_at: string; service: string; branch: string }[];
    vaccinationsDue: { vaccine: string; next_due_on: string; postponed: boolean; overdue: boolean }[];
    history: { date: string; branch: string; group: string; group_label: string; status: string; status_label: string; summary: string | null }[];
    timezone: string | null;
    can: { update: boolean; delete: boolean; viewVisits: boolean; medicalTab: boolean; vaccinationsTab: boolean };
}>();

const tabs = computed(() =>
    [
        { key: 'overview' as Tab, label: 'Tổng quan', show: true },
        { key: 'history' as Tab, label: 'Lịch sử dịch vụ', show: true },
        { key: 'medical' as Tab, label: 'Bệnh án', show: props.can.medicalTab },
        { key: 'vaccinations' as Tab, label: 'Tiêm phòng & tẩy giun', show: props.can.vaccinationsTab },
    ].filter((t) => t.show),
);

const page = usePage();
const deleteError = computed(() => (page.props.errors as Record<string, string | undefined>).pet);

const facts = computed(() =>
    [
        props.pet.species,
        props.pet.breed,
        props.pet.sex_label,
        props.pet.age,
        props.pet.weight_kg ? `${Number(props.pet.weight_kg).toLocaleString('vi-VN')} kg` : null,
        `cỡ ${props.pet.size_label}`,
        props.pet.is_neutered ? 'đã triệt sản' : null,
    ].filter(Boolean),
);

const destroy = () => {
    if (!confirm(`Xóa hồ sơ ${props.pet.name}? Lịch sử khám, hóa đơn vẫn được giữ.`)) return;
    router.delete(route('app.pets.destroy', props.pet.id));
};
</script>

<template>
    <Head :title="pet.name" />

    <AppLayout>
        <div class="flex max-w-5xl flex-col gap-6 p-4 md:p-6">
            <nav class="flex items-center gap-1.5 text-sm text-muted-foreground" aria-label="Điều hướng">
                <Link :href="route('app.customers.index')" class="hover:underline">Khách & Thú cưng</Link>
                <span aria-hidden="true">›</span>
                <Link
                    :href="pet.customer.is_system ? route('app.customers.index', { tab: 'strays' }) : route('app.customers.show', pet.customer.id)"
                    class="hover:underline"
                    >{{ pet.customer.is_system ? 'Thú hoang' : pet.customer.name }}</Link
                >
                <span aria-hidden="true">›</span>
                <span class="text-foreground">{{ pet.name }}</span>
            </nav>

            <header class="flex flex-wrap items-start gap-5">
                <RingAvatar :initials="pet.name.slice(0, 2)" color="hsl(var(--color-accent))" :size="64" />
                <div class="grid min-w-[14rem] flex-1 gap-1">
                    <h1 class="text-[28px] font-semibold leading-9">{{ pet.name }}</h1>
                    <p class="text-sm text-muted-foreground">{{ facts.join(' · ') }}</p>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
                        <span v-if="pet.customer.is_system" class="text-muted-foreground">Thú hoang / vô chủ</span>
                        <span v-else>
                            <span class="text-muted-foreground">Chủ nuôi</span>
                            <Link :href="route('app.customers.show', pet.customer.id)" class="ml-1.5 font-semibold text-primary hover:underline">{{
                                pet.customer.name
                            }}</Link>
                            <span class="font-mono text-xs text-muted-foreground"> · {{ pet.customer.phone }}</span>
                        </span>
                        <PetAlertBadges :alerts="pet.alerts" />
                    </div>
                </div>
                <div class="flex gap-2">
                    <Button v-if="can.update" variant="outline" as-child>
                        <Link :href="route('app.pets.edit', pet.id)"><Pencil class="size-4" /> Sửa</Link>
                    </Button>
                    <Button v-if="can.delete" variant="outline" class="text-danger" @click="destroy"><Trash2 class="size-4" /> Xóa</Button>
                </div>
            </header>
            <InputError :message="deleteError" />

            <TabsBar
                :model-value="tab"
                :items="tabs.map((t) => ({ value: t.key, label: t.label }))"
                label="Hồ sơ thú cưng"
                @update:model-value="(v) => router.get(route('app.pets.show', { pet: pet.id, tab: v }), {}, { preserveScroll: true })"
            />

            <div v-if="tab === 'overview'" class="grid gap-4 md:grid-cols-2">
                <section class="grid content-start gap-3 rounded-[10px] border bg-card p-6">
                    <h2 class="font-semibold">Lịch sắp tới</h2>
                    <ul class="grid gap-2 text-sm">
                        <li v-for="(u, i) in upcoming" :key="i" class="flex justify-between gap-3">
                            <span
                                >{{ u.service }} <span class="text-muted-foreground">· {{ u.branch }}</span></span
                            >
                            <span class="whitespace-nowrap tabular-nums">{{ formatDateTime(u.starts_at, timezone) }}</span>
                        </li>
                        <li v-if="!upcoming.length" class="text-muted-foreground">Không có lịch hẹn sắp tới.</li>
                    </ul>
                </section>
                <section v-if="can.vaccinationsTab" class="grid content-start gap-3 rounded-[10px] border bg-card p-6">
                    <h2 class="font-semibold">Mũi tiêm sắp đến hạn</h2>
                    <ul class="grid gap-2 text-sm">
                        <li v-for="(v, i) in vaccinationsDue" :key="i" class="flex justify-between gap-3">
                            <span>{{ v.vaccine }}<span v-if="v.postponed" class="text-muted-foreground"> · đã hoãn</span></span>
                            <span class="whitespace-nowrap tabular-nums" :class="{ 'font-medium text-danger': v.overdue }">
                                {{ formatDay(v.next_due_on) }}<template v-if="v.overdue"> · quá hạn</template>
                            </span>
                        </li>
                        <li v-if="!vaccinationsDue.length" class="text-muted-foreground">Chưa có mũi nào đến hạn.</li>
                    </ul>
                </section>
            </div>

            <section v-else-if="tab === 'history'" class="grid gap-3">
                <Timeline>
                    <TimelineItem v-for="(h, i) in history" :key="i">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-medium tabular-nums">{{ formatDay(h.date) }}</span>
                            <Badge>{{ h.branch }}</Badge>
                            <GroupBadge :group="h.group" :label="h.group_label" />
                            <span class="text-muted-foreground">{{ h.status_label }}</span>
                        </div>
                        <p v-if="can.viewVisits && h.summary" class="text-sm">{{ h.summary }}</p>
                        <p v-else-if="!can.viewVisits" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Lock class="size-3.5" /> Chi tiết lần đến cần quyền xem Visit.
                        </p>
                    </TimelineItem>
                    <li v-if="!history.length" class="text-sm text-muted-foreground">Bé chưa đến lần nào.</li>
                </Timeline>
            </section>

            <EmptyState v-else>{{ tab === 'medical' ? 'Bệnh án' : 'Sổ tiêm phòng' }} sẽ có trong bản cập nhật tới.</EmptyState>
        </div>
    </AppLayout>
</template>
