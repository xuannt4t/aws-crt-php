<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const user = usePage<PageProps>().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header>
            <h2 class="font-display text-base font-bold text-ink-950">Thông tin tài khoản</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">Cập nhật họ tên và địa chỉ email đăng nhập.</p>
        </header>

        <form class="mt-6 space-y-5" @submit.prevent="form.patch(route('profile.update'))">
            <div>
                <InputLabel for="name" value="Họ và tên" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="w-full"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="w-full"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div
                v-if="mustVerifyEmail && user.email_verified_at === null"
                class="rounded-xl border border-amber-200 bg-amber-50 p-4"
            >
                <p class="text-sm leading-6 text-amber-800">
                    Email của bạn chưa được xác minh.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-semibold underline underline-offset-2"
                    >
                        Gửi lại email xác minh
                    </Link>
                </p>
                <p v-show="status === 'verification-link-sent'" class="mt-2 text-sm font-semibold text-emerald-700">
                    Email xác minh mới đã được gửi.
                </p>
            </div>

            <div class="flex items-center gap-4 pt-1">
                <PrimaryButton :disabled="form.processing">
                    {{ form.processing ? 'Đang lưu...' : 'Lưu thông tin' }}
                </PrimaryButton>
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.recentlySuccessful" class="text-sm font-semibold text-emerald-600">Đã lưu.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
