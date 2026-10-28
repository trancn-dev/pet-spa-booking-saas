<script setup lang="ts">
import CalendarBlock from '@/components/appointments/CalendarBlock.vue';
import CapacityBar from '@/components/appointments/CapacityBar.vue';
import AppDrawer from '@/components/pb/AppDrawer.vue';
import AppModal from '@/components/pb/AppModal.vue';
import AttentionCard from '@/components/pb/AttentionCard.vue';
import Banner from '@/components/pb/Banner.vue';
import Chip from '@/components/pb/Chip.vue';
import ConfirmModal from '@/components/pb/ConfirmModal.vue';
import DataTable from '@/components/pb/DataTable.vue';
import EmptyState from '@/components/pb/EmptyState.vue';
import FormField from '@/components/pb/FormField.vue';
import GroupBadge from '@/components/pb/GroupBadge.vue';
import KeyValue from '@/components/pb/KeyValue.vue';
import KpiCard from '@/components/pb/KpiCard.vue';
import LockedNote from '@/components/pb/LockedNote.vue';
import OtpInput from '@/components/pb/OtpInput.vue';
import PageHeader from '@/components/pb/PageHeader.vue';
import Pagination from '@/components/pb/Pagination.vue';
import PetChip from '@/components/pb/PetChip.vue';
import ProgressBar from '@/components/pb/ProgressBar.vue';
import QueueNumber from '@/components/pb/QueueNumber.vue';
import RingAvatar from '@/components/pb/RingAvatar.vue';
import SearchField from '@/components/pb/SearchField.vue';
import Segmented from '@/components/pb/Segmented.vue';
import SlotPicker, { type SlotOption } from '@/components/pb/SlotPicker.vue';
import StatusBadge from '@/components/pb/StatusBadge.vue';
import Stepper from '@/components/pb/Stepper.vue';
import TabsBar from '@/components/pb/TabsBar.vue';
import Tag from '@/components/pb/Tag.vue';
import Timeline from '@/components/pb/Timeline.vue';
import TimelineItem from '@/components/pb/TimelineItem.vue';
import TwoLine from '@/components/pb/TwoLine.vue';
import BottomSheet from '@/components/site/BottomSheet.vue';
import ImagePlaceholder from '@/components/site/ImagePlaceholder.vue';
import SiteBottomNav from '@/components/site/SiteBottomNav.vue';
import SiteCard from '@/components/site/SiteCard.vue';
import SiteChip from '@/components/site/SiteChip.vue';
import SiteField from '@/components/site/SiteField.vue';
import SiteFooter from '@/components/site/SiteFooter.vue';
import SiteHeader from '@/components/site/SiteHeader.vue';
import SiteStepHeader from '@/components/site/SiteStepHeader.vue';
import Toaster from '@/components/Toaster.vue';
import { Badge, type BadgeTone } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useToast } from '@/composables/useToast';
import type { CalendarBlock as BlockData, CapacityCell } from '@/types/calendar';
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';

/*
| Component gallery (design K0 / K2). Every shared component, with the states the mockups show.
| Route: /ui-kit (local, demo and testing only).
*/
const { show } = useToast();
const tab = ref('overview');
const view = ref('staff');
const time = ref<string | null>('14:00');
const slots: SlotOption[] = [
    { time: '13:45', caption: 'còn 2 chỗ' },
    { time: '14:00', caption: 'còn 1 chỗ' },
    { time: '14:15', caption: 'còn 3 chỗ' },
    { time: '14:30', caption: 'hết chỗ', disabled: true },
    { time: '14:45', caption: 'đủ người', over: true },
];
const tones: BadgeTone[] = ['neutral', 'primary', 'info', 'coral', 'success', 'warning', 'danger', 'violet', 'dark'];
const modal = ref(false);
const confirm = ref(false);
const drawer = ref(false);
const sheet = ref(false);
const otp = ref('');
const search = ref('');
const text = ref('');
const rows = [
    { pet: 'Sushi', owner: 'Đặng Mỹ Linh', vaccine: 'Vaccine 7 bệnh · mũi 3', due: '06/11/2026', status: 'soon' },
    { pet: 'Tôm', owner: 'Bùi Gia Huy', vaccine: 'Tẩy giun', due: '04/10/2026', status: 'late' },
    { pet: 'Mochi', owner: 'Nguyễn Thị Lan', vaccine: 'Vaccine 7 bệnh · nhắc lại', due: '20/03/2027', status: 'ok' },
];
const cell: CapacityCell = { time: '14:00', medical: { taken: 2, on_duty: 3 }, grooming: { taken: 1, on_duty: 2 } };
const over: CapacityCell = { time: '15:00', medical: { taken: 3, on_duty: 3 }, grooming: { taken: 3, on_duty: 2 } };
const block: BlockData = {
    key: 'k',
    appointment: 'a',
    group: 'grooming',
    staff_id: 1,
    start: '14:00',
    end: '15:30',
    service_end: '15:15',
    status: 'confirmed',
    line_status: 'booked',
    source: 'online',
    customer: 'Nguyễn Thị Lan',
    preferred: null,
    no_reminders: false,
    linked: true,
    pets: [
        { name: 'Mochi, Bơ', service: 'Tắm + sấy', status: 'booked', alerts: [{ kind: 'behavior_notes', label: 'Sợ máy sấy', text: 'Sợ máy sấy' }] },
    ],
};
const alerts = [{ kind: 'allergies' as const, label: 'Dị ứng', text: 'đậu gà' }];
</script>

