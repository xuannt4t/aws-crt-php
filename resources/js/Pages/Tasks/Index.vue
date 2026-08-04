<script setup lang="ts">
import { computed, ref } from 'vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppTaskPriorityBadge from '@/Components/AppTaskPriorityBadge.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import TaskFilterBar from '@/Components/TaskFilterBar.vue';
import TaskSummaryCards from '@/Components/TaskSummaryCards.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { taskContextDescriptions, taskContextLabels, taskStatusClasses, taskStatusLabels } from '@/Constants/task';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type {
    OrganizationUnit,
    PageProps,
    Project,
    Task,
    TaskApplyRoute,
    TaskContext,
    TaskDashboardScope,
    TaskFilterKey,
    TaskIndexFilters,
    TaskPriority,
    TaskStatus,
    TaskSummary,
    User,
} from '@/types';

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
    tasks: PaginatedTasks;
    filters: TaskIndexFilters;
    statuses: TaskStatus[];
    priorities: TaskPriority[];
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    users: Pick<User, 'id' | 'name'>[];
    projects: Pick<Project, 'id' | 'name' | 'code'>[];
    summary: TaskSummary;
    context: TaskContext;
    availableFilters: TaskFilterKey[];
    scope: TaskDashboardScope | null;
    applyRoute: TaskApplyRoute;
}>();

const page = usePage<PageProps>();
const { can } = usePermissions();
const canCreate = computed(() => can('task.create'));

// Bối cảnh do route/controller quyết định (spec §5.1, §5.2) — chỉ dùng để
// đổi tiêu đề trang, KHÔNG bao giờ tự suy ra hay đổi qua query. Ở cấp 2
// (props.scope khác null) tiêu đề là tên dự án/phòng thay vì nhãn bối cảnh.
const pageTitle = computed(() => props.scope?.name ?? taskContextLabels[props.context]);
const pageDescription = computed(() =>
    props.scope
        ? `Ba khúc công việc cố định trong phạm vi bạn được xem, chỉ tính việc thuộc ${
              props.scope.type === 'project' ? 'dự án' : 'phòng ban'
          } này.`
        : taskContextDescriptions[props.context],
);

const isLoading = ref(false);

function submitFilters(payload: {
    search?: string;
    status?: string;
    priority?: string;
    organization_unit_id?: number;
    project_id?: number;
    assignee_ids?: number[];
}) {
    router.get(route(props.applyRoute.name, props.applyRoute.params), payload, {
        preserveState: true,
        replace: true,
        onStart: () => {
            isLoading.value = true;
        },
        onFinish: () => {
            isLoading.value = false;
        },
    });
}

