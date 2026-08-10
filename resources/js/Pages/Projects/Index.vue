<script setup lang="ts">
import { computed, ref } from 'vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import { navigateRow } from '@/Support/rowNavigation';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppProjectStatusBadge from '@/Components/AppProjectStatusBadge.vue';
import ProjectProgressBar from '@/Components/ProjectProgressBar.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { projectStatusLabels } from '@/Constants/project';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { OrganizationUnit, Project, ProjectStatus, UserOption } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedProjects {
    data: Project[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    projects: PaginatedProjects;
    filters: {
        search?: string;
        status?: ProjectStatus;
        organization_unit_id?: number;
        owner_id?: number;
        only_mine?: boolean | string;
    };
    statuses: ProjectStatus[];
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    users: UserOption[];
}>();

const { can } = usePermissions();
const canCreate = computed(() => can('project.create'));

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const organizationUnitId = ref<number | ''>(props.filters.organization_unit_id ?? '');
const ownerId = ref<number | ''>(props.filters.owner_id ?? '');
const onlyMine = ref(props.filters.only_mine === true || props.filters.only_mine === '1');
const isLoading = ref(false);

const handleFilter = () => {
    router.get(
        route('projects.index'),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            organization_unit_id: organizationUnitId.value || undefined,
            owner_id: ownerId.value || undefined,
            only_mine: onlyMine.value ? 1 : undefined,
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
    organizationUnitId.value = '';
    ownerId.value = '';
    onlyMine.value = false;
    handleFilter();
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
    <Head title="Việc dự án" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Việc dự án"
                description="Theo dõi tiến độ, thành viên và công việc thuộc từng việc dự án."
                eyebrow="Project Core"
            >
                <template v-if="canCreate" #actions>
                    <Link :href="route('projects.create')" class="app-button-primary">
                        <AppIcon name="plus" class="size-4" />
                        Tạo việc dự án
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <form class="app-panel mb-5 p-5 sm:p-6" @submit.prevent="handleFilter">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <label class="xl:col-span-2">
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Tìm kiếm</span>
                    <input v-model="search" type="search" class="app-field" placeholder="Mã hoặc tên việc dự án" />
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Trạng thái</span>
                    <select v-model="status" class="app-field">
                        <option value="">Tất cả</option>
                        <option v-for="item in statuses" :key="item" :value="item">
                            {{ projectStatusLabels[item] }}
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
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Chủ việc dự án</span>
                    <select v-model="ownerId" class="app-field">
                        <option value="">Tất cả</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
                    <input
                        v-model="onlyMine"
                        type="checkbox"
                        class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                    />
                    Chỉ việc dự án của tôi
                </label>
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
                <h2 class="font-display text-base font-bold text-ink-950">Danh sách việc dự án</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ projects.total }} việc dự án · hiển thị {{ projects.from ?? 0 }}–{{ projects.to ?? 0 }}
                </p>
            </div>

            <AppEmptyState
                v-if="projects.data.length === 0"
                icon="folder"
                title="Chưa có việc dự án phù hợp"
                description="Thay đổi bộ lọc hoặc tạo việc dự án đầu tiên để bắt đầu vận hành."
            >
                <template v-if="canCreate" #action>
                    <Link :href="route('projects.create')" class="app-button-primary">
                        <AppIcon name="plus" class="size-4" />
                        Tạo việc dự án
                    </Link>
                </template>
            </AppEmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[980px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-xs font-bold text-slate-500">
                            <th class="px-5 py-3 sm:px-6">Mã</th>
                            <th class="px-5 py-3">Tên việc dự án</th>
                            <th class="px-5 py-3">Đơn vị</th>
                            <th class="px-5 py-3">Chủ việc dự án</th>
                            <th class="px-5 py-3">Trạng thái</th>
                            <th class="px-5 py-3">Tiến độ</th>
                            <th class="px-5 py-3">Công việc</th>
                            <th class="px-5 py-3">Thành viên</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="project in projects.data"
                            :key="project.id"
                            class="app-row-link"
                            @click="navigateRow($event, route('projects.show', project.id))"
                            @auxclick="navigateRow($event, route('projects.show', project.id))"
                        >
                            <td class="px-2 py-4 text-sm font-bold text-slate-700 sm:px-6">
                                {{ project.code }}
                            </td>
                            <td class="max-w-xs px-5 py-4">
                                <Link
                                    :href="route('projects.show', project.id)"
                                    class="block truncate text-sm font-bold text-slate-800 hover:text-brand-700"
                                >
                                    {{ project.name }}
                                </Link>
                            </td>
                            <td class="px-2 py-4 text-sm font-medium text-slate-600">
                                {{ project.organization_unit?.name }}
                            </td>
                            <td class="px-2 py-4 text-sm font-medium text-slate-600">
                                {{ project.owner?.name }}
                            </td>
                            <td class="px-2 py-4">
                                <AppProjectStatusBadge :status="project.status" />
                            </td>
                            <td class="w-40 px-5 py-4">
                                <ProjectProgressBar :progress="project.progress ?? 0" />
                            </td>
                            <td class="px-2 py-4 text-sm font-semibold text-slate-600">
                                {{ project.task_count ?? 0 }}
                                <span v-if="project.open_task_count" class="text-xs font-normal text-slate-400">
                                    ({{ project.open_task_count }} mở)
                                </span>
                            </td>
                            <td class="px-2 py-4 text-sm font-semibold text-slate-600">
                                {{ project.member_count ?? 0 }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="projects.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-slate-100 px-5 py-4"
                aria-label="Phân trang việc dự án"
            >
                <template v-for="link in projects.links" :key="link.label">
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
    </AuthenticatedLayout>
</template>
