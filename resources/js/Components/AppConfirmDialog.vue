<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

withDefaults(
    defineProps<{
        show: boolean;
        title: string;
        description: string;
        confirmLabel?: string;
        processing?: boolean;
        icon?: 'trash' | 'arrow-left';
        tone?: 'danger' | 'primary';
    }>(),
    {
        confirmLabel: 'Xác nhận',
        processing: false,
        icon: 'trash',
        tone: 'danger',
    },
);

defineEmits<{
    cancel: [];
    confirm: [];
}>();
</script>

<template>
    <Modal :show="show" max-width="md" @close="$emit('cancel')">
        <div class="p-6 sm:p-7">
            <div
                class="flex size-11 items-center justify-center rounded-xl"
                :class="tone === 'danger' ? 'bg-red-50 text-red-600' : 'bg-brand-50 text-brand-700'"
            >
                <AppIcon :name="icon" class="size-5" />
            </div>
            <h2 class="mt-5 font-display text-lg font-bold tracking-[-0.02em] text-ink-950">{{ title }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ description }}</p>
            <div class="mt-7 flex justify-end gap-2">
                <SecondaryButton @click="$emit('cancel')">Huỷ</SecondaryButton>
                <DangerButton v-if="tone === 'danger'" :disabled="processing" @click="$emit('confirm')">
                    {{ confirmLabel }}
                </DangerButton>
                <PrimaryButton v-else :disabled="processing" @click="$emit('confirm')">
                    {{ confirmLabel }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
