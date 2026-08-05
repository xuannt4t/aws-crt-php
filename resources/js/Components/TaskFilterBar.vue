<script setup lang="ts">
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { taskPriorityLabels, taskStatusLabels } from '@/Constants/task';
import { usePage } from '@inertiajs/vue3';
import MultiSelect from 'primevue/multiselect';
import type {
    OrganizationUnit,
    PageProps,
    Project,
    TaskFilterKey,
    TaskIndexFilters,
    TaskPriority,
    TaskStatus,
    UserOption,
} from '@/types';

const props = defineProps<{
    filters: TaskIndexFilters;
    availableFilters: TaskFilterKey[];
    statuses: TaskStatus[];
    priorities: TaskPriority[];
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
    users: UserOption[];
    projects: Pick<Project, 'id' | 'name' | 'code'>[];
}>();

const emit = defineEmits<{
    apply: [
        payload: {
            search?: string;
            status?: string;
            priority?: string;
            organization_unit_id?: number;
            project_id?: number;
            assignee_ids?: number[];
        },
    ];
    reset: [];
}>();

const page = usePage<PageProps>();

// Khúc 2 (spec §6.2): giao diện gọn, một hàng ngang thu gọn được. Mặc định mở
// khi đang có ít nhất một bộ lọc áp dụng để người dùng thấy ngay điều kiện
// đang lọc, còn lại thì thu gọn cho gọn màn hình.
const isExpanded = ref(hasAnyActiveFilter());

function hasAnyActiveFilter(): boolean {
    return activeCount(props.filters) > 0;
}

function activeCount(filters: TaskIndexFilters): number {
    let count = 0;

    if (props.availableFilters.includes('search') && filters.search) {
        count += 1;
    }
    if (props.availableFilters.includes('status') && filters.status) {
        count += 1;
    }
    if (props.availableFilters.includes('priority') && filters.priority) {
        count += 1;
    }
    if (props.availableFilters.includes('organization_unit_id') && filters.organization_unit_id) {
        count += 1;
    }
    if (props.availableFilters.includes('project_id') && filters.project_id) {
        count += 1;
    }
    if (props.availableFilters.includes('assignee_ids') && (filters.assignee_ids?.length ?? 0) > 0) {
        count += 1;
    }

    return count;
}

const activeFilterCount = computed(() => activeCount(props.filters));

const search = ref(props.filters.search ?? '');
const status = ref<TaskStatus | ''>(props.filters.status ?? '');
const priority = ref<TaskPriority | ''>(props.filters.priority ?? '');
const organizationUnitId = ref<number | ''>(props.filters.organization_unit_id ?? '');
const projectId = ref<number | ''>(props.filters.project_id ?? '');
const assigneeIds = ref<number[]>(props.filters.assignee_ids ?? []);

const assigneeOptions = computed(() =>
    props.users.map((user) => ({
        ...user,
        display_name: user.id === page.props.auth.user.id ? `${user.name} (Bạn)` : user.name,
    })),
);

const handleApply = () => {
    emit('apply', {
        search: search.value || undefined,
        status: status.value || undefined,
        priority: priority.value || undefined,
        organization_unit_id: organizationUnitId.value || undefined,
        project_id: projectId.value || undefined,
        assignee_ids: assigneeIds.value.length > 0 ? assigneeIds.value : undefined,
    });
};

const handleReset = () => {
    search.value = '';
    status.value = '';
    priority.value = '';
    organizationUnitId.value = '';
    projectId.value = '';
    assigneeIds.value = [];
    emit('reset');
};
</script>

<template>
    <section class="app-panel p-4 sm:p-5">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 text-left"
            :aria-expanded="isExpanded"
            @click="isExpanded = !isExpanded"
        >
            <span class="flex items-center gap-2">
                <AppIcon name="filter" class="size-4 text-slate-500" />
                <span class="text-sm font-bold text-ink-950">Bộ lọc</span>
                <span
                    v-if="activeFilterCount > 0"
                    class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700"
                >
                    {{ activeFilterCount }} đang áp dụng
                </span>
                <span v-else class="text-xs font-medium text-slate-400">Chưa áp dụng bộ lọc nào</span>
            </span>
            <AppIcon :name="isExpanded ? 'chevron-down' : 'chevron-right'" class="size-4 shrink-0 text-slate-400" />
        </button>

        <form v-show="isExpanded" class="mt-4 max-h-[25vh] overflow-y-auto" @submit.prevent="handleApply">
            <div class="flex flex-wrap items-end gap-3">
                <template v-for="key in props.availableFilters" :key="key">
                    <label v-if="key === 'search'" class="min-w-[220px] flex-1 basis-56">
                        <span class="mb-1.5 block text-xs font-bold text-slate-600">Tìm kiếm</span>
                        <input v-model="search" type="search" class="app-field" placeholder="Tiêu đề công việc" />
                    </label>

                    <label v-else-if="key === 'status'" class="w-44">
                        <span class="mb-1.5 block text-xs font-bold text-slate-600">Trạng thái</span>
                        <select v-model="status" class="app-field">
                            <option value="">Tất cả</option>
                            <option v-for="item in props.statuses" :key="item" :value="item">
                                {{ taskStatusLabels[item] }}
                            </option>
                        </select>
                    </label>

                    <label v-else-if="key === 'priority'" class="w-40">
                        <span class="mb-1.5 block text-xs font-bold text-slate-600">Ưu tiên</span>
                        <select v-model="priority" class="app-field">
                            <option value="">Tất cả</option>
                            <option v-for="item in props.priorities" :key="item" :value="item">
                                {{ taskPriorityLabels[item] }}
                            </option>
                        </select>
                    </label>

                    <label v-else-if="key === 'organization_unit_id'" class="w-48">
                        <span class="mb-1.5 block text-xs font-bold text-slate-600">Phòng ban</span>
                        <select v-model="organizationUnitId" class="app-field">
                            <option value="">Tất cả</option>
                            <option v-for="unit in props.organizationUnits" :key="unit.id" :value="unit.id">
                                {{ unit.name }}
                            </option>
                        </select>
                    </label>

                    <label v-else-if="key === 'project_id'" class="w-48">
                        <span class="mb-1.5 block text-xs font-bold text-slate-600">Dự án</span>
                        <select v-model="projectId" class="app-field">
                            <option value="">Tất cả</option>
                            <option v-for="project in props.projects" :key="project.id" :value="project.id">
                                {{ project.code }} · {{ project.name }}
                            </option>
                        </select>
                    </label>

                    <label v-else-if="key === 'assignee_ids'" class="min-w-[220px] flex-1 basis-56">
                        <span class="mb-1.5 block text-xs font-bold text-slate-600">Người phụ trách</span>
                        <MultiSelect
                            v-model="assigneeIds"
                            :options="assigneeOptions"
                            option-label="display_name"
                            option-value="id"
                            display="chip"
                            filter
                            :max-selected-labels="2"
                            selected-items-label="{0} người đã chọn"
                            placeholder="Chọn một hoặc nhiều người"
                            class="w-full"
                            aria-label="Lọc theo người phụ trách"
                        />
                    </label>
                </template>

                <div class="ml-auto flex gap-2">
                    <button type="button" class="app-button-secondary" @click="handleReset">Xoá lọc</button>
                    <button type="submit" class="app-button-primary">
                        <AppIcon name="filter" class="size-4" />
                        Áp dụng
                    </button>
                </div>
            </div>
        </form>
    </section>
</template>
