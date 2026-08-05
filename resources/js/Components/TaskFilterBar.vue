<script setup lang="ts">
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { taskPriorityLabels, taskStatusBucketLabels, taskStatusLabels } from '@/Constants/task';
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
    TaskStatusBucket,
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

// Cùng kiểu với bộ lọc mà server nhận, thay vì liệt kê lại một tập con — liệt
// kê lại là thêm một chỗ nữa phải nhớ đồng bộ mỗi khi có bộ lọc mới.
const emit = defineEmits<{
    apply: [payload: TaskIndexFilters];
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
    // `status` và `bucket` là hai cách diễn đạt cùng một trục, gộp chung trong ô
    // "Trạng thái" nên chỉ đếm là một.
    if (props.availableFilters.includes('status') && (filters.status || filters.bucket)) {
        count += 1;
    }
    if (filters.overdue) {
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
/**
 * Ô "Trạng thái" nhận cả nhóm (từ ô tóm tắt) lẫn trạng thái đơn lẻ, phân biệt
 * bằng tiền tố. Gộp vào một ô để hai thứ KHÔNG THỂ mâu thuẫn nhau: trước đây
 * bấm ô "Đang làm" xong áp bộ lọc trạng thái "Hoàn thành" sẽ ra danh sách rỗng
 * mà không hiểu vì sao, còn bấm "Áp dụng" thì mất luôn ô đang chọn vì thanh lọc
 * gửi lại query không kèm `bucket`.
 */
const statusSelection = ref<string>(
    props.filters.bucket
        ? `bucket:${props.filters.bucket}`
        : props.filters.status
          ? `status:${props.filters.status}`
          : '',
);

const onlyOverdue = ref<boolean>(Boolean(props.filters.overdue));

const buckets: TaskStatusBucket[] = ['not_started', 'in_progress', 'waiting_approval', 'completed'];

const splitStatusSelection = (): Pick<TaskIndexFilters, 'status' | 'bucket'> => {
    const [kind, value] = statusSelection.value.split(':');

    if (kind === 'bucket') {
        return { bucket: value as TaskStatusBucket };
    }

    if (kind === 'status') {
        return { status: value as TaskStatus };
    }

    return {};
};
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
        ...splitStatusSelection(),
        overdue: onlyOverdue.value || undefined,
        priority: priority.value || undefined,
        organization_unit_id: organizationUnitId.value || undefined,
        project_id: projectId.value || undefined,
        assignee_ids: assigneeIds.value.length > 0 ? assigneeIds.value : undefined,
    });
};

const handleReset = () => {
    search.value = '';
    statusSelection.value = '';
    onlyOverdue.value = false;
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
                        <select v-model="statusSelection" class="app-field">
                            <option value="">Tất cả</option>
                            <optgroup label="Theo ô thống kê">
                                <option v-for="item in buckets" :key="item" :value="`bucket:${item}`">
                                    {{ taskStatusBucketLabels[item] }}
                                </option>
                            </optgroup>
                            <optgroup label="Từng trạng thái">
                                <option v-for="item in props.statuses" :key="item" :value="`status:${item}`">
                                    {{ taskStatusLabels[item] }}
                                </option>
                            </optgroup>
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

                <!--
                    Trễ hạn cắt ngang mọi trạng thái nên không thể nằm trong ô
                    "Trạng thái" — nó là điều kiện độc lập, chọn kèm được.
                -->
                <label class="flex items-center gap-2 pb-2.5">
                    <input
                        v-model="onlyOverdue"
                        type="checkbox"
                        class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                    />
                    <span class="text-xs font-bold text-slate-600">Chỉ việc trễ hạn</span>
                </label>

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
