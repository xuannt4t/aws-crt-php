<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Xác nhận mật khẩu" />

        <div class="mb-8">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-700">Khu vực bảo mật</p>
            <h1 class="mt-3 font-display text-3xl font-extrabold tracking-[-0.04em] text-ink-950">Xác nhận mật khẩu</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">
                Vui lòng xác nhận mật khẩu trước khi tiếp tục thao tác nhạy cảm.
            </p>
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <InputLabel for="password" value="Mật khẩu" />
                <TextInput
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>
            <PrimaryButton class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Đang xác nhận...' : 'Xác nhận' }}
            </PrimaryButton>
        </form>
    </GuestLayout>
</template>
