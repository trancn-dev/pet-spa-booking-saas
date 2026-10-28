<script setup lang="ts">
import AppModal from '@/components/pb/AppModal.vue';
import { Button } from '@/components/ui/button';

/** "Hủy lịch hẹn?" pattern: one question, a safe button ("Giữ lịch") and the action. `danger` paints the action red. */
withDefaults(
    defineProps<{ title: string; description?: string; confirmLabel: string; cancelLabel?: string; danger?: boolean; loading?: boolean }>(),
    { cancelLabel: 'Giữ lại' },
);
const open = defineModel<boolean>('open', { required: true });
defineEmits<{ confirm: [] }>();
</script>

<template>
    <AppModal v-model:open="open" :title="title" :description="description">
        <slot />
        <template #footer>
            <Button variant="outline" @click="open = false">{{ cancelLabel }}</Button>
            <Button :variant="danger ? 'destructive' : 'default'" :loading="loading" @click="$emit('confirm')">{{ confirmLabel }}</Button>
        </template>
    </AppModal>
</template>
