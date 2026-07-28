<script setup lang="ts">
import { ref } from 'vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import Modal from '@/Components/Modal.vue';
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
const selectedLog = ref<AuditLog | null>(null);

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

const openDetails = (log: AuditLog) => {
    selectedLog.value = log;
};

const closeDetails = () => {
    selectedLog.value = null;
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
                    <input v-model="search" type="search" class="app-field" placeholder="Tìm theo tên hoặc email" />
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
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-bold text-brand-700 transition hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2"
                                    :aria-label="`Xem thay đổi của bản ghi ${log.id}`"
                                    @click="openDetails(log)"
                                >
                                    <AppIcon name="chevron-right" class="size-3.5" />
                                    Xem thay đổi
                                </button>
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

        <Modal :show="selectedLog !== null" max-width="2xl" @close="closeDetails">
            <div v-if="selectedLog" class="flex max-h-[85vh] flex-col">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-5 sm:px-6">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-brand-700">Chi tiết nhật ký</p>
                        <h2 class="mt-1 font-display text-xl font-bold tracking-[-0.02em] text-ink-950">
                            {{ actionLabels[selectedLog.action] ?? selectedLog.action }}
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ subjectLabel(selectedLog) }} · {{ formatDateTime(selectedLog.created_at) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="app-icon-button shrink-0"
                        aria-label="Đóng chi tiết nhật ký"
                        title="Đóng"
                        @click="closeDetails"
                    >
                        <AppIcon name="x" class="size-4" />
                    </button>
                </div>

                <div class="overflow-y-auto px-5 py-5 sm:px-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <section class="min-w-0">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                Dữ liệu trước thay đổi
                            </p>
                            <pre
                                class="max-h-[52vh] min-h-40 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-950 p-4 text-xs leading-5 text-slate-200"
                                >{{ formatValues(selectedLog.before_values) }}</pre>
                        </section>
                        <section class="min-w-0">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                Dữ liệu sau thay đổi
                            </p>
                            <pre
                                class="max-h-[52vh] min-h-40 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-950 p-4 text-xs leading-5 text-slate-200"
                                >{{ formatValues(selectedLog.after_values) }}</pre>
                        </section>
                    </div>

                    <div
                        class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500"
                    >
                        <span>
                            Thực hiện bởi:
                            <strong class="font-semibold text-slate-700">
                                {{ selectedLog.actor?.name ?? 'Tài khoản đã xóa' }}
                            </strong>
                        </span>
                        <span v-if="selectedLog.ip_address">IP: {{ selectedLog.ip_address }}</span>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 px-5 py-4 sm:px-6">
                    <button type="button" class="app-button-secondary" @click="closeDetails">Đóng</button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
