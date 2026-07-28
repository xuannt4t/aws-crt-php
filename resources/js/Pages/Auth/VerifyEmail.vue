<script setup lang="ts">
import { computed, ref } from 'vue';
import AppLogoutDialog from '@/Components/AppLogoutDialog.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});
const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
const isLogoutDialogOpen = ref(false);

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Xác minh email" />

        <div class="mb-8">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-700">Một bước cuối</p>
            <h1 class="mt-3 font-display text-3xl font-extrabold tracking-[-0.04em] text-ink-950">Xác minh email</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">
                Mở liên kết chúng tôi vừa gửi đến email của bạn. Nếu chưa nhận được, hãy yêu cầu gửi lại.
            </p>
        </div>

        <div
            v-if="verificationLinkSent"
            class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
        >
            Email xác minh mới đã được gửi.
        </div>

        <form @submit.prevent="submit">
            <PrimaryButton class="w-full" :disabled="form.processing">
                {{ form.processing ? 'Đang gửi...' : 'Gửi lại email xác minh' }}
            </PrimaryButton>
            <button
                type="button"
                class="mt-5 w-full text-center text-sm font-semibold text-slate-500 hover:text-slate-800"
                @click="isLogoutDialogOpen = true"
            >
                Đăng xuất
            </button>
        </form>

        <AppLogoutDialog :show="isLogoutDialogOpen" @close="isLogoutDialogOpen = false" />
    </GuestLayout>
</template>
