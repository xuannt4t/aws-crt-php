<script setup lang="ts">
import { ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Modal.vue';
import { router } from '@inertiajs/vue3';

defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const isLoggingOut = ref(false);

const logout = () => {
    router.post(
        route('logout'),
        {},
        {
            onStart: () => {
                isLoggingOut.value = true;
            },
            onFinish: () => {
                isLoggingOut.value = false;
                emit('close');
            },
        },
    );
};
</script>

<template>
    <Modal :show="show" max-width="md" :closeable="!isLoggingOut" @close="$emit('close')">
        <div class="p-6 sm:p-7">
            <div class="flex size-11 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                <AppIcon name="logout" class="size-5" />
            </div>
            <h2 class="mt-5 font-display text-lg font-bold tracking-[-0.02em] text-ink-950">Xác nhận đăng xuất</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">
                Bạn có chắc muốn kết thúc phiên làm việc hiện tại không?
            </p>
            <div class="mt-7 flex justify-end gap-2">
                <button type="button" class="app-button-secondary" :disabled="isLoggingOut" @click="$emit('close')">
                    Ở lại
                </button>
                <button type="button" class="app-button-danger" :disabled="isLoggingOut" @click="logout">
                    <AppIcon name="logout" class="size-4" />
                    {{ isLoggingOut ? 'Đang đăng xuất...' : 'Đăng xuất' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
