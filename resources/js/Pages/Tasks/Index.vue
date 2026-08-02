<script setup lang="ts">
import { computed, ref } from 'vue';
import AppActionButton from '@/Components/AppActionButton.vue';
import AppConfirmDialog from '@/Components/AppConfirmDialog.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppTaskPriorityBadge from '@/Components/AppTaskPriorityBadge.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { taskPriorityLabels, taskStatusClasses, taskStatusLabels } from '@/Constants/task';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import MultiSelect from 'primevue/multiselect';
import type { OrganizationUnit, PageProps, Project, Task, TaskPriority, TaskStatus, User } from '@/types';

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
    filters: {
        search?: string;
        status?: TaskStatus;
        priority?: TaskPriority;
        organization_unit_id?: number;
        project_id?: number;
        assignee_ids?: number[];
        overdue?: boolean | string;
    };
    statuses: TaskStatus[];
    priorities: TaskPriority[];
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    users: Pick<User, 'id' | 'name'>[];
    projects: Pick<Project, 'id' | 'name' | 'code'>[];
}>();

const page = usePage<PageProps>();
const { can } = usePermissions();
const canCreate = computed(() => can('task.create'));
const canUpdate = computed(() => can('task.update'));
const canDelete = computed(() => can('task.delete'));

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const priority = ref(props.filters.priority ?? '');
const organizationUnitId = ref<number | ''>(props.filters.organization_unit_id ?? '');
const projectId = ref<number | ''>(props.filters.project_id ?? '');
const assigneeIds = ref<number[]>(props.filters.assignee_ids ?? []);
const assigneeOptions = computed(() =>
    props.users.map((user) => ({
        ...user,
        display_name: user.id === page.props.auth.user.id ? `${user.name} (Bạn)` : user.name,
    })),
);
const onlyMyTasks = computed({
    get: () => assigneeIds.value.length === 1 && assigneeIds.value[0] === page.props.auth.user.id,
    set: (checked: boolean) => {
        assigneeIds.value = checked ? [page.props.auth.user.id] : [];
    },
});
const overdue = ref(props.filters.overdue === true || props.filters.overdue === '1');
const isLoading = ref(false);
const taskToDelete = ref<Task | null>(null);

