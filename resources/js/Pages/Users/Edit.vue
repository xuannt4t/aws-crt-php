<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit, User } from '@/types';

const props = defineProps<{
    user: Pick<
        User,
        'id' | 'name' | 'email' | 'organization_unit_id' | 'employee_code' | 'phone' | 'job_title' | 'is_system_admin'
    >;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
}>();

const form = useForm({
    organization_unit_id: props.user.organization_unit_id,
    name: props.user.name,
    email: props.user.email,
    employee_code: props.user.employee_code ?? '',
    phone: props.user.phone ?? '',
    job_title: props.user.job_title ?? '',
    is_system_admin: props.user.is_system_admin,
});

const submit = () => {
    form.put(route('users.update', props.user.id));
};
</script>

<template>
    <Head title="Sửa người dùng" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Sửa người dùng</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel for="organization_unit_id" value="Đơn vị" />
                            <select
                                id="organization_unit_id"
                                v-model="form.organization_unit_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                                    {{ unit.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.organization_unit_id" />
                        </div>

                        <div>
                            <InputLabel for="name" value="Họ tên" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="employee_code" value="Mã nhân viên" />
                            <TextInput
                                id="employee_code"
                                v-model="form.employee_code"
                                type="text"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-2" :message="form.errors.employee_code" />
                        </div>

                        <div>
                            <InputLabel for="phone" value="Điện thoại" />
                            <TextInput id="phone" v-model="form.phone" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div>
                            <InputLabel for="job_title" value="Chức danh" />
                            <TextInput id="job_title" v-model="form.job_title" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.job_title" />
                        </div>

                        <div class="flex items-center">
                            <Checkbox id="is_system_admin" v-model:checked="form.is_system_admin" />
                            <InputLabel for="is_system_admin" value="Quản trị hệ thống" class="ms-2" />
                        </div>

                        <div class="flex items-center justify-end">
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Lưu
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
