<script setup lang="ts">
import type { PetAlert } from '@/types/pets';
import { AlertTriangle, HeartPulse, ShieldAlert } from 'lucide-vue-next';

/*
| Safety badges (design 10, rule 6): allergies, chronic conditions and behaviour ("cắn người",
| "sợ máy sấy") show wherever a pet is shown, to everyone who can see the pet.
*/
withDefaults(defineProps<{ alerts: PetAlert[]; compact?: boolean }>(), { compact: false });

const icon = { allergies: AlertTriangle, chronic_conditions: HeartPulse, behavior_notes: ShieldAlert } as const;
const tone = {
    allergies: 'bg-danger/10 text-danger border-danger/30',
    chronic_conditions: 'bg-warning/10 text-warning border-warning/30',
    behavior_notes: 'bg-coral/10 text-coral border-coral/30',
} as const;
</script>

<template>
    <ul v-if="alerts.length" class="flex flex-wrap gap-1.5" aria-label="Cảnh báo an toàn">
        <li
            v-for="a in alerts"
            :key="a.kind"
            class="inline-flex max-w-full items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium"
            :class="tone[a.kind]"
            :title="`${a.label}: ${a.text}`"
        >
            <component :is="icon[a.kind]" class="size-3.5 shrink-0" />
            <span v-if="compact" class="truncate">{{ a.label }}</span>
            <span v-else class="truncate">{{ a.label }}: {{ a.text }}</span>
        </li>
    </ul>
</template>
