<script setup lang="ts">
import { ref } from 'vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import { navigateRow } from '@/Support/rowNavigation';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppStatusBadge from '@/Components/AppStatusBadge.vue';
import AppTaskPriorityBadge from '@/Components/AppTaskPriorityBadge.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { OrganizationUnit, Project, RecurrenceFrequency, TaskRecurrence, User, UserOption } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface RecurrenceListItem extends TaskRecurrence {
    cadence: string;
    next_occurrence: string | null;
    organization_unit?: Pick<OrganizationUnit, 'id' | 'name'>;
    project?: Pick<Project, 'id' | 'name' | 'code'> | null;
    assignee?: Pick<User, 'id' | 'name' | 'avatar_url'> | null;
}

interface PaginatedRecurrences {
    data: RecurrenceListItem[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    recurrences: PaginatedRecurrences;
    filters: {
        search?: string;
        organization_unit_id?: number;
        assignee_id?: number;
        frequency?: RecurrenceFrequency;
        is_active?: boolean | string;
    };
    frequencies: { value: RecurrenceFrequency; label: string }[];
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    users: UserOption[];
    can: {
        create: boolean;
    };
}>();

const search = ref(props.filters.search ?? '');
const organizationUnitId = ref<number | ''>(props.filters.organization_unit_id ?? '');
const assigneeId = ref<number | ''>(props.filters.assignee_id ?? '');
const frequency = ref(props.filters.frequency ?? '');
const isActive = ref(
    props.filters.is_active === true || props.filters.is_active === '1'
        ? '1'
        : props.filters.is_active === false || props.filters.is_active === '0'
          ? '0'
          : '',
);
const isLoading = ref(false);
const togglingId = ref<number | null>(null);

const handleFilter = () => {
    router.get(
        route('task-recurrences.index'),
        {
            search: search.value || undefined,
            organization_unit_id: organizationUnitId.value || undefined,
            assignee_id: assigneeId.value || undefined,
            frequency: frequency.value || undefined,
            is_active: isActive.value || undefined,
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
    organizationUnitId.value = '';
    assigneeId.value = '';
    frequency.value = '';
    isActive.value = '';
    handleFilter();
};

const handleToggle = (recurrence: RecurrenceListItem) => {
    togglingId.value = recurrence.id;
    router.patch(
        route('task-recurrences.toggle', recurrence.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                togglingId.value = null;
            },
        },
    );
};

const formatDate = (value: string | null) => {
    if (!value) {
        return 'Không còn kỳ kế tiếp';
    }

    return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium' }).format(new Date(value));
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
    <Head title="Việc định kỳ" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Việc định kỳ"
                description="Quản lý các mẫu công việc lặp lại và theo dõi kỳ sinh kế tiếp."
                eyebrow="Task Core"
            >
                <template v-if="can.create" #actions>
                    <Link :href="route('task-recurrences.create')" class="app-button-primary">
                        <AppIcon name="plus" class="size-4" />
                        Tạo mẫu
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <form class="app-panel mb-5 p-5 sm:p-6" @submit.prevent="handleFilter">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <label class="xl:col-span-2">
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Tìm kiếm</span>
                    <input v-model="search" type="search" class="app-field" placeholder="Tiêu đề mẫu" />
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
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Người phụ trách</span>
                    <select v-model="assigneeId" class="app-field">
                        <option value="">Tất cả</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Chu kỳ</span>
                    <select v-model="frequency" class="app-field">
                        <option value="">Tất cả</option>
                        <option v-for="item in frequencies" :key="item.value" :value="item.value">
                            {{ item.label }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                <label class="min-w-0">
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Trạng thái</span>
                    <select v-model="isActive" class="app-field">
                        <option value="">Tất cả</option>
                        <option value="1">Đang bật</option>
                        <option value="0">Đã tắt</option>
                    </select>
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
                <h2 class="font-display text-base font-bold text-ink-950">Danh sách mẫu</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ recurrences.total }} mẫu · hiển thị {{ recurrences.from ?? 0 }}–{{ recurrences.to ?? 0 }}
                </p>
            </div>

            <AppEmptyState
                v-if="recurrences.data.length === 0"
                icon="calendar"
                title="Chưa có mẫu công việc định kỳ"
                description="Thay đổi bộ lọc hoặc tạo mẫu đầu tiên để bắt đầu sinh công việc tự động."
            >
                <template v-if="can.create" #action>
                    <Link :href="route('task-recurrences.create')" class="app-button-primary">
                        <AppIcon name="plus" class="size-4" />
                        Tạo mẫu
                    </Link>
                </template>
            </AppEmptyState>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[1100px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-xs font-bold text-slate-500">
                            <th class="px-5 py-3 sm:px-6">Mẫu</th>
                            <th class="px-5 py-3">Chu kỳ</th>
                            <th class="px-5 py-3">Đơn vị</th>
                            <th class="px-5 py-3">Dự án</th>
                            <th class="px-5 py-3">Phụ trách</th>
                            <th class="px-5 py-3">Kỳ kế tiếp</th>
                            <th class="px-5 py-3 text-right">Bật/tắt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="recurrence in recurrences.data"
                            :key="recurrence.id"
                            class="app-row-link"
                            @click="navigateRow($event, route('task-recurrences.show', recurrence.id))"
                            @auxclick="navigateRow($event, route('task-recurrences.show', recurrence.id))"
                        >
                            <td class="max-w-md px-5 py-4 sm:px-6">
                                <Link
                                    :href="route('task-recurrences.show', recurrence.id)"
                                    class="block truncate text-sm font-bold text-slate-800 hover:text-brand-700"
                                >
                                    {{ recurrence.title }}
                                </Link>
                                <div class="mt-1.5 flex items-center gap-2 text-xs">
                                    <AppTaskPriorityBadge :priority="recurrence.priority" />
                                </div>
                            </td>
                            <td class="px-2 py-4 text-sm font-medium text-slate-600">
                                {{ recurrence.cadence }}
                            </td>
                            <td class="px-2 py-4 text-sm font-medium text-slate-600">
                                {{ recurrence.organization_unit?.name }}
                            </td>
                            <td class="px-2 py-4 text-sm font-medium text-slate-600">
                                <Link
                                    v-if="recurrence.project"
                                    :href="route('projects.show', recurrence.project.id)"
                                    class="hover:text-brand-700"
                                >
                                    {{ recurrence.project.name }}
                                </Link>
                                <span v-else class="text-xs text-slate-400">Không có</span>
                            </td>
                            <td class="px-2 py-4 text-sm font-medium text-slate-600">
                                {{ recurrence.assignee?.name ?? 'Chưa phân công' }}
                            </td>
                            <td class="px-2 py-4 text-sm font-semibold text-slate-600">
                                {{ formatDate(recurrence.next_occurrence) }}
                            </td>
                            <td class="px-2 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <AppStatusBadge :active="recurrence.is_active" />
                                    <button
                                        type="button"
                                        class="app-button-secondary px-3 py-1.5 text-xs"
                                        :disabled="togglingId === recurrence.id"
                                        @click="handleToggle(recurrence)"
                                    >
                                        {{ recurrence.is_active ? 'Tạm dừng' : 'Kích hoạt' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="recurrences.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-slate-100 px-5 py-4"
                aria-label="Phân trang việc định kỳ"
            >
                <template v-for="link in recurrences.links" :key="link.label">
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
