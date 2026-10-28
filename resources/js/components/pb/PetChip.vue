<script setup lang="ts">
import PetAlertBadges from '@/components/PetAlertBadges.vue';
import type { PetAlert } from '@/types/pets';
import { computed } from 'vue';

/*
| Design K0 "Pet chip + badge an toàn": initials avatar, name, "Chó · Poodle · cỡ S · 5,2 kg" and the safety badges, which
| always travel with the pet (design 10, rule 6). `selected` / `selectable` for pickers.
*/
const props = defineProps<{ name: string; detail?: string | null; alerts?: PetAlert[]; selected?: boolean; selectable?: boolean }>();
defineEmits<{ select: [] }>();
const initials = computed(() => props.name.trim().slice(0, 2));
</script>

<template>
    <component
        :is="selectable ? 'button' : 'div'"
        :type="selectable ? 'button' : undefined"
        class="flex w-full items-center gap-3 rounded-xl border p-3 text-left"
        :class="[
            selected ? 'border-primary bg-primary-soft' : 'bg-card',
            selectable ? 'transition-colors hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary' : '',
        ]"
        :aria-pressed="selectable ? !!selected : undefined"
        @click="selectable && $emit('select')"
    >
        <span
            class="grid size-9 shrink-0 place-items-center rounded-full bg-primary-soft text-xs font-semibold text-teal-900 dark:text-teal-200"
            aria-hidden="true"
            >{{ initials }}</span
        >
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium">{{ name }}</span>
            <span v-if="detail" class="block truncate text-xs text-muted-foreground">{{ detail }}</span>
        </span>
        <PetAlertBadges v-if="alerts?.length" :alerts="alerts" compact class="shrink-0 justify-end" />
    </component>
</template>
