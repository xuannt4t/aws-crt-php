<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { projectStatusLabels } from '@/Constants/project';
import { Link, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit, Project, ProjectStatus, User } from '@/types';

const props = defineProps<{
    project?: Pick<
        Project,
        | 'id'
        | 'organization_unit_id'
        | 'owner_id'
        | 'code'
        | 'name'
        | 'description'
        | 'status'
        | 'start_date'
        | 'end_date'
    >;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    users: Pick<User, 'id' | 'name'>[];
    statuses: ProjectStatus[];
}>();

const toDateInput = (value: string | null | undefined) => (value ? value.slice(0, 10) : '');

const form = useForm({
    organization_unit_id: props.project?.organization_unit_id ?? null,
    owner_id: props.project?.owner_id ?? null,
    code: props.project?.code ?? '',
    name: props.project?.name ?? '',
    description: props.project?.description ?? '',
    status: props.project?.status ?? ('planning' as ProjectStatus),
    start_date: toDateInput(props.project?.start_date),
    end_date: toDateInput(props.project?.end_date),
});

const handleSubmit = () => {
    const payload: Record<string, unknown> = { ...form.data() };

    payload.code = String(payload.code ?? '').toUpperCase();
    payload.description = payload.description || null;
    payload.start_date = payload.start_date || null;
    payload.end_date = payload.end_date || null;

    if (props.project) {
        form.transform(() => payload).put(route('projects.update', props.project.id));
    } else {
        form.transform(() => payload).post(route('projects.store'));
    }
};
</script>

<template>
    <form class="app-panel overflow-hidden" @submit.prevent="handleSubmit">
        <section class="border-b border-slate-100">
            <div class="px-5 py-5 sm:px-7">
                <h2 class="font-display text-base font-bold text-ink-950">Thông tin dự án</h2>
                <p class="mt-1 text-xs text-slate-500">Mã và tên dự án dùng để nhận diện trong toàn hệ thống.</p>
            </div>
            <div class="app-form-grid px-5 pb-7 sm:px-7 lg:grid-cols-2">
                <div>
                    <InputLabel for="code" value="Mã dự án *" />
                    <TextInput
                        id="code"
                        v-model="form.code"
                        type="text"
                        class="w-full uppercase"
                        maxlength="32"
                        required
                        placeholder="Ví dụ: PRJ-001"
                    />
                    <InputError class="mt-2" :message="form.errors.code" />
                </div>
                <div>
                    <InputLabel for="name" value="Tên dự án *" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="w-full"
                        maxlength="160"
                        required
                        placeholder="Ví dụ: Triển khai hệ thống ERP"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>
                <div class="lg:col-span-2">
                    <InputLabel for="description" value="Mô tả" />
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        maxlength="5000"
                        class="app-field resize-y"
                        placeholder="Bối cảnh, mục tiêu và phạm vi của dự án..."
                    />
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>
            </div>
        </section>

        <section>
            <div class="px-5 py-5 sm:px-7">
                <h2 class="font-display text-base font-bold text-ink-950">Phân công & lịch trình</h2>
                <p class="mt-1 text-xs text-slate-500">Xác định đơn vị sở hữu, chủ dự án và thời gian thực hiện.</p>
            </div>
            <div class="app-form-grid px-5 pb-7 sm:px-7 lg:grid-cols-2">
                <div>
                    <InputLabel for="organization_unit_id" value="Đơn vị sở hữu *" />
                    <select id="organization_unit_id" v-model="form.organization_unit_id" class="app-field" required>
                        <option :value="null" disabled>Chọn đơn vị</option>
                        <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                            {{ unit.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.organization_unit_id" />
                </div>
                <div>
                    <InputLabel for="owner_id" value="Chủ dự án *" />
                    <select id="owner_id" v-model="form.owner_id" class="app-field" required>
                        <option :value="null" disabled>Chọn chủ dự án</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.owner_id" />
                </div>
                <div>
                    <InputLabel for="status" value="Trạng thái" />
                    <select id="status" v-model="form.status" class="app-field">
                        <option v-for="status in statuses" :key="status" :value="status">
                            {{ projectStatusLabels[status] }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.status" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel for="start_date" value="Ngày bắt đầu" />
                        <input id="start_date" v-model="form.start_date" type="date" class="app-field" />
                        <InputError class="mt-2" :message="form.errors.start_date" />
                    </div>
                    <div>
                        <InputLabel for="end_date" value="Ngày kết thúc" />
                        <input id="end_date" v-model="form.end_date" type="date" class="app-field" />
                        <InputError class="mt-2" :message="form.errors.end_date" />
                    </div>
                </div>
            </div>
        </section>

        <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-7">
            <Link :href="route('projects.index')" class="app-button-secondary">Hủy</Link>
            <PrimaryButton :disabled="form.processing">
                <AppIcon v-if="!form.processing" name="check" class="size-4" />
                {{ form.processing ? 'Đang lưu...' : project ? 'Lưu thay đổi' : 'Tạo dự án' }}
            </PrimaryButton>
        </div>
    </form>
</template>
