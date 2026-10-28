<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { type Group, groupLabel } from '@/types/calendar';

/** Service group chip: exam = teal/info, spa = coral (design 3.1). */
defineProps<{ group: Group; label?: string }>();
</script>

<template>
    <Badge :tone="group === 'medical' ? 'primary' : 'coral'">{{ label ?? groupLabel[group] }}</Badge>
</template>
