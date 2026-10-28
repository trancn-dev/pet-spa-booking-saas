<script setup lang="ts">
import { LifeBuoy } from 'lucide-vue-next';

export interface SupportSession {
    id: number;
    admin: string | null;
    target: string | null;
    reason: string;
    started_at: string | null;
    ended_at: string | null;
    expires_at: string;
    ip: string | null;
    state: 'unused' | 'open' | 'ended' | 'expired';
}

const props = defineProps<{
    sessions: SupportSession[];
    timezone: string;
}>();

const states: Record<SupportSession['state'], string> = {
    unused: 'Chưa dùng link',
    open: 'Đang diễn ra',
    ended: 'Đã thoát',
    expired: 'Hết 30 phút',
};
const dateTime = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short', timeZone: props.timezone }).format(new Date(iso)) : '—';
</script>

<template>
    <div class="overflow-x-auto rounded-[10px] border bg-card">
        <table v-if="sessions.length" class="w-full text-sm">
            <thead class="bg-muted text-left text-[13px] font-medium text-muted-foreground">
                <tr>
                    <th class="px-4 py-3">Bắt đầu</th>
                    <th class="px-4 py-3">Kết thúc</th>
                    <th class="px-4 py-3">Nhân viên PawBook</th>
                    <th class="px-4 py-3">Đăng nhập như</th>
                    <th class="px-4 py-3">Lý do</th>
                    <th class="px-4 py-3">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="s in sessions" :key="s.id" class="border-t align-top">
                    <td class="whitespace-nowrap px-4 py-3 tabular-nums">{{ dateTime(s.started_at) }}</td>
                    <td class="whitespace-nowrap px-4 py-3 tabular-nums">{{ dateTime(s.ended_at ?? (s.state === 'expired' ? s.expires_at : null)) }}</td>
                    <td class="px-4 py-3">{{ s.admin ?? '—' }}</td>
                    <td class="px-4 py-3">{{ s.target ?? '—' }}</td>
                    <td class="max-w-sm px-4 py-3">{{ s.reason }}</td>
                    <td class="px-4 py-3" :class="{ 'font-medium text-danger': s.state === 'open' }">{{ states[s.state] }}</td>
                </tr>
            </tbody>
        </table>
        <div v-else class="flex flex-col items-center gap-2 px-6 py-16 text-center">
            <LifeBuoy class="size-10 text-subtle" />
            <p class="font-medium">Chưa có lần truy cập hỗ trợ nào</p>
        </div>
    </div>
</template>