const handleFilter = () => {
    router.get(
        route('tasks.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            priority: priority.value || undefined,
            organization_unit_id: organizationUnitId.value || undefined,
            project_id: projectId.value || undefined,
            assignee_ids: assigneeIds.value.length > 0 ? assigneeIds.value : undefined,
            overdue: overdue.value ? 1 : undefined,
        },
        {
            preserveState: true,
            replace: true,
            onStart: () => {
                isLoading.value = true;
            },
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
};

const handleReset = () => {
    search.value = '';
    status.value = '';
    priority.value = '';
    organizationUnitId.value = '';
    projectId.value = '';
    assigneeIds.value = [];
    overdue.value = false;
    handleFilter();
};

const handleDelete = () => {
    if (!taskToDelete.value) {
        return;
    }

    router.delete(route('tasks.destroy', taskToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            taskToDelete.value = null;
        },
    });
};

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
    <Head title="Công việc" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Công việc"
                description="Theo dõi đầu việc, người phụ trách, thời hạn và trạng thái thực hiện."
                eyebrow="Task Core"
            >
                <template v-if="canCreate" #actions>
                    <Link :href="route('tasks.create')" class="app-button-primary">
                        <AppIcon name="plus" class="size-4" />
                        Tạo công việc
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <form class="app-panel mb-5 p-5 sm:p-6" @submit.prevent="handleFilter">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <label class="xl:col-span-2">
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Tìm kiếm</span>
                    <input v-model="search" type="search" class="app-field" placeholder="Tiêu đề công việc" />
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Trạng thái</span>
                    <select v-model="status" class="app-field">
                        <option value="">Tất cả</option>
                        <option v-for="item in statuses" :key="item" :value="item">
                            {{ taskStatusLabels[item] }}
                        </option>
                    </select>
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Ưu tiên</span>
                    <select v-model="priority" class="app-field">
                        <option value="">Tất cả</option>
                        <option v-for="item in priorities" :key="item" :value="item">
                            {{ taskPriorityLabels[item] }}
                        </option>
                    </select>
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Đơn vị</span>
                    <select v-model="organizationUnitId" class="app-field">
                        <option value="">Tất cả</option>
                        <option v-for="unit in organizationUnits" :key="unit.id" :value="unit.id">
                            {{ unit.name }}
                        </option>
                    </select>
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Dự án</span>
                    <select v-model="projectId" class="app-field">
                        <option value="">Tất cả</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">
                            {{ project.code }} · {{ project.name }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="mt-4 grid gap-4 xl:grid-cols-[minmax(320px,1fr)_auto_auto] xl:items-end">
                <label class="block min-w-0">
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Người phụ trách</span>
                    <MultiSelect
                        v-model="assigneeIds"
                        :options="assigneeOptions"
                        option-label="display_name"
                        option-value="id"
                        display="chip"
                        filter
                        :max-selected-labels="3"
                        selected-items-label="{0} người đã chọn"
                        placeholder="Chọn một hoặc nhiều người"
                        class="mt-2 w-full"
                        aria-label="Lọc theo người phụ trách"
                    />
                </label>
                <div class="flex min-h-10 flex-wrap items-center gap-x-4 gap-y-2 xl:mb-px">
                    <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
                        <input
                            v-model="onlyMyTasks"
                            type="checkbox"
                            class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                        />
                        Chỉ công việc của bạn
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
                        <input
                            v-model="overdue"
                            type="checkbox"
                            class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                        />
                        Chỉ công việc quá hạn
                    </label>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="app-button-secondary" :disabled="isLoading" @click="handleReset">
                        Xóa bộ lọc
                    </button>
                    <button type="submit" class="app-button-primary" :disabled="isLoading">
                        <AppIcon name="filter" class="size-4" />
                        {{ isLoading ? 'Đang lọc...' : 'Áp dụng' }}
                    </button>
                </div>
            </div>
        </form>

        <section class="app-panel overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="font-display text-base font-bold text-ink-950">Danh sách công việc</h2>
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
                <table class="w-full min-w-[1080px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-xs font-bold text-slate-500">
                            <th class="px-5 py-3 sm:px-6">Công việc</th>
                            <th class="px-5 py-3">Trạng thái</th>
                            <th class="px-5 py-3">Đơn vị</th>
                            <th class="px-5 py-3">Dự án</th>
                            <th class="px-5 py-3">Phụ trách</th>
                            <th class="px-5 py-3">Thời hạn</th>
                            <th v-if="canUpdate || canDelete" class="px-5 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="task in tasks.data" :key="task.id" class="hover:bg-slate-50/60">
                            <td class="max-w-md px-5 py-4 sm:px-6">
                                <Link
                                    :href="route('tasks.show', task.id)"
                                    class="block truncate text-sm font-bold text-slate-800 hover:text-brand-700"
                                >
                                    {{ task.title }}
                                </Link>
                                <div class="mt-1.5 flex items-center gap-2 text-xs">
                                    <AppTaskPriorityBadge :priority="task.priority" />
                                    <span class="text-slate-300">·</span>
                                    <span class="text-slate-400">Tạo bởi {{ task.creator?.name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                    :class="taskStatusClasses[task.status]"
                                >
                                    {{ taskStatusLabels[task.status] }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm font-medium text-slate-600">
                                {{ task.organization_unit?.name }}
                            </td>
                            <td class="px-5 py-4 text-sm font-medium text-slate-600">
                                <Link
                                    v-if="task.project"
                                    :href="route('projects.show', task.project.id)"
                                    class="hover:text-brand-700"
                                >
                                    {{ task.project.name }}
                                </Link>
                                <span v-else class="text-xs text-slate-400">Không có</span>
                            </td>
                            <td class="px-5 py-4">
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
                            <td class="px-5 py-4">
                                <p
                                    class="text-xs font-semibold"
                                    :class="task.is_overdue ? 'text-red-700' : 'text-slate-500'"
                                >
                                    {{ formatDueDate(task.due_at) }}
                                </p>
                                <p v-if="task.is_overdue" class="mt-1 text-[10px] font-bold uppercase text-red-500">
                                    Quá hạn
                                </p>
                            </td>
                            <td v-if="canUpdate || canDelete" class="px-5 py-4">
                                <div class="flex justify-end gap-1">
                                    <AppActionButton
                                        v-if="canUpdate"
                                        :href="route('tasks.edit', task.id)"
                                        icon="edit"
                                        :label="`Chỉnh sửa ${task.title}`"
                                    />
                                    <AppActionButton
                                        v-if="canDelete"
                                        icon="trash"
                                        tone="danger"
                                        :label="`Xóa ${task.title}`"
                                        @click="taskToDelete = task"
                                    />
                                </div>
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

        <AppConfirmDialog
            :show="taskToDelete !== null"
            title="Xóa công việc?"
            :description="`Công việc “${taskToDelete?.title ?? ''}” sẽ được chuyển vào trạng thái đã xóa.`"
            confirm-label="Xóa công việc"
            @cancel="taskToDelete = null"
            @confirm="handleDelete"
        />
    </AuthenticatedLayout>
</template>
