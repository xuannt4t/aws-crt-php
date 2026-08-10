<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppUserSelect from '@/Components/AppUserSelect.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { taskPriorityLabels } from '@/Constants/task';
import { Link, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit, Project, RecurrenceFrequency, TaskPriority, TaskRecurrence, UserOption } from '@/types';

const props = defineProps<{
    recurrence?: Pick<
        TaskRecurrence,
        | 'id'
        | 'organization_unit_id'
        | 'project_id'
        | 'assignee_id'
        | 'title'
        | 'description'
        | 'priority'
        | 'planned_quantity'
        | 'quantity_unit'
        | 'frequency'
        | 'interval'
        | 'weekdays'
        | 'day_of_month'
        | 'start_date'
        | 'due_time'
    >;
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    assignableUsers: UserOption[];
    priorities: TaskPriority[];
    frequencies: { value: RecurrenceFrequency; label: string }[];
    projects: Pick<Project, 'id' | 'name' | 'code'>[];
}>();

const weekdayOptions: { value: number; label: string }[] = [
    { value: 1, label: 'Thứ Hai' },
    { value: 2, label: 'Thứ Ba' },
    { value: 3, label: 'Thứ Tư' },
    { value: 4, label: 'Thứ Năm' },
    { value: 5, label: 'Thứ Sáu' },
    { value: 6, label: 'Thứ Bảy' },
    { value: 7, label: 'Chủ Nhật' },
];

const toDateInput = (value: string | null | undefined) => (value ? value.slice(0, 10) : '');
const toTimeInput = (value: string | null | undefined) => (value ? value.slice(0, 5) : '');

const form = useForm({
    organization_unit_id: props.recurrence?.organization_unit_id ?? null,
    project_id: props.recurrence?.project_id ?? null,
    assignee_id: props.recurrence?.assignee_id ?? null,
    title: props.recurrence?.title ?? '',
    description: props.recurrence?.description ?? '',
    priority: props.recurrence?.priority ?? ('medium' as TaskPriority),
    planned_quantity: props.recurrence?.planned_quantity ?? null,
    quantity_unit: props.recurrence?.quantity_unit ?? '',
    frequency: props.recurrence?.frequency ?? ('daily' as RecurrenceFrequency),
    interval: props.recurrence?.interval ?? 1,
    weekdays: props.recurrence?.weekdays ?? ([] as number[]),
    day_of_month: props.recurrence?.day_of_month ?? null,
    start_date: toDateInput(props.recurrence?.start_date) || toDateInput(new Date().toISOString()),
    due_time: toTimeInput(props.recurrence?.due_time),
});

const isWeekly = computed(() => form.frequency === 'weekly');
const needsDayOfMonth = computed(() => form.frequency === 'monthly' || form.frequency === 'quarterly');

const toggleWeekday = (value: number) => {
    const index = form.weekdays.indexOf(value);

    if (index === -1) {
        form.weekdays = [...form.weekdays, value].sort((a, b) => a - b);
    } else {
        form.weekdays = form.weekdays.filter((weekday) => weekday !== value);
    }
};

const handleSubmit = () => {
    const submit = props.recurrence
        ? (payload: Record<string, unknown>) =>
              form.transform(() => payload).put(route('task-recurrences.update', props.recurrence!.id))
        : (payload: Record<string, unknown>) => form.transform(() => payload).post(route('task-recurrences.store'));

    const payload: Record<string, unknown> = { ...form.data() };

    payload.description = payload.description || null;

    if (props.assignableUsers.length === 0) {
        delete payload.assignee_id;
    }

    if (!payload.planned_quantity) {
        payload.planned_quantity = null;
        delete payload.quantity_unit;
    } else if (!payload.quantity_unit) {
        payload.quantity_unit = null;
    }

    payload.due_time = payload.due_time || null;

    if (isWeekly.value) {
        delete payload.day_of_month;
    } else {
        delete payload.weekdays;

        if (!needsDayOfMonth.value) {
            delete payload.day_of_month;
        }
    }

    submit(payload);
};
</script>