<template>
    <Head title="UI kit" />
    <div class="mx-auto max-w-6xl space-y-10 p-6">
        <PageHeader title="UI kit" subtitle="PawBook · thành phần dùng chung theo design K0 / K2">
            <template #actions
                ><Button
                    variant="outline"
                    size="sm"
                    @click="show('Đã hủy lịch của Mochi, Bơ', 'success', { action: { label: 'Hoàn tác', run: () => {} } })"
                    >Thử toast</Button
                ></template
            >
        </PageHeader>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Button</h2>
            <div class="flex flex-wrap items-center gap-3">
                <Button><Plus /> Xác nhận đặt lịch</Button>
                <Button variant="outline">Dời lịch</Button>
                <Button variant="ghost">Bỏ qua</Button>
                <Button variant="destructive">Hủy lịch</Button>
                <Button variant="ghostDanger">Xóa</Button>
                <Button variant="link">Nhắc lại sau</Button>
                <Button variant="accent" size="touch">CTA Site (accent)</Button>
                <Button loading>Đang lưu</Button>
                <Button disabled disabled-reason="Mochi đang làm spa">Hoàn thành &amp; thu tiền</Button>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Input · FormField</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <FormField label="Số điện thoại" hint="Dùng để gửi nhắc lịch" required v-slot="{ id }"
                    ><Input :id="id" v-model="text" placeholder="903 123 456"
                /></FormField>
                <FormField label="Họ tên" error="Vui lòng nhập họ tên" v-slot="{ id, invalid }"
                    ><Input :id="id" :aria-invalid="invalid" class="border-danger"
                /></FormField>
                <SearchField v-model="search" placeholder="Tìm khách, SĐT, tên pet" />
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Badge · Tag · Avatar</h2>
            <div class="flex flex-wrap items-center gap-2">
                <Badge v-for="t in tones" :key="t" :tone="t" dot>{{ t }}</Badge>
                <StatusBadge status="confirmed" /><StatusBadge status="checked_in" /><StatusBadge status="cancelled" /><StatusBadge
                    status="no_show"
                />
                <GroupBadge group="medical" /><GroupBadge group="grooming" /><Tag>Quận 3</Tag>
            </div>
            <div class="flex items-center gap-3">
                <RingAvatar initials="KH" color="#0D9488" /><RingAvatar initials="LH" color="#8B5CF6" /><RingAvatar
                    initials="NA"
                    color="#F97362"
                    active
                /><RingAvatar initials="TT" color="#64748B" :size="28" />
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Banner</h2>
            <Banner tone="warning"
                >Gói Pro hết hạn ngày 24/10/2026, liên hệ PawBook để gia hạn.<template #action
                    ><a href="#" class="underline">Gia hạn</a></template
                ></Banner
            >
            <Banner tone="danger">Đã quá hạn 3 ngày. Tài khoản sẽ tạm khóa sau 4 ngày nữa.</Banner>
            <Banner tone="info">Lịch tháng 10: 412/500. Còn 88 lịch trong gói.</Banner>
            <Banner tone="success">Đã lưu thay đổi.</Banner>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Tabs · Segmented · Stepper · Progress</h2>
            <TabsBar
                v-model="tab"
                :items="[
                    { value: 'overview', label: 'Tổng quan' },
                    { value: 'history', label: 'Lịch sử dịch vụ' },
                    { value: 'medical', label: 'Bệnh án', locked: true },
                    { value: 'vacc', label: 'Tiêm phòng', count: 4 },
                ]"
            />
            <Segmented
                v-model="view"
                :options="[
                    { value: 'day', label: 'Ngày' },
                    { value: 'week', label: 'Tuần' },
                    { value: 'staff', label: 'Theo nhân viên' },
                ]"
            />
            <Stepper :steps="5" :current="3" />
            <ProgressBar :value="3" :max="5" label="Tiến độ" />
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Table</h2>
            <DataTable
                :columns="[
                    { key: 'pet', label: 'Thú cưng', width: '1fr' },
                    { key: 'owner', label: 'Khách', width: '1fr' },
                    { key: 'vaccine', label: 'Vaccine', width: '1.5fr' },
                    { key: 'due', label: 'Đến hạn', width: '120px' },
                    { key: 'status', label: 'Trạng thái', width: '140px' },
                ]"
                :rows="rows"
                row-key="pet"
            >
                <template #cell-pet="{ row }"><TwoLine :title="row.pet" caption="Chó · Poodle" /></template>
                <template #cell-status="{ row }"
                    ><Badge :tone="row.status === 'late' ? 'danger' : row.status === 'soon' ? 'warning' : 'success'" dot>{{
                        row.status === 'late' ? 'Quá hạn' : row.status === 'soon' ? 'Sắp đến hạn' : 'Đúng hạn'
                    }}</Badge></template
                >
            </DataTable>
            <DataTable
                :columns="[
                    { key: 'a', label: 'Cột A' },
                    { key: 'b', label: 'Cột B' },
                ]"
                :rows="[]"
                ><template #empty><EmptyState compact>Chưa có dữ liệu.</EmptyState></template></DataTable
            >
            <DataTable
                :columns="[
                    { key: 'a', label: 'Đang tải' },
                    { key: 'b', label: 'Skeleton' },
                ]"
                :rows="[]"
                loading
            />
            <Pagination
                :links="[
                    { url: null, label: '&laquo; Previous', active: false },
                    { url: '#', label: '1', active: true },
                    { url: '#', label: '2', active: false },
                    { url: '#', label: 'Next &raquo;', active: false },
                ]"
            />
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <KpiCard label="Lịch hôm nay" :value="9" max="14" delta="+2 so với T4" delta-tone="up" :series="[3, 5, 4, 7, 6, 9]" />
            <AttentionCard title="Cần xử lý">
                Hóa đơn chưa hoàn thành từ ngày trước: 2<template #action><a href="#">Mở Thu ngân</a></template></AttentionCard
            >
            <div class="space-y-2"><QueueNumber :no="42" /><QueueNumber :no="7" emergency /></div>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Pet chip · Slot picker · Calendar</h2>
            <div class="grid gap-3 md:grid-cols-2">
                <PetChip name="Mochi" detail="Chó · Poodle · cỡ S · 5,2 kg" :alerts="alerts" />
                <PetChip
                    name="Lu"
                    detail="Chó · Golden Retriever · cỡ L · 32 kg"
                    :alerts="[{ kind: 'behavior_notes', label: 'Sợ máy sấy', text: '' }]"
                    selectable
                    selected
                />
            </div>
            <SlotPicker v-model="time" :slots="slots" class="max-w-md" />
            <div class="flex items-start gap-6">
                <div class="w-28"><CapacityBar :cell="cell" /><CapacityBar :cell="over" /></div>
                <div class="relative h-24 w-64"><CalendarBlock :block="block" color="#F97362" :buffer-px="18" class="inset-x-0 top-0 h-24" /></div>
            </div>
            <Chip>Chip</Chip> <Chip selected>Đã chọn</Chip> <Chip dashed>+ Bé mới</Chip>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Timeline · Empty · Locked · Skeleton</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <Timeline>
                    <TimelineItem caption="16/10/2026 · CN Quận 3" title="Khám tổng quát · BS. Lê Thu Hà" />
                    <TimelineItem caption="02/09/2026 · CN Quận 3" title="Tiêm vaccine 7 bệnh, mũi 2" />
                </Timeline>
                <div class="space-y-3">
                    <EmptyState title="Chưa có lịch hẹn nào hôm nay"
                        ><template #icon><Plus /></template><template #action><Button size="sm">Đặt lịch</Button></template></EmptyState
                    >
                    <LockedNote>Nội dung bệnh án chỉ bác sĩ xem.</LockedNote>
                    <div class="space-y-2"><Skeleton class="h-4 w-2/3" /><Skeleton class="h-4 w-full" /><Skeleton class="h-4 w-1/2" /></div>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Modal · Drawer · Bottom sheet</h2>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" @click="modal = true">Modal</Button>
                <Button variant="outline" @click="confirm = true">Xác nhận hủy</Button>
                <Button variant="outline" @click="drawer = true">Drawer phải</Button>
                <Button variant="outline" @click="sheet = true">Bottom sheet</Button>
            </div>
            <AppModal v-model:open="modal" title="Dời lịch hẹn" description="Chọn ngày giờ mới.">
                <KeyValue label="Khách">Nguyễn Thị Lan</KeyValue>
                <template #footer
                    ><Button variant="outline" @click="modal = false">Đóng</Button><Button @click="modal = false">Dời lịch</Button></template
                >
            </AppModal>
            <ConfirmModal
                v-model:open="confirm"
                title="Hủy lịch hẹn?"
                description="Chọn lý do. Shop sẽ báo khách qua Zalo."
                confirm-label="Hủy lịch"
                cancel-label="Giữ lịch"
                danger
                @confirm="confirm = false"
            />
            <AppDrawer v-model:open="drawer" title="Chi tiết lịch hẹn" description="29/10/2026 · 14:00–15:30"
                ><div class="p-6 text-sm">Nội dung drawer cuộn được.</div>
                <template #footer><Button class="w-full">Check-in</Button></template></AppDrawer
            >
            <BottomSheet v-model:open="sheet" title="Hủy lịch hẹn" description="Vui lòng cho biết lý do."
                ><template #actions
                    ><Button variant="accent" size="touch">Xác nhận hủy</Button
                    ><Button variant="outline" size="touch" @click="sheet = false">Giữ lịch</Button></template
                ></BottomSheet
            >
        </section>

        <section class="space-y-3">
            <h2 class="text-xl font-semibold">Site (mobile, 390px)</h2>
            <div class="flex flex-wrap gap-6">
                <div class="flex h-[640px] w-[390px] flex-col overflow-hidden rounded-[32px] border bg-background">
                    <SiteHeader name="Paw House Spa & Vet"
                        ><template #actions><Button variant="accent" size="sm">Đặt lịch</Button></template></SiteHeader
                    >
                    <div class="flex-1 space-y-4 overflow-y-auto p-4">
                        <ImagePlaceholder :height="120" label="ảnh cơ sở" />
                        <div class="flex gap-2 overflow-x-auto">
                            <SiteChip selected>T5 29/10</SiteChip><SiteChip>T6 30/10</SiteChip><SiteChip disabled>T7 31/10</SiteChip>
                        </div>
                        <SiteCard><KeyValue label="Bé">Mochi, Bơ</KeyValue><KeyValue label="Giờ">14:00 · T5 29/10</KeyValue></SiteCard>
                        <SiteField label="Số điện thoại" prefix="+84" inputmode="tel" v-model="text" hint="Mã OTP sẽ gửi qua SMS" />
                        <OtpInput v-model="otp" />
                        <SiteFooter />
                    </div>
                    <SiteBottomNav
                        active="home"
                        :items="[
                            { key: 'home', label: 'Trang chủ', href: '#' },
                            { key: 'services', label: 'Dịch vụ', href: '#' },
                            { key: 'appointments', label: 'Lịch hẹn', href: '#' },
                            { key: 'account', label: 'Tài khoản', href: '#' },
                        ]"
                    />
                </div>
                <div class="h-[160px] w-[390px] overflow-hidden rounded-[32px] border bg-background">
                    <SiteStepHeader title="Chọn giờ" step="Bước 4/5" />
                </div>
            </div>
        </section>
        <Toaster />
    </div>
</template>
