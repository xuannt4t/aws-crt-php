<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
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

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);
const avatarInput = ref<HTMLInputElement | null>(null);
const avatarObjectUrl = ref<string | null>(null);
const avatarPreview = ref<string | null>(user.value.avatar_url);

const form = useForm({
    _method: 'patch',
    name: user.value.name,
    email: user.value.email,
    avatar: null as File | null,
});

const clearObjectUrl = () => {
    if (avatarObjectUrl.value) {
        URL.revokeObjectURL(avatarObjectUrl.value);
        avatarObjectUrl.value = null;
    }
};

const selectAvatar = () => {
    avatarInput.value?.click();
};

const handleAvatarChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    form.clearErrors('avatar');

    if (!file) {
        return;
    }

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        form.setError('avatar', 'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.');
        input.value = '';

        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        form.setError('avatar', 'Ảnh đại diện không được lớn hơn 2 MB.');
        input.value = '';

        return;
    }

    clearObjectUrl();
    avatarObjectUrl.value = URL.createObjectURL(file);
    avatarPreview.value = avatarObjectUrl.value;
    form.avatar = file;
};

const submit = () => {
    form.post(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.avatar = null;

            if (avatarInput.value) {
                avatarInput.value.value = '';
            }

            clearObjectUrl();
            avatarPreview.value = page.props.auth.user.avatar_url;
        },
    });
};

onUnmounted(clearObjectUrl);
</script>

<template>
    <section>
        <header>
            <h2 class="font-display text-base font-bold text-ink-950">Thông tin tài khoản</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">Cập nhật họ tên và địa chỉ email đăng nhập.</p>
        </header>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div>
                <InputLabel value="Ảnh đại diện" />
                <div
                    class="mt-2 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:flex-row sm:items-center"
                >
                    <AppUserAvatar :name="user.name" :avatar-url="avatarPreview" size="xl" class="shadow-sm" />
                    <div class="min-w-0 flex-1">
                        <input
                            ref="avatarInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="handleAvatarChange"
                        />
                        <button type="button" class="app-button-secondary" @click="selectAvatar">
                            <AppIcon name="camera" class="size-4" />
                            Chọn ảnh
                        </button>
                        <p class="mt-2 text-xs leading-5 text-slate-500">JPG, PNG hoặc WebP. Dung lượng tối đa 2 MB.</p>
                        <p v-if="form.avatar" class="mt-1 truncate text-xs font-semibold text-brand-700">
                            Đã chọn: {{ form.avatar.name }}
                        </p>
                    </div>
                </div>
                <InputError class="mt-2" :message="form.errors.avatar" />
            </div>

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
            </div>
        </form>
    </section>
</template>
