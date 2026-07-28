<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Đăng nhập" />

        <div class="mb-8">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-700">Chào mừng trở lại</p>
            <h1 class="mt-3 font-display text-3xl font-extrabold tracking-[-0.04em] text-ink-950">Đăng nhập DORMIDA</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">Tiếp tục vào không gian làm việc của doanh nghiệp bạn.</p>
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
                    placeholder="ten@congty.vn"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <InputLabel for="password" value="Mật khẩu" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs font-semibold text-brand-700 transition hover:text-brand-800"
                    >
                        Quên mật khẩu?
                    </Link>
                </div>
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="w-full"
                    placeholder="Nhập mật khẩu"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <label class="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-slate-600">
                <Checkbox v-model:checked="form.remember" name="remember" />
                Duy trì đăng nhập
            </label>

            <PrimaryButton class="w-full" :disabled="form.processing">
                <span v-if="form.processing">Đang đăng nhập...</span>
                <span v-else>Đăng nhập</span>
            </PrimaryButton>
        </form>

        <p class="mt-8 text-center text-xs leading-5 text-slate-400">
            Bằng việc tiếp tục, bạn đồng ý tuân thủ chính sách bảo mật nội bộ của doanh nghiệp.
        </p>
    </GuestLayout>
</template>
