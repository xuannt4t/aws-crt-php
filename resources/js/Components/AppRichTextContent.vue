<script setup lang="ts">
defineProps<{
    html: string;
}>();
</script>

<template>
    <!-- Nội dung đã được lọc bằng allow-list ở backend trước khi truyền qua Inertia. -->
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div class="task-rich-content" v-html="html" />
</template>