const formatDueDate = (value: string | null) => {
    if (!value) {
        return 'Chưa đặt hạn';
    }

    return new Intl.DateTimeFormat('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
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
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader :title="pageTitle" :description="pageDescription" eyebrow="Task Core">
                <template v-if="scope || canCreate" #actions>
                    <Link v-if="scope" :href="route(scope.backRouteName)" class="app-button-secondary">
                        <AppIcon name="arrow-left" class="size-4" />
                        Quay lại danh sách
                    </Link>
                    <Link v-if="canCreate" :href="route('tasks.create')" class="app-button-primary">
                        <AppIcon name="plus" class="size-4" />
                        Tạo công việc
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <div class="space-y-5">
            <TaskSummaryCards :summary="summary" />

            <TaskFilterBar
                :filters="filters"
                :available-filters="availableFilters"
                :statuses="statuses"
                :priorities="priorities"
                :organization-units="organizationUnits"
                :users="users"
                :projects="projects"
                @apply="submitFilters"
                @reset="() => submitFilters({})"
            />

            <section class="app-panel overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h2 class="font-display text-base font-bold text-ink-950">Danh sách chi tiết</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ tasks.total }} công việc · hiển thị {{ tasks.from ?? 0 }}–{{ tasks.to ?? 0 }}
                    </p>
                </div>

                <AppEmptyState
                    v-if="tasks.data.length === 0"
                    icon="tasks"
                    title="Chưa có công việc phù hợp"
                    description="Thay đổi bộ lọc hoặc tạo công việc đầu tiên để bắt đầu vận hành."
                >
                    <template v-if="canCreate" #action>
                        <Link :href="route('tasks.create')" class="app-button-primary">
                            <AppIcon name="plus" class="size-4" />
                            Tạo công việc
                        </Link>
                    </template>
                </AppEmptyState>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[1120px] border-collapse text-left">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/70 text-xs font-bold text-slate-500">
                                <th class="px-5 py-3 sm:px-6">Tên việc</th>
                                <th class="px-5 py-3">Tình trạng</th>
                                <th class="px-5 py-3">Mức ưu tiên</th>
                                <th class="px-5 py-3">Người phụ trách</th>
                                <th class="px-5 py-3">Phòng ban</th>
                                <th class="px-5 py-3">Dự án</th>
                                <th class="px-5 py-3">Hạn</th>
                                <th class="px-5 py-3">Người tạo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="task in tasks.data"
                                :key="task.id"
                                class="hover:bg-slate-50/60"
                                :class="task.is_overdue ? 'bg-red-50/50' : ''"
                            >
                                <td class="max-w-md px-5 py-4 sm:px-6">
                                    <Link
                                        :href="route('tasks.show', task.id)"
                                        class="block truncate text-sm font-bold text-slate-800 hover:text-brand-700"
                                    >
                                        {{ task.title }}
                                    </Link>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                                        <span
                                            v-if="task.is_overdue"
                                            class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 font-bold uppercase text-red-700"
                                        >
                                            Quá hạn
                                        </span>
                                        <Link
                                            v-if="task.recurrence && !task.recurrence.deleted_at"
                                            :href="route('task-recurrences.show', task.recurrence.id)"
                                            class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 font-semibold text-brand-700 hover:bg-brand-100"
                                        >
                                            <AppIcon name="calendar" class="size-3" />
                                            Từ mẫu định kỳ
                                        </Link>
                                        <span
                                            v-else-if="task.recurrence"
                                            class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-500"
                                        >
                                            <AppIcon name="calendar" class="size-3" />
                                            Từ mẫu định kỳ (đã xóa)
                                        </span>
                                    </div>
                                </td>
                                <td class="px-2 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-bold"
                                        :class="taskStatusClasses[task.status]"
                                    >
                                        {{ taskStatusLabels[task.status] }}
                                    </span>
                                </td>
                                <td class="px-2 py-4">
                                    <AppTaskPriorityBadge :priority="task.priority" />
                                </td>
                                <td class="px-2 py-4">
                                    <div v-if="task.assignee" class="flex items-center gap-2">
                                        <AppUserAvatar
                                            :name="task.assignee.name"
                                            :avatar-url="task.assignee.avatar_url"
                                            size="sm"
                                        />
                                        <span class="text-sm font-medium text-slate-700">
                                            {{ task.assignee.name }}
                                            <span
                                                v-if="task.assignee.id === page.props.auth.user.id"
                                                class="font-semibold text-brand-700"
                                            >
                                                (Bạn)
                                            </span>
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-slate-400">Chưa phân công</span>
                                </td>
                                <td class="px-2 py-4 text-sm font-medium text-slate-600">
                                    {{ task.organization_unit?.name }}
                                </td>
                                <td class="px-2 py-4 text-sm font-medium text-slate-600">
                                    <Link
                                        v-if="task.project"
                                        :href="route('projects.show', task.project.id)"
                                        class="hover:text-brand-700"
                                    >
                                        {{ task.project.name }}
                                    </Link>
                                    <span v-else class="text-xs text-slate-400">Không có</span>
                                </td>
                                <td class="px-2 py-4">
                                    <p
                                        class="text-xs font-semibold"
                                        :class="task.is_overdue ? 'text-red-700' : 'text-slate-500'"
                                    >
                                        {{ formatDueDate(task.due_at) }}
                                    </p>
                                </td>
                                <td class="px-2 py-4 text-sm font-medium text-slate-600">
                                    {{ task.creator?.name }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav
                    v-if="tasks.last_page > 1"
                    class="flex flex-wrap items-center justify-center gap-1 border-t border-slate-100 px-5 py-4"
                    aria-label="Phân trang công việc"
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
    </AuthenticatedLayout>
</template>
