<script setup lang="ts">
import { ref } from 'vue';
import AppConfirmDialog from '@/Components/AppConfirmDialog.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppStatusBadge from '@/Components/AppStatusBadge.vue';
import AppTaskPriorityBadge from '@/Components/AppTaskPriorityBadge.vue';
import { taskStatusClasses, taskStatusLabels } from '@/Constants/task';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { OrganizationUnit, Project, Task, TaskRecurrence, User } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTasks {
    data: Task[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    recurrence: TaskRecurrence & {
        description: string;
        next_occurrence: string | null;
        organization_unit?: Pick<OrganizationUnit, 'id' | 'name'>;
        project?: Pick<Project, 'id' | 'name' | 'code'> | null;
        creator?: Pick<User, 'id' | 'name'>;
        assignee?: Pick<User, 'id' | 'name'> | null;
    };
    tasks: PaginatedTasks;
    actions: {
        update: boolean;
        delete: boolean;
        toggle: boolean;
    };
}>();

const isConfirmingDelete = ref(false);
const isDeleting = ref(false);
const isToggling = ref(false);

const confirmDelete = () => {
    router.delete(route('task-recurrences.destroy', props.recurrence.id), {
        onStart: () => {
            isDeleting.value = true;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const handleToggle = () => {
    router.patch(
        route('task-recurrences.toggle', props.recurrence.id),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isToggling.value = true;
            },
            onFinish: () => {
                isToggling.value = false;
            },
        },
    );
};

const formatDate = (value: string | null) => {
    if (!value) {
        return 'Chưa thiết lập';
    }

    return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium' }).format(new Date(value));
};

const formatTime = (value: string | null) => {
    if (!value) {
        return 'Không đặt';
    }

    return value.slice(0, 5);
};

const paginationLabel = (label: string) => {
    if (label.includes('Previous')) {
        return 'Trước';
    }

    if (label.includes('Next')) {
        return 'Sau';
    }

    return label;
};
</script>

<template>
    <Head :title="recurrence.title" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                :title="recurrence.title"
                :description="`Mẫu công việc định kỳ · ${recurrence.organization_unit?.name ?? 'Chưa có đơn vị'}`"
                eyebrow="Chi tiết mẫu"
            >
                <template #actions>
                    <Link :href="route('task-recurrences.index')" class="app-button-secondary">
                        <AppIcon name="arrow-left" class="size-4" />
                        Danh sách
                    </Link>
                    <Link
                        v-if="actions.update"
                        :href="route('task-recurrences.edit', recurrence.id)"
                        class="app-button-secondary"
                    >
                        <AppIcon name="edit" class="size-4" />
                        Chỉnh sửa
                    </Link>
                    <button
                        v-if="actions.toggle"
                        type="button"
                        class="app-button-secondary"
                        :disabled="isToggling"
                        @click="handleToggle"
                    >
                        <AppIcon :name="recurrence.is_active ? 'lock' : 'unlock'" class="size-4" />
                        {{ recurrence.is_active ? 'Tạm dừng' : 'Kích hoạt' }}
                    </button>
                    <button
                        v-if="actions.delete"
                        type="button"
                        class="app-button-secondary text-red-600 hover:bg-red-50"
                        @click="isConfirmingDelete = true"
                    >
                        <AppIcon name="trash" class="size-4" />
                        Xóa
                    </button>
                </template>
            </AppPageHeader>
        </template>

        <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
            <div class="space-y-5">
                <section class="app-panel p-5 sm:p-7">
                    <div class="flex flex-wrap items-center gap-2">
                        <AppStatusBadge
                            :active="recurrence.is_active"
                            active-label="Đang bật"
                            inactive-label="Đã tắt"
                        />
                        <AppTaskPriorityBadge :priority="recurrence.priority" show-prefix />
                    </div>

                    <div class="mt-6">
                        <h2 class="font-display text-base font-bold text-ink-950">Mô tả</h2>
                        <p v-if="recurrence.description" class="mt-3 text-sm leading-6 text-slate-600">
                            {{ recurrence.description }}
                        </p>
                        <p v-else class="mt-3 text-sm italic text-slate-400">Chưa có mô tả chi tiết.</p>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Kỳ kế tiếp</h3>
                            <p class="mt-2 text-sm font-semibold text-slate-700">
                                {{ formatDate(recurrence.next_occurrence) }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Giờ hết hạn</h3>
                            <p class="mt-2 text-sm font-semibold text-slate-700">
                                {{ formatTime(recurrence.due_time) }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="app-panel overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 class="font-display text-base font-bold text-ink-950">Công việc đã sinh</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ tasks.total }} công việc · hiển thị {{ tasks.from ?? 0 }}–{{ tasks.to ?? 0 }}
                        </p>
                    </div>

                    <AppEmptyState
                        v-if="tasks.data.length === 0"
                        icon="tasks"
                        title="Chưa sinh công việc nào"
                        description="Công việc sẽ tự động sinh theo chu kỳ đã thiết lập."
                    />

                    <ul v-else class="divide-y divide-slate-100">
                        <li
                            v-for="task in tasks.data"
                            :key="task.id"
                            class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6"
                        >
                            <div class="min-w-0">
                                <Link
                                    :href="route('tasks.show', task.id)"
                                    class="block truncate text-sm font-bold text-slate-800 hover:text-brand-700"
                                >
                                    {{ task.title }}
                                </Link>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ task.assignee ? task.assignee.name : 'Chưa phân công' }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold"
                                :class="taskStatusClasses[task.status]"
                            >
                                {{ taskStatusLabels[task.status] }}
                            </span>
                        </li>
                    </ul>

                    <nav
                        v-if="tasks.last_page > 1"
                        class="flex flex-wrap items-center justify-center gap-1 border-t border-slate-100 px-5 py-4"
                        aria-label="Phân trang công việc đã sinh"
                    >
                        <template v-for="link in tasks.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="min-w-9 rounded-lg px-3 py-2 text-center text-xs font-semibold transition"
                                :class="
                                    link.active
                                        ? 'bg-brand-600 text-white'
                                        : 'bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                "
                            >
                                {{ paginationLabel(link.label) }}
                            </Link>
                            <span v-else class="min-w-9 px-3 py-2 text-center text-xs text-slate-300">
                                {{ paginationLabel(link.label) }}
                            </span>
                        </template>
                    </nav>
                </section>
            </div>

            <aside class="app-panel h-fit p-5 sm:p-6">
                <h2 class="font-display text-base font-bold text-ink-950">Thông tin mẫu</h2>

                <dl class="mt-5 space-y-5">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Người phụ trách</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">
                            {{ recurrence.assignee?.name ?? 'Chưa phân công' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Người tạo</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">{{ recurrence.creator?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Đơn vị sở hữu</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">
                            {{ recurrence.organization_unit?.name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Dự án</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">
                            <Link
                                v-if="recurrence.project"
                                :href="route('projects.show', recurrence.project.id)"
                                class="text-brand-700 hover:underline"
                            >
                                {{ recurrence.project.name }}
                            </Link>
                            <span v-else class="text-slate-400">Không thuộc dự án</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Ngày bắt đầu</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">
                            {{ formatDate(recurrence.start_date) }}
                        </dd>
                    </div>
                    <div v-if="recurrence.planned_quantity">
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                            Số lượng dự kiến
                        </dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">
                            {{ recurrence.planned_quantity }} {{ recurrence.quantity_unit }}
                        </dd>
                    </div>
                </dl>
            </aside>
        </div>

        <AppConfirmDialog
            :show="isConfirmingDelete"
            title="Xóa mẫu công việc định kỳ?"
            :description="`Mẫu “${recurrence.title}” sẽ được chuyển vào trạng thái đã xóa. Các công việc đã sinh trước đó không bị ảnh hưởng.`"
            confirm-label="Xóa mẫu"
            :processing="isDeleting"
            @cancel="isConfirmingDelete = false"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
