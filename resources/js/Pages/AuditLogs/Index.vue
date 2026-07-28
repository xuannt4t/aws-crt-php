<script setup lang="ts">
import { ref } from 'vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface AuditActor {
    id: number;
    name: string;
    email: string;
}

interface AuditLog {
    id: number;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    before_values: Record<string, unknown> | null;
    after_values: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
    actor: AuditActor | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedAuditLogs {
    data: AuditLog[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    auditLogs: PaginatedAuditLogs;
    filters: {
        search?: string;
        action?: string;
        date_from?: string;
        date_to?: string;
    };
    actions: string[];
}>();

const search = ref(props.filters.search ?? '');
const action = ref(props.filters.action ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const isLoading = ref(false);

const actionLabels: Record<string, string> = {
    'user.created': 'Tạo người dùng',
    'user.updated': 'Cập nhật người dùng',
    'user.roles_updated': 'Thay đổi vai trò',
    'user.disabled': 'Vô hiệu hóa người dùng',
    'user.enabled': 'Kích hoạt người dùng',
    'user.deleted': 'Xóa người dùng',
    'organization_unit.deleted': 'Xóa đơn vị',
    'task.deleted': 'Xóa công việc',
};

const handleFilter = () => {
    router.get(
        route('audit-logs.index'),
        {
            search: search.value || undefined,
            action: action.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
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
    action.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    handleFilter();
};

const formatDateTime = (value: string) =>
    new Intl.DateTimeFormat('vi-VN', {
        dateStyle: 'short',
        timeStyle: 'medium',
    }).format(new Date(value));

const subjectLabel = (log: AuditLog) => {
    const type = log.subject_type?.split('\\').at(-1);
    const labels: Record<string, string> = {
        User: 'Người dùng',
        OrganizationUnit: 'Đơn vị',
    };

    return `${labels[type ?? ''] ?? type ?? 'Đối tượng'} #${log.subject_id ?? '—'}`;
};

const formatValues = (values: Record<string, unknown> | null) =>
    values ? JSON.stringify(values, null, 2) : 'Không có dữ liệu';

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
    <Head title="Nhật ký hệ thống" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Nhật ký hệ thống"
                description="Theo dõi các thao tác nhạy cảm và thay đổi quyền truy cập trong hệ thống."
                eyebrow="Kiểm soát"
            />
        </template>

        <form class="app-panel mb-5 p-5 sm:p-6" @submit.prevent="handleFilter">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <label class="xl:col-span-2">
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Người thực hiện</span>
                    <input
                        v-model="search"
                        type="search"
                        class="app-field"
                        placeholder="Tìm theo tên hoặc email"
                    />
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Hành động</span>
                    <select v-model="action" class="app-field">
                        <option value="">Tất cả hành động</option>
                        <option v-for="item in actions" :key="item" :value="item">
                            {{ actionLabels[item] ?? item }}
                        </option>
                    </select>
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Từ ngày</span>
                    <input v-model="dateFrom" type="date" class="app-field" />
                </label>
                <label>
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Đến ngày</span>
                    <input v-model="dateTo" type="date" class="app-field" />
                </label>
            </div>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <button type="button" class="app-button-secondary" :disabled="isLoading" @click="handleReset">
                    Xóa bộ lọc
                </button>
                <button type="submit" class="app-button-primary" :disabled="isLoading">
                    <AppIcon name="filter" class="size-4" />
                    {{ isLoading ? 'Đang lọc...' : 'Áp dụng' }}
                </button>
            </div>
        </form>

        <section class="app-panel overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="font-display text-base font-bold text-ink-950">Hoạt động đã ghi nhận</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ auditLogs.total }} bản ghi · hiển thị {{ auditLogs.from ?? 0 }}–{{ auditLogs.to ?? 0 }}
                </p>
            </div>

            <AppEmptyState
                v-if="auditLogs.data.length === 0"
                icon="shield"
                title="Không có nhật ký phù hợp"
                description="Thử thay đổi bộ lọc hoặc thực hiện một thao tác nhạy cảm để tạo bản ghi mới."
            />

            <div v-else class="overflow-x-auto">
                <table class="min-w-[900px] w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-xs font-bold text-slate-500">
                            <th class="px-5 py-3 sm:px-6">Thời gian</th>
                            <th class="px-5 py-3">Người thực hiện</th>
                            <th class="px-5 py-3">Hành động</th>
                            <th class="px-5 py-3">Đối tượng</th>
                            <th class="px-5 py-3">Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="log in auditLogs.data" :key="log.id" class="align-top hover:bg-slate-50/60">
                            <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-500 sm:px-6">
                                {{ formatDateTime(log.created_at) }}
                            </td>
                            <td class="px-5 py-4">
                                <div v-if="log.actor" class="flex items-center gap-2.5">
                                    <AppUserAvatar :name="log.actor.name" size="sm" />
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">{{ log.actor.name }}</p>
                                        <p class="mt-0.5 text-xs text-slate-400">{{ log.actor.email }}</p>
                                    </div>
                                </div>
                                <span v-else class="text-xs text-slate-400">Tài khoản đã xóa</span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700">
                                    {{ actionLabels[log.action] ?? log.action }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-sm font-medium text-slate-700">
                                {{ subjectLabel(log) }}
                            </td>
                            <td class="px-5 py-4">
                                <details class="group">
                                    <summary class="cursor-pointer text-xs font-bold text-brand-700">
                                        Xem thay đổi
                                    </summary>
                                    <div class="mt-3 grid max-w-xl gap-3 lg:grid-cols-2">
                                        <div>
                                            <p class="mb-1 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                                Trước
                                            </p>
                                            <pre
                                                class="max-h-48 overflow-auto rounded-lg bg-slate-950 p-3 text-[10px] leading-5 text-slate-200"
                                            >{{ formatValues(log.before_values) }}</pre>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                                Sau
                                            </p>
                                            <pre
                                                class="max-h-48 overflow-auto rounded-lg bg-slate-950 p-3 text-[10px] leading-5 text-slate-200"
                                            >{{ formatValues(log.after_values) }}</pre>
                                        </div>
                                    </div>
                                    <p v-if="log.ip_address" class="mt-2 text-[10px] text-slate-400">
                                        IP: {{ log.ip_address }}
                                    </p>
                                </details>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="auditLogs.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-slate-100 px-5 py-4"
                aria-label="Phân trang nhật ký"
            >
                <template v-for="link in auditLogs.links" :key="link.label">
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
