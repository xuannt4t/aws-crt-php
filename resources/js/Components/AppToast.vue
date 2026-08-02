<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import type { PageProps } from '@/types';

const page = usePage<PageProps>();
const toast = useToast();
let removeSuccessListener: (() => void) | undefined;

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

const showFlash = (flash: PageProps['flash']) => {
    showSuccess(flash.success);
    showError(flash.error);
};

onMounted(() => {
    showFlash(page.props.flash);
    removeSuccessListener = router.on('success', (event) => {
        const nextPage = event.detail.page.props as unknown as PageProps;
        showFlash(nextPage.flash);
    });
});

onUnmounted(() => {
    removeSuccessListener?.();
});
</script>

<template>
    <Toast position="top-right" class="app-toast" />
</template>
