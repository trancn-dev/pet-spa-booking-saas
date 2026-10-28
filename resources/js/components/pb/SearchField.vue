<script setup lang="ts">
import { Search } from 'lucide-vue-next';

/** Search box with the magnifier (toolbars, global search topbar). Forwards `placeholder`, `autofocus`… to the input. */
const model = defineModel<string>({ default: '' });
defineProps<{ label?: string }>();
defineOptions({ inheritAttrs: false });
</script>

<template>
    <div class="relative h-10">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        <input
            v-model="model"
            type="search"
            :aria-label="label ?? 'Tìm kiếm'"
            class="h-10 w-full rounded-[10px] border border-input bg-surface-muted pl-9 pr-3 text-sm placeholder:text-muted-foreground focus-visible:bg-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            v-bind="$attrs"
        />
    </div>
</template>
