<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit } from '@/types';

defineProps<{
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
}>();

const form = useForm({
    parent_id: null as number | null,
    name: '',
    code: '',
    is_active: true,
});

const submit = () => {
    form.post(route('organization-units.store'));
};
</script>

<template>
    <Head title="Thêm đơn vị" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Thêm đơn vị"
                description="Tạo một đơn vị mới và xác định vị trí của đơn vị trong cơ cấu doanh nghiệp."
                eyebrow="Cơ cấu tổ chức"
            >
                <template #actions>
                    <Link :href="route('organization-units.index')" class="app-button-secondary">
                        <AppIcon name="arrow-left" class="size-4" />
                        Quay lại
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <form class="app-panel overflow-hidden" @submit.prevent="submit">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-7">
                    <h2 class="font-display text-base font-bold text-ink-950">Thông tin đơn vị</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Các trường có dấu * là thông tin bắt buộc.</p>
                </div>

                <div class="space-y-5 p-5 sm:p-7">
                    <div>
                        <InputLabel for="parent_id" value="Đơn vị cha" />
                        <select id="parent_id" v-model="form.parent_id" class="app-field">
                            <option :value="null">Không có — đây là đơn vị gốc</option>
                            <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                                {{ unit.name }}
                            </option>
                        </select>
                        <p class="app-help">Chọn đơn vị trực tiếp quản lý đơn vị mới.</p>
                        <InputError class="mt-2" :message="form.errors.parent_id" />
                    </div>

                    <div class="app-form-grid">
                        <div>
                            <InputLabel for="name" value="Tên đơn vị *" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="w-full"
                                required
                                placeholder="Ví dụ: Phòng Sản phẩm"
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="code" value="Mã đơn vị *" />
                            <TextInput
                                id="code"
                                v-model="form.code"
                                type="text"
                                class="w-full"
                                required
                                placeholder="Ví dụ: PRODUCT"
                            />
                            <p class="app-help">Mã ngắn gọn, duy nhất trong hệ thống.</p>
                            <InputError class="mt-2" :message="form.errors.code" />
                        </div>
                    </div>

                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4"
                    >
                        <Checkbox v-model:checked="form.is_active" />
                        <span>
                            <span class="block text-sm font-semibold text-slate-700">Cho phép hoạt động ngay</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">
                                Thành viên có thể được gắn vào đơn vị sau khi tạo.
                            </span>
                        </span>
                    </label>
                </div>

                <div
                    class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-7"
                >
                    <Link :href="route('organization-units.index')" class="app-button-secondary">Huỷ</Link>
                    <PrimaryButton :disabled="form.processing">
                        {{ form.processing ? 'Đang lưu...' : 'Tạo đơn vị' }}
                    </PrimaryButton>
                </div>
            </form>

            <aside class="app-panel h-fit p-5 sm:p-6">
                <div class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                    <AppIcon name="building" class="size-5" />
                </div>
                <h2 class="mt-4 font-display text-base font-bold text-ink-950">Thiết kế đúng phân cấp</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Đơn vị gốc nằm ở cấp cao nhất. Các phòng ban và nhóm nên được gắn vào đúng đơn vị quản lý trực tiếp.
                </p>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
