<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AppUserSelect from '@/Components/AppUserSelect.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { taskPriorityLabels } from '@/Constants/task';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Editor from 'primevue/editor';
import type { OrganizationUnit, PageProps, Project, Task, TaskPriority, UserOption } from '@/types';

const props = defineProps<{
    task?: Pick<
        Task,
        | 'id'
        | 'organization_unit_id'
        | 'project_id'
        | 'assignee_id'
        | 'title'
        | 'description'
        | 'priority'
        | 'due_at'
        | 'planned_quantity'
        | 'quantity_unit'
    >;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    assignableUsers: UserOption[];
    priorities: TaskPriority[];
    projects: Pick<Project, 'id' | 'name' | 'code'>[];
}>();

const page = usePage<PageProps>();

const toDateTimeLocal = (value: string | null | undefined) => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
};

const form = useForm({
    organization_unit_id: props.task?.organization_unit_id ?? null,
    project_id: props.task?.project_id ?? null,
    assignee_id: props.task?.assignee_id ?? null,
    title: props.task?.title ?? '',
    description: props.task?.description ?? '',
    priority: props.task?.priority ?? ('medium' as TaskPriority),
    due_at: toDateTimeLocal(props.task?.due_at),
    planned_quantity: props.task?.planned_quantity ?? null,
    quantity_unit: props.task?.quantity_unit ?? '',
});

const descriptionFormats = ['header', 'bold', 'italic', 'underline', 'strike', 'list', 'blockquote', 'link'];

const handleSubmit = () => {
    const submit = props.task
        ? (payload: Record<string, unknown>) => form.transform(() => payload).put(route('tasks.update', props.task!.id))
        : (payload: Record<string, unknown>) => form.transform(() => payload).post(route('tasks.store'));

    const payload: Record<string, unknown> = { ...form.data() };

    if (props.assignableUsers.length === 0) {
        delete payload.assignee_id;
    }

    if (!payload.due_at) {
        payload.due_at = null;
    } else {
        payload.due_at = new Date(String(payload.due_at)).toISOString();
    }

    if (!payload.planned_quantity) {
        payload.planned_quantity = null;
        delete payload.quantity_unit;
    } else if (!payload.quantity_unit) {
        payload.quantity_unit = null;
    }

    submit(payload);
};
</script>

