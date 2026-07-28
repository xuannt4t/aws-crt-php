<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Quên mật khẩu" />

        <div class="mb-8">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-700">Khôi phục tài khoản</p>
            <h1 class="mt-3 font-display text-3xl font-extrabold tracking-[-0.04em] text-ink-950">Quên mật khẩu?</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">
                Nhập email đăng nhập. Chúng tôi sẽ gửi liên kết để bạn đặt lại mật khẩu.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
        >
            {{ status }}
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
                    placeholder="ten@congty.vn"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>
            <PrimaryButton class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Đang gửi...' : 'Gửi liên kết khôi phục' }}
            </PrimaryButton>
        </form>

        <p class="mt-7 text-center text-sm text-slate-500">
            Đã nhớ mật khẩu?
            <Link :href="route('login')" class="font-semibold text-brand-700 hover:text-brand-800"
                >Quay lại đăng nhập</Link
            >
        </p>
    </GuestLayout>
</template>
