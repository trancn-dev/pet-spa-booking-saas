<script setup lang="ts">
import { AlertCircle, CheckCircle2, Info } from 'lucide-vue-next';
import { computed } from 'vue';

/*
| Design K0 "Banner": plan quota, expiry, suspended shop, impersonation, errors. A pastel strip with an
| icon, text and an optional action (`action` slot). `danger` / `warning` are announced as alerts.
*/
const props = withDefaults(defineProps<{ tone?: 'info' | 'primary' | 'success' | 'warning' | 'danger'; icon?: boolean }>(), {
    tone: 'info',
    icon: true,
});

const classes = {
    info: 'bg-sky-100 text-sky-900 dark:bg-sky-500/15 dark:text-sky-200',
    primary: 'bg-primary-soft text-teal-900 dark:text-teal-200',
    success: 'bg-green-100 text-green-900 dark:bg-green-500/15 dark:text-green-200',
    warning: 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200',
    danger: 'bg-red-100 text-red-900 dark:bg-red-500/15 dark:text-red-200',
} as const;
const role = computed(() => (props.tone === 'danger' || props.tone === 'warning' ? 'alert' : 'status'));
const Icon = computed(() => (props.tone === 'success' ? CheckCircle2 : props.tone === 'info' || props.tone === 'primary' ? Info : AlertCircle));
</script>

<template>
    <div :role="role" class="flex items-center gap-2.5 rounded-[10px] px-4 py-3 text-sm" :class="classes[tone]">
        <component :is="Icon" v-if="icon" class="size-[18px] shrink-0" aria-hidden="true" />
        <div class="min-w-0 flex-1"><slot /></div>
        <div v-if="$slots.action" class="shrink-0 font-semibold"><slot name="action" /></div>
    </div>
</template>
