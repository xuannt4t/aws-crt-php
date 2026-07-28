<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { taskPriorityLabels } from '@/Constants/task';
import { Link, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit, Task, TaskPriority, User } from '@/types';

const props = defineProps<{
    task?: Pick<
        Task,
        'id' | 'organization_unit_id' | 'assignee_id' | 'title' | 'description' | 'priority' | 'due_at'
    >;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    assignableUsers: Pick<User, 'id' | 'name'>[];
    priorities: TaskPriority[];
}>();

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
    assignee_id: props.task?.assignee_id ?? null,
    title: props.task?.title ?? '',
    description: props.task?.description ?? '',
    priority: props.task?.priority ?? ('medium' as TaskPriority),
    due_at: toDateTimeLocal(props.task?.due_at),
});

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
                    <InputLabel for="title" value="Tiêu đề *" />
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
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="7"
                        class="app-field resize-y"
                        placeholder="Bối cảnh, yêu cầu và kết quả mong đợi..."
                    />
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
                    <InputLabel for="organization_unit_id" value="Đơn vị sở hữu *" />
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
                    <InputLabel for="priority" value="Độ ưu tiên *" />
                    <select id="priority" v-model="form.priority" class="app-field" required>
                        <option v-for="priority in priorities" :key="priority" :value="priority">
                            {{ taskPriorityLabels[priority] }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.priority" />
                </div>
                <div v-if="assignableUsers.length > 0">
                    <InputLabel for="assignee_id" value="Người phụ trách chính" />
                    <select id="assignee_id" v-model="form.assignee_id" class="app-field">
                        <option :value="null">Chưa phân công</option>
                        <option v-for="user in assignableUsers" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.assignee_id" />
                </div>
                <div>
                    <InputLabel for="due_at" value="Thời hạn" />
                    <input id="due_at" v-model="form.due_at" type="datetime-local" class="app-field" />
                    <InputError class="mt-2" :message="form.errors.due_at" />
                </div>
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
