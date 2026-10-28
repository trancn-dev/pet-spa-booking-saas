<script setup lang="ts">
import { auditActionLabel, type AuditLogRow, type Paginated } from '@/types/admin';
import { Link } from '@inertiajs/vue3';
import { ScrollText } from 'lucide-vue-next';

defineProps<{
    logs: Paginated<AuditLogRow>;
    showTenant?: boolean;
}>();

const dateTime = new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'medium', timeZone: 'Asia/Ho_Chi_Minh' });

/** Short, readable summary of the `changes` JSON. */
const summary = (changes: Record<string, unknown> | null) =>
    changes === null
        ? ''
        : Object.entries(changes)
              .map(([key, value]) => `${key}: ${Array.isArray(value) ? value.join(' → ') : typeof value === 'object' ? JSON.stringify(value) : String(value)}`)
              .join(' · ');
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table v-if="logs.data.length" class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-[13px] font-medium text-stone-500 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-3">Thời gian</th>
                    <th class="px-4 py-3">Người làm</th>
                    <th v-if="showTenant" class="px-4 py-3">Shop</th>
                    <th class="px-4 py-3">Hành động</th>
                    <th class="px-4 py-3">Chi tiết</th>
                    <th class="px-4 py-3">IP</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="log in logs.data" :key="log.id" class="border-t border-stone-100 align-top dark:border-slate-800">
                    <td class="whitespace-nowrap px-4 py-3 tabular-nums">{{ dateTime.format(new Date(log.created_at)) }}</td>
                    <td class="px-4 py-3">{{ log.admin ?? 'Hệ thống' }}</td>
                    <td v-if="showTenant" class="px-4 py-3">
                        <Link v-if="log.tenant" :href="route('admin.tenants.show', log.tenant.id)" class="hover:underline">{{ log.tenant.name }}</Link>
                        <span v-else class="text-stone-400">—</span>
                    </td>
                    <td class="px-4 py-3 font-medium">{{ auditActionLabel(log.action) }}</td>
                    <td class="max-w-md break-words px-4 py-3 text-xs text-stone-500">{{ summary(log.changes) }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-stone-500">{{ log.ip ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
        <div v-else class="flex flex-col items-center gap-2 px-6 py-16 text-center">
            <ScrollText class="size-10 text-stone-300" />
            <p class="font-medium">Chưa có nhật ký</p>
        </div>
    </div>

    <nav v-if="logs.links.length > 3" class="mt-4 flex flex-wrap gap-1" aria-label="Phân trang">
        <template v-for="link in logs.links" :key="link.label">
            <Link
                v-if="link.url"
                :href="link.url"
                class="rounded-md px-3 py-1 text-sm"
                :class="link.active ? 'bg-teal-600 text-white' : 'hover:bg-stone-100 dark:hover:bg-slate-800'"
                preserve-scroll
                ><span v-html="link.label"
            /></Link>
            <span v-else class="px-3 py-1 text-sm text-stone-400" v-html="link.label" />
        </template>
    </nav>
</template>
