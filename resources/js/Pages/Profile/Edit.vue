<script setup lang="ts">
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();
</script>

<template>
    <Head title="Hồ sơ cá nhân" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Hồ sơ cá nhân"
                description="Quản lý thông tin tài khoản và các thiết lập bảo mật của bạn."
                eyebrow="Tài khoản"
            />
        </template>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="app-panel p-5 sm:p-7">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
            </div>

            <div class="app-panel p-5 sm:p-7">
                <UpdatePasswordForm />
            </div>

            <div class="app-panel border-red-100 p-5 sm:p-7 xl:col-span-2">
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
