<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import { roleLabels } from '@/Support/roleLabels';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit, Role, User } from '@/types';

const props = defineProps<{
    user: Pick<
        User,
        'id' | 'name' | 'email' | 'organization_unit_id' | 'employee_code' | 'phone' | 'job_title' | 'avatar_url'
    >;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    selectedRoles: string[];
    roles: Role[];
}>();

const form = useForm({
    organization_unit_id: props.user.organization_unit_id,
    name: props.user.name,
    email: props.user.email,
    employee_code: props.user.employee_code ?? '',
    phone: props.user.phone ?? '',
    job_title: props.user.job_title ?? '',
    roles: [...props.selectedRoles],
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
    }).put(route('users.update', props.user.id));
};
</script>

<template>
    <Head :title="`Sửa ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex items-center gap-4">
                    <AppUserAvatar :name="user.name" :avatar-url="user.avatar_url" size="lg" />
                    <div>
                        <p class="mb-1 text-xs font-bold uppercase tracking-[0.16em] text-brand-700">
                            Chỉnh sửa thành viên
                        </p>
                        <h1
                            class="font-display text-2xl font-extrabold tracking-[-0.035em] text-ink-950 sm:text-[2rem]"
                        >
                            {{ user.name }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">{{ user.email }}</p>
                    </div>
                </div>
                <Link :href="route('users.index')" class="app-button-secondary">
                    <AppIcon name="arrow-left" class="size-4" />
                    Quay lại
                </Link>
            </div>
        </template>

        <form class="app-panel overflow-hidden" @submit.prevent="submit">
            <section class="border-b border-slate-100">
                <div class="px-5 py-5 sm:px-7">
                    <h2 class="font-display text-base font-bold text-ink-950">Thông tin công việc</h2>
                    <p class="mt-1 text-xs text-slate-500">Cập nhật thông tin nhận diện trong tổ chức.</p>
                </div>
                <div class="app-form-grid px-5 pb-7 sm:px-7 lg:grid-cols-3">
                    <div>
                        <InputLabel for="name" value="Họ và tên" required />
                        <TextInput id="name" v-model="form.name" type="text" class="w-full" required />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>
                    <div>
                        <InputLabel for="employee_code" value="Mã nhân viên" />
                        <TextInput id="employee_code" v-model="form.employee_code" type="text" class="w-full" />
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
                            <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                                {{ unit.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.organization_unit_id" />
                    </div>
                    <div>
                        <InputLabel for="job_title" value="Chức danh" />
                        <TextInput id="job_title" v-model="form.job_title" type="text" class="w-full" />
                        <InputError class="mt-2" :message="form.errors.job_title" />
                    </div>
                    <div>
                        <InputLabel for="phone" value="Số điện thoại" />
                        <TextInput id="phone" v-model="form.phone" type="tel" class="w-full" />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>
                </div>
            </section>

            <section class="border-b border-slate-100">
                <div class="px-5 py-5 sm:px-7">
                    <h2 class="font-display text-base font-bold text-ink-950">Tài khoản đăng nhập</h2>
                    <p class="mt-1 text-xs text-slate-500">Email đăng nhập phải là duy nhất trong hệ thống.</p>
                </div>
                <div class="max-w-xl px-5 pb-7 sm:px-7">
                    <InputLabel for="email" value="Email" required />
                    <TextInput id="email" v-model="form.email" type="email" class="w-full" required />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
            </section>

            <section v-if="roles.length > 0" class="px-5 py-6 sm:px-7">
                <div class="mb-4">
                    <h2 class="flex items-center gap-2 font-display text-base font-bold text-ink-950">
                        <AppIcon name="shield" class="size-4 text-violet-700" />
                        Vai trò
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Thay đổi vai trò sẽ cập nhật toàn bộ quyền của người dùng.
                    </p>
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
                    form.processing ? 'Đang lưu...' : 'Lưu thay đổi'
                }}</PrimaryButton>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
