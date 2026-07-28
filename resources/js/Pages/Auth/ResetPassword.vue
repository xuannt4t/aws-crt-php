<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Đặt lại mật khẩu" />

        <div class="mb-8">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-700">Khôi phục tài khoản</p>
            <h1 class="mt-3 font-display text-3xl font-extrabold tracking-[-0.04em] text-ink-950">Tạo mật khẩu mới</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">Chọn mật khẩu mạnh và không dùng lại mật khẩu cũ.</p>
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="w-full"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            <div>
                <InputLabel for="password" value="Mật khẩu mới" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <div>
                <InputLabel for="password_confirmation" value="Xác nhận mật khẩu" />
                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="w-full"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>
            <PrimaryButton class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Đang cập nhật...' : 'Đặt lại mật khẩu' }}
            </PrimaryButton>
        </form>
    </GuestLayout>
</template>
