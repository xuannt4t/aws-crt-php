<script setup lang="ts">
import { ref } from 'vue';
import AppConfirmDialog from '@/Components/AppConfirmDialog.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import type { OrganizationUnit } from '@/types';

const props = defineProps<{
    organizationUnit: OrganizationUnit;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
}>();

const page = usePage();
const isConfirmingDelete = ref(false);

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
    router.delete(route('organization-units.destroy', props.organizationUnit.id), {
        onFinish: () => {
            isConfirmingDelete.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Sửa ${organizationUnit.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                :title="organizationUnit.name"
                description="Cập nhật thông tin và vị trí của đơn vị trong cơ cấu doanh nghiệp."
                eyebrow="Chỉnh sửa đơn vị"
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
                    <p class="mt-1 text-xs leading-5 text-slate-500">Thay đổi sẽ có hiệu lực ngay sau khi lưu.</p>
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
                        <InputError class="mt-2" :message="form.errors.parent_id" />
                    </div>

                    <div class="app-form-grid">
                        <div>
                            <InputLabel for="name" value="Tên đơn vị" required />
                            <TextInput id="name" v-model="form.name" type="text" class="w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="code" value="Mã đơn vị" required />
                            <TextInput id="code" v-model="form.code" type="text" class="w-full" required />
                            <InputError class="mt-2" :message="form.errors.code" />
                        </div>
                    </div>

                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4"
                    >
                        <Checkbox v-model:checked="form.is_active" />
                        <span>
                            <span class="block text-sm font-semibold text-slate-700">Đơn vị đang hoạt động</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">
                                Tắt trạng thái nếu đơn vị tạm ngừng vận hành.
                            </span>
                        </span>
                    </label>

                    <InputError
                        :message="(page.props.errors as Record<string, string>).organization_unit"
                        class="rounded-xl border border-red-200 bg-red-50 px-4 py-3"
                    />
                </div>

                <div
                    class="flex items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-7"
                >
                    <DangerButton type="button" @click="isConfirmingDelete = true">
                        <AppIcon name="trash" class="size-4" />
                        Xoá đơn vị
                    </DangerButton>
                    <PrimaryButton :disabled="form.processing">
                        {{ form.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                    </PrimaryButton>
                </div>
            </form>

            <aside class="app-panel h-fit p-5 sm:p-6">
                <div class="flex size-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                    <AppIcon name="shield" class="size-5" />
                </div>
                <h2 class="mt-4 font-display text-base font-bold text-ink-950">Lưu ý khi thay đổi</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Di chuyển đơn vị có thể thay đổi phạm vi dữ liệu của các luồng nghiệp vụ được bổ sung ở những sprint
                    sau.
                </p>
            </aside>
        </div>

        <AppConfirmDialog
            :show="isConfirmingDelete"
            title="Xoá đơn vị?"
            :description="`Đơn vị “${organizationUnit.name}” sẽ được chuyển vào trạng thái đã xoá. Hệ thống sẽ từ chối nếu đơn vị vẫn còn dữ liệu liên quan.`"
            confirm-label="Xoá đơn vị"
            @cancel="isConfirmingDelete = false"
            @confirm="destroy"
        />
    </AuthenticatedLayout>
</template>