<template>
    <form class="app-panel overflow-hidden" @submit.prevent="handleSubmit">
        <section class="border-b border-slate-100">
            <div class="px-5 py-5 sm:px-7">
                <h2 class="font-display text-base font-bold text-ink-950">Nội dung công việc</h2>
                <p class="mt-1 text-xs text-slate-500">Mô tả rõ kết quả cần đạt và phạm vi thực hiện.</p>
            </div>
            <div class="space-y-5 px-5 pb-7 sm:px-7">
                <div>
                    <InputLabel for="title" value="Tiêu đề" required />
                    <TextInput
                        id="title"
                        v-model="form.title"
                        type="text"
                        class="w-full"
                        required
                        placeholder="Ví dụ: Hoàn thiện báo cáo vận hành tháng 7"
                    />
                    <InputError class="mt-2" :message="form.errors.title" />
                </div>
                <div>
                    <InputLabel for="description" value="Mô tả" />
                    <Editor
                        id="description"
                        v-model="form.description"
                        :formats="descriptionFormats"
                        editor-style="height: 260px"
                        class="task-rich-editor mt-2"
                        placeholder="Bối cảnh, yêu cầu và kết quả mong đợi..."
                        aria-label="Mô tả công việc"
                    >
                        <template #toolbar>
                            <span class="ql-formats">
                                <select class="ql-header" title="Kiểu đoạn">
                                    <option value="2">Tiêu đề</option>
                                    <option value="3">Tiêu đề nhỏ</option>
                                    <option selected>Đoạn văn</option>
                                </select>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-bold" type="button" title="In đậm" />
                                <button class="ql-italic" type="button" title="In nghiêng" />
                                <button class="ql-underline" type="button" title="Gạch chân" />
                                <button class="ql-strike" type="button" title="Gạch ngang" />
                            </span>
                            <span class="ql-formats">
                                <button class="ql-list" value="ordered" type="button" title="Danh sách số" />
                                <button class="ql-list" value="bullet" type="button" title="Danh sách dấu chấm" />
                                <button class="ql-blockquote" type="button" title="Trích dẫn" />
                            </span>
                            <span class="ql-formats">
                                <button class="ql-link" type="button" title="Chèn liên kết" />
                                <button class="ql-clean" type="button" title="Xóa định dạng" />
                            </span>
                        </template>
                    </Editor>
                    <p class="mt-2 text-xs text-slate-500">
                        Dùng tiêu đề và danh sách để chia nhỏ yêu cầu, kết quả bàn giao và tiêu chí hoàn thành.
                    </p>
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>
            </div>
        </section>

        <section>
            <div class="px-5 py-5 sm:px-7">
                <h2 class="font-display text-base font-bold text-ink-950">Phạm vi & thời hạn</h2>
                <p class="mt-1 text-xs text-slate-500">Xác định đơn vị sở hữu, độ ưu tiên và người phụ trách.</p>
            </div>
            <div class="app-form-grid px-5 pb-7 sm:px-7 lg:grid-cols-2">
                <div>
                    <InputLabel for="organization_unit_id" value="Đơn vị sở hữu" required />
                    <select id="organization_unit_id" v-model="form.organization_unit_id" class="app-field" required>
                        <option :value="null" disabled>Chọn đơn vị</option>
                        <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                            {{ unit.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.organization_unit_id" />
                </div>
                <div>
                    <InputLabel for="project_id" value="Việc dự án" />
                    <select id="project_id" v-model="form.project_id" class="app-field">
                        <option :value="null">Không thuộc việc dự án</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">
                            {{ project.code }} · {{ project.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.project_id" />
                </div>
                <div>
                    <InputLabel for="priority" value="Độ ưu tiên" required />
                    <select id="priority" v-model="form.priority" class="app-field" required>
                        <option v-for="priority in priorities" :key="priority" :value="priority">
                            {{ taskPriorityLabels[priority] }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.priority" />
                </div>
                <div v-if="assignableUsers.length > 0">
                    <InputLabel for="assignee_id" value="Người phụ trách chính" />
                    <AppUserSelect
                        v-model="form.assignee_id"
                        input-id="assignee_id"
                        :options="assignableUsers"
                        :current-user-id="page.props.auth.user.id"
                        :invalid="Boolean(form.errors.assignee_id)"
                        clearable
                        placeholder="Chưa phân công"
                    />
                    <p
                        v-if="assignableUsers.length === 1 && assignableUsers[0].id === page.props.auth.user.id"
                        class="mt-2 text-xs text-slate-500"
                    >
                        Bạn có thể tự nhận công việc; phân công cho người khác cần quyền quản lý công việc.
                    </p>
                    <InputError class="mt-2" :message="form.errors.assignee_id" />
                </div>
                <div>
                    <InputLabel for="due_at" value="Thời hạn" />
                    <input id="due_at" v-model="form.due_at" type="datetime-local" class="app-field" />
                    <InputError class="mt-2" :message="form.errors.due_at" />
                </div>
                <div class="grid gap-5 sm:col-span-2 sm:grid-cols-2">
                    <div>
                        <InputLabel for="planned_quantity" value="Số lượng dự kiến" />
                        <TextInput
                            id="planned_quantity"
                            v-model.number="form.planned_quantity"
                            type="number"
                            min="1"
                            max="1000000"
                            step="1"
                            class="mt-1 block w-full"
                            placeholder="Ví dụ: 500"
                        />
                        <InputError class="mt-2" :message="form.errors.planned_quantity" />
                    </div>
                    <div>
                        <InputLabel for="quantity_unit" value="Đơn vị" />
                        <TextInput
                            id="quantity_unit"
                            v-model="form.quantity_unit"
                            type="text"
                            maxlength="30"
                            class="mt-1 block w-full"
                            placeholder="hồ sơ, cuộc gọi..."
                            :disabled="!form.planned_quantity"
                        />
                        <InputError class="mt-2" :message="form.errors.quantity_unit" />
                    </div>
                </div>
                <p class="text-xs text-slate-500 sm:col-span-2">
                    Để trống số lượng dự kiến nếu công việc theo dõi tiến độ bằng phần trăm.
                </p>
            </div>
        </section>

        <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-7">
            <Link :href="route('tasks.index')" class="app-button-secondary">Hủy</Link>
            <PrimaryButton :disabled="form.processing">
                <AppIcon v-if="!form.processing" name="check" class="size-4" />
                {{ form.processing ? 'Đang lưu...' : task ? 'Lưu thay đổi' : 'Tạo công việc' }}
            </PrimaryButton>
        </div>
    </form>
</template>
