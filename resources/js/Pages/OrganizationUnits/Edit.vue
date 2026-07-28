<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit } from '@/types';

const props = defineProps<{
    organizationUnit: OrganizationUnit;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
}>();

const form = useForm({
    parent_id: props.organizationUnit.parent_id,
    name: props.organizationUnit.name,
    code: props.organizationUnit.code,
    is_active: props.organizationUnit.is_active,
});

const submit = () => {
    form.put(route('organization-units.update', props.organizationUnit.id));
};

const destroy = () => {
    if (confirm(`Xoá đơn vị "${props.organizationUnit.name}"?`)) {
        router.delete(route('organization-units.destroy', props.organizationUnit.id));
    }
};
</script>

<template>
    <Head title="Sửa đơn vị" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Sửa đơn vị</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel for="parent_id" value="Đơn vị cha" />
                            <select
                                id="parent_id"
                                v-model="form.parent_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option :value="null">-- Không có (đơn vị gốc) --</option>
                                <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                                    {{ unit.name }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.parent_id" />
                        </div>

                        <div>
                            <InputLabel for="name" value="Tên đơn vị" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="code" value="Mã đơn vị" />
                            <TextInput id="code" v-model="form.code" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.code" />
                        </div>

                        <div class="flex items-center">
                            <Checkbox id="is_active" v-model:checked="form.is_active" />
                            <InputLabel for="is_active" value="Hoạt động" class="ms-2" />
                        </div>
                        <InputError class="mt-2" :message="(form.errors as Record<string, string>).organization_unit" />

                        <div class="flex items-center justify-between">
                            <DangerButton type="button" @click="destroy">Xoá</DangerButton>
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
