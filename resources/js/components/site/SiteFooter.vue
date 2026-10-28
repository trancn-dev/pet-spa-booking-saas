<script setup lang="ts">
/** Design Site footer: "Điều khoản sử dụng · Đặt lịch bởi PawBook". */
defineProps<{ termsHref?: string }>();
</script>

<template>
    <footer class="mt-auto flex-none px-4 pb-4 pt-6 text-center text-xs leading-4 text-muted-foreground">
        <a :href="termsHref ?? '#'" class="text-primary underline">Điều khoản sử dụng</a> · Đặt lịch bởi PawBook
    </footer>
</template>
