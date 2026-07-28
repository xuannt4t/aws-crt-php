<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const toast = useToast();

const showSuccess = (message: string | null | undefined) => {
    if (!message) {
        return;
    }

    toast.add({
        severity: 'success',
        summary: 'Thành công',
        detail: message,
        life: 4000,
    });
};

const showError = (message: string | null | undefined) => {
    if (!message) {
        return;
    }

    toast.add({
        severity: 'error',
        summary: 'Không thể thực hiện',
        detail: message,
        life: 6000,
    });
};

onMounted(() => {
    showSuccess(page.props.flash.success);
    showError(page.props.flash.error);
});

watch(
    () => page.props.flash.success,
    (message) => showSuccess(message),
);

watch(
    () => page.props.flash.error,
    (message) => showError(message),
);
</script>

<template>
    <Toast position="top-right" class="app-toast" />
</template>
