<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value?.focus());
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <section class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <header class="max-w-2xl">
            <h2 class="font-display text-base font-bold text-red-800">Xoá tài khoản</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Tài khoản và dữ liệu cá nhân sẽ bị xoá. Hành động này cần xác nhận bằng mật khẩu.
            </p>
        </header>

        <DangerButton class="shrink-0" @click="confirmUserDeletion">Xoá tài khoản</DangerButton>

        <Modal :show="confirmingUserDeletion" max-width="md" @close="closeModal">
            <div class="p-6 sm:p-7">
                <h2 class="font-display text-lg font-bold text-ink-950">Xác nhận xoá tài khoản?</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Nhập mật khẩu hiện tại để xác nhận. Dữ liệu đã xoá có thể không khôi phục được.
                </p>

                <div class="mt-6">
                    <InputLabel for="password" value="Mật khẩu hiện tại" />
                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="w-full"
                        placeholder="Nhập mật khẩu"
                        @keyup.enter="deleteUser"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-7 flex justify-end gap-2">
                    <SecondaryButton @click="closeModal">Huỷ</SecondaryButton>
                    <DangerButton :disabled="form.processing" @click="deleteUser">
                        {{ form.processing ? 'Đang xoá...' : 'Xoá tài khoản' }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
