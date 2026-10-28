<script setup lang="ts">
import { Lock } from 'lucide-vue-next';

/** Design 10 rule 5: a section the viewer may not open is shown locked with the reason, never silently missing. */
defineProps<{ reason?: string }>();
</script>

<template>
    <div class="flex items-center gap-2 rounded-[10px] border border-dashed bg-surface-muted px-4 py-3 text-sm text-muted-foreground">
        <Lock class="size-4 shrink-0" aria-hidden="true" />
        <span
            ><slot>{{ reason ?? 'Bạn không có quyền xem mục này.' }}</slot></span
        >
    </div>
</template>
