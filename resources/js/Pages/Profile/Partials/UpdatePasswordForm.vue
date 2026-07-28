<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="font-display text-base font-bold text-ink-950">Đổi mật khẩu</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Dùng mật khẩu dài, khó đoán và không trùng với dịch vụ khác.
            </p>
        </header>

        <form class="mt-6 space-y-5" @submit.prevent="updatePassword">
            <div>
                <InputLabel for="current_password" value="Mật khẩu hiện tại" />
                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="w-full"
                    autocomplete="current-password"
                />
                <InputError :message="form.errors.current_password" class="mt-2" />
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <InputLabel for="password" value="Mật khẩu mới" />
                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="w-full"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>
                <div>
                    <InputLabel for="password_confirmation" value="Xác nhận mật khẩu" />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="w-full"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password_confirmation" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-4 pt-1">
                <PrimaryButton :disabled="form.processing">
                    {{ form.processing ? 'Đang cập nhật...' : 'Đổi mật khẩu' }}
                </PrimaryButton>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm font-semibold text-emerald-600">Đã cập nhật.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
