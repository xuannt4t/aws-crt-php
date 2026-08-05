<script setup lang="ts">
import { onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { installFlashToasts } from '@/Support/flashToasts';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const toast = useToast();

// Component này mount lại sau mỗi lần điều hướng (layout đặt trong template chứ
// không phải layout bền), nên việc đọc flash được giao cho một chỗ cài đặt duy
// nhất, chỉ chạy một lần cho cả vòng đời ứng dụng — xem `flashToasts.ts`.
onMounted(() => {
    installFlashToasts((options) => toast.add(options), page.props.flash);
});
</script>

<template>
    <Toast position="top-right" class="app-toast" />
</template>
