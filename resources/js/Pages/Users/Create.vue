<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import { roleLabels } from '@/Support/roleLabels';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit, Role } from '@/types';

const props = defineProps<{
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    roles: Role[];
}>();

const form = useForm({
    organization_unit_id: null as number | null,
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    employee_code: '',
    phone: '',
    job_title: '',
    roles: props.roles.some((role) => role.name === 'employee') ? ['employee'] : [],
});

const toggleRole = (role: string) => {
    form.roles = form.roles.includes(role) ? form.roles.filter((item) => item !== role) : [...form.roles, role];
};

const submit = () => {
    form.transform((data) => {
        const payload: Record<string, unknown> = { ...data };

        if (props.roles.length === 0) {
            delete payload.roles;
        }

        return payload;
    }).post(route('users.store'));
};
</script>

<template>
    <Head title="Thêm người dùng" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Thêm người dùng"
                description="Tạo hồ sơ thành viên và thiết lập quyền truy cập ban đầu."
                eyebrow="Đội ngũ"
            >
                <template #actions>
                    <Link :href="route('users.index')" class="app-button-secondary">
                        <AppIcon name="arrow-left" class="size-4" />
                        Quay lại
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <form class="app-panel overflow-hidden" @submit.prevent="submit">
            <section class="border-b border-slate-100">
                <div class="px-5 py-5 sm:px-7">
                    <h2 class="font-display text-base font-bold text-ink-950">Thông tin công việc</h2>
                    <p class="mt-1 text-xs text-slate-500">Thông tin nhận diện thành viên trong tổ chức.</p>
                </div>
                <div class="app-form-grid px-5 pb-7 sm:px-7 lg:grid-cols-3">
                    <div>
                        <InputLabel for="name" value="Họ và tên" required />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="w-full"
                            required
                            placeholder="Nguyễn Văn An"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="employee_code" value="Mã nhân viên" />
                        <TextInput
                            id="employee_code"
                            v-model="form.employee_code"
                            type="text"
                            class="w-full"
                            placeholder="NV-001"
                        />
                        <InputError class="mt-2" :message="form.errors.employee_code" />
                    </div>
                    <div>
                        <InputLabel for="organization_unit_id" value="Đơn vị" required />
                        <select
                            id="organization_unit_id"
                            v-model="form.organization_unit_id"
                            class="app-field"
                            required
                        >
                            <option :value="null" disabled>Chọn đơn vị</option>
                            <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                                {{ unit.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.organization_unit_id" />
                    </div>
                    <div>
                        <InputLabel for="job_title" value="Chức danh" />
                        <TextInput
                            id="job_title"
                            v-model="form.job_title"
                            type="text"
                            class="w-full"
                            placeholder="Chuyên viên"
                        />
                        <InputError class="mt-2" :message="form.errors.job_title" />
                    </div>
                    <div>
                        <InputLabel for="phone" value="Số điện thoại" />
                        <TextInput
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            class="w-full"
                            placeholder="090 123 4567"
                        />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>
                </div>
            </section>

            <section class="border-b border-slate-100">
                <div class="px-5 py-5 sm:px-7">
                    <h2 class="font-display text-base font-bold text-ink-950">Tài khoản đăng nhập</h2>
                    <p class="mt-1 text-xs text-slate-500">Email dùng để đăng nhập và nhận thông báo hệ thống.</p>
                </div>
                <div class="app-form-grid px-5 pb-7 sm:px-7 lg:grid-cols-3">
                    <div>
                        <InputLabel for="email" value="Email" required />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="w-full"
                            required
                            placeholder="an@congty.vn"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>
                    <div>
                        <InputLabel for="password" value="Mật khẩu" required />
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
                        <InputLabel for="password_confirmation" value="Xác nhận mật khẩu" required />
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
                </div>
            </section>

            <section v-if="roles.length > 0" class="px-5 py-6 sm:px-7">
                <div class="mb-4">
                    <h2 class="flex items-center gap-2 font-display text-base font-bold text-ink-950">
                        <AppIcon name="shield" class="size-4 text-violet-700" />
                        Vai trò
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">Chọn ít nhất một vai trò để xác định quyền truy cập.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="role in roles"
                        :key="role.id"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition"
                        :class="
                            form.roles.includes(role.name)
                                ? 'border-violet-300 bg-violet-50 text-violet-900'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'
                        "
                    >
                        <input
                            type="checkbox"
                            class="size-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                            :checked="form.roles.includes(role.name)"
                            @change="toggleRole(role.name)"
                        />
                        <span class="text-sm font-semibold">{{ roleLabels[role.name] ?? role.name }}</span>
                    </label>
                </div>
                <InputError class="mt-2" :message="form.errors.roles" />
            </section>

            <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-7">
                <Link :href="route('users.index')" class="app-button-secondary">Huỷ</Link>
                <PrimaryButton :disabled="form.processing">{{
                    form.processing ? 'Đang tạo...' : 'Tạo người dùng'
                }}</PrimaryButton>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