<template>
    <form class="app-panel overflow-hidden" @submit.prevent="handleSubmit">
        <section class="border-b border-slate-100">
            <div class="px-5 py-5 sm:px-7">
                <h2 class="font-display text-base font-bold text-ink-950">Nội dung mẫu</h2>
                <p class="mt-1 text-xs text-slate-500">Tiêu đề và mô tả sẽ được áp dụng cho mọi công việc sinh ra.</p>
            </div>
            <div class="space-y-5 px-5 pb-7 sm:px-7">
                <div>
                    <InputLabel for="title" value="Tiêu đề" required />
                    <TextInput
                        id="title"
                        v-model="form.title"
                        type="text"
                        class="w-full"
                        maxlength="200"
                        required
                        placeholder="Ví dụ: Báo cáo doanh thu hàng tuần"
                    />
                    <InputError class="mt-2" :message="form.errors.title" />
                </div>
                <div>
                    <InputLabel for="description" value="Mô tả" />
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        maxlength="5000"
                        class="app-field resize-y"
                        placeholder="Bối cảnh, yêu cầu và kết quả mong đợi..."
                    />
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>
            </div>
        </section>

        <section class="border-b border-slate-100">
            <div class="px-5 py-5 sm:px-7">
                <h2 class="font-display text-base font-bold text-ink-950">Phạm vi & phân công</h2>
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
                        :invalid="Boolean(form.errors.assignee_id)"
                        clearable
                        placeholder="Chưa phân công"
                    />
                    <InputError class="mt-2" :message="form.errors.assignee_id" />
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
                            maxlength="32"
                            class="mt-1 block w-full"
                            placeholder="hồ sơ, cuộc gọi..."
                            :disabled="!form.planned_quantity"
                        />
                        <InputError class="mt-2" :message="form.errors.quantity_unit" />
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="px-5 py-5 sm:px-7">
                <h2 class="font-display text-base font-bold text-ink-950">Chu kỳ lặp</h2>
                <p class="mt-1 text-xs text-slate-500">Xác định kiểu lặp, tần suất và ngày bắt đầu sinh công việc.</p>
            </div>
            <div class="app-form-grid px-5 pb-7 sm:px-7 lg:grid-cols-2">
                <div>
                    <InputLabel for="frequency" value="Kiểu lặp" required />
                    <select id="frequency" v-model="form.frequency" class="app-field" required>
                        <option v-for="item in frequencies" :key="item.value" :value="item.value">
                            {{ item.label }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.frequency" />
                </div>
                <div>
                    <InputLabel for="interval" value="Mỗi N kỳ" required />
                    <TextInput
                        id="interval"
                        v-model.number="form.interval"
                        type="number"
                        min="1"
                        max="52"
                        step="1"
                        class="w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.interval" />
                </div>

                <div v-if="isWeekly" class="lg:col-span-2">
                    <InputLabel value="Chọn thứ trong tuần" required />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button
                            v-for="option in weekdayOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-full border px-3.5 py-2 text-xs font-semibold transition"
                            :class="
                                form.weekdays.includes(option.value)
                                    ? 'border-brand-600 bg-brand-600 text-white'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-brand-300 hover:text-brand-700'
                            "
                            @click="toggleWeekday(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <InputError class="mt-2" :message="form.errors.weekdays" />
                </div>

                <div v-if="needsDayOfMonth">
                    <InputLabel for="day_of_month" value="Ngày trong tháng" required />
                    <TextInput
                        id="day_of_month"
                        v-model.number="form.day_of_month"
                        type="number"
                        min="1"
                        max="31"
                        step="1"
                        class="w-full"
                        required
                    />
                    <p class="mt-2 text-xs text-slate-500">
                        Nếu tháng không đủ số ngày, công việc sẽ sinh vào ngày cuối cùng của tháng.
                    </p>
                    <InputError class="mt-2" :message="form.errors.day_of_month" />
                </div>

                <div>
                    <InputLabel for="start_date" value="Ngày bắt đầu" required />
                    <input id="start_date" v-model="form.start_date" type="date" class="app-field" required />
                    <InputError class="mt-2" :message="form.errors.start_date" />
                </div>
                <div>
                    <InputLabel for="due_time" value="Giờ hết hạn trong ngày" />
                    <input id="due_time" v-model="form.due_time" type="time" class="app-field" />
                    <InputError class="mt-2" :message="form.errors.due_time" />
                </div>
            </div>
        </section>

        <div class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-7">
            <Link :href="route('task-recurrences.index')" class="app-button-secondary">Hủy</Link>
            <PrimaryButton :disabled="form.processing">
                <AppIcon v-if="!form.processing" name="check" class="size-4" />
                {{ form.processing ? 'Đang lưu...' : recurrence ? 'Lưu thay đổi' : 'Tạo mẫu' }}
            </PrimaryButton>
        </div>
    </form>
</template>
