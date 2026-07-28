<script setup lang="ts">
import { computed, ref } from 'vue';
import AppActionButton from '@/Components/AppActionButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppStatusBadge from '@/Components/AppStatusBadge.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import { usePermissions } from '@/Composables/usePermissions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Select from 'primevue/select';
import type { OrganizationUnit, PageProps, User } from '@/types';

const props = defineProps<{
    users: User[];
    organizationUnits: Pick<OrganizationUnit, 'id' | 'name'>[];
}>();

const page = usePage<PageProps>();
const { can } = usePermissions();
const canCreate = computed(() => can('user.create'));
const canUpdate = computed(() => can('user.update'));
const canDisable = computed(() => can('user.disable'));
const selectedUnitId = ref<number | null>(null);
const searchTerm = ref('');

const unitFilterOptions = computed(() => [{ id: null, name: 'Tất cả đơn vị' }, ...props.organizationUnits]);
const activeUserCount = computed(() => props.users.filter((user) => user.is_active).length);
const roleLabels: Record<string, string> = {
    system_admin: 'Quản trị hệ thống',
    director: 'Giám đốc',
    department_manager: 'Quản lý phòng ban',
    project_manager: 'Quản lý dự án',
    employee: 'Nhân viên',
    auditor: 'Kiểm toán viên',
};

const filteredUsers = computed(() => {
    const normalizedSearch = searchTerm.value.trim().toLocaleLowerCase('vi');

    return props.users.filter((user) => {
        const matchesUnit = selectedUnitId.value === null || user.organization_unit_id === selectedUnitId.value;
        const matchesSearch =
            normalizedSearch === '' ||
            user.name.toLocaleLowerCase('vi').includes(normalizedSearch) ||
            user.email.toLocaleLowerCase('vi').includes(normalizedSearch) ||
            (user.employee_code?.toLocaleLowerCase('vi').includes(normalizedSearch) ?? false);

        return matchesUnit && matchesSearch;
    });
});

const toggleActive = (user: User) => {
    const routeName = user.is_active ? 'users.disable' : 'users.enable';
    router.patch(route(routeName, user.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Người dùng" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Người dùng"
                description="Quản lý thành viên, thông tin công việc và trạng thái truy cập hệ thống."
                eyebrow="Quản trị"
            >
                <template v-if="canCreate" #actions>
                    <Link :href="route('users.create')" class="app-button-primary">
                        <AppIcon name="plus" class="size-4" />
                        Thêm người dùng
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <div v-if="users.length > 0" class="mb-5 grid gap-3 sm:grid-cols-3">
            <div class="app-panel px-5 py-4">
                <p class="text-xs font-semibold text-slate-500">Tổng thành viên</p>
                <p class="mt-2 font-display text-2xl font-extrabold text-ink-950">{{ users.length }}</p>
            </div>
            <div class="app-panel px-5 py-4">
                <p class="text-xs font-semibold text-slate-500">Đang hoạt động</p>
                <p class="mt-2 font-display text-2xl font-extrabold text-emerald-700">{{ activeUserCount }}</p>
            </div>
            <div class="app-panel px-5 py-4">
                <p class="text-xs font-semibold text-slate-500">Tạm ngừng</p>
                <p class="mt-2 font-display text-2xl font-extrabold text-slate-500">
                    {{ users.length - activeUserCount }}
                </p>
            </div>
        </div>

        <section class="app-panel overflow-hidden">
            <div
                class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <div>
                    <h2 class="font-display text-base font-bold text-ink-950">Danh sách thành viên</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ filteredUsers.length }} kết quả phù hợp</p>
                </div>

                <div v-if="users.length > 0" class="flex flex-col gap-2 sm:flex-row">
                    <div class="relative">
                        <input
                            v-model="searchTerm"
                            type="search"
                            class="app-field mt-0 min-w-0 pl-9 sm:w-64"
                            placeholder="Tìm tên, email, mã NV..."
                            aria-label="Tìm người dùng"
                        />
                        <svg
                            class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <circle cx="11" cy="11" r="7" />
                            <path d="m20 20-3.5-3.5" />
                        </svg>
                    </div>
                    <Select
                        id="organization-unit-filter"
                        v-model="selectedUnitId"
                        :options="unitFilterOptions"
                        option-label="name"
                        option-value="id"
                        class="w-full sm:w-52"
                        aria-label="Lọc theo đơn vị"
                    />
                </div>
            </div>

            <AppEmptyState
                v-if="users.length === 0"
                icon="users"
                title="Chưa có thành viên nào"
                description="Thêm người dùng đầu tiên và gắn họ vào một đơn vị để bắt đầu."
            >
                <template v-if="canCreate" #action>
                    <Link :href="route('users.create')" class="app-button-primary">
                        <AppIcon name="plus" class="size-4" />
                        Thêm người dùng
                    </Link>
                </template>
            </AppEmptyState>

            <div v-else-if="filteredUsers.length === 0" class="py-12 text-center">
                <p class="text-sm font-semibold text-slate-700">Không tìm thấy kết quả phù hợp</p>
                <button
                    type="button"
                    class="mt-2 text-xs font-semibold text-brand-700 hover:text-brand-800"
                    @click="
                        searchTerm = '';
                        selectedUnitId = null;
                    "
                >
                    Xoá bộ lọc
                </button>
            </div>

            <div v-else class="overflow-x-auto">
                <DataTable :value="filteredUsers" data-key="id" paginator :rows="20" table-style="min-width: 760px">
                    <Column field="name" header="Thành viên">
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <AppUserAvatar :name="data.name" :avatar-url="data.avatar_url" size="sm" />
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-800">{{ data.name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-400">
                                        {{ data.employee_code || 'Chưa có mã nhân viên' }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="email"
                        header="Liên hệ"
                        header-class="hidden sm:table-cell"
                        body-class="hidden sm:table-cell"
                    >
                        <template #body="{ data }">
                            <p class="text-sm text-slate-700">{{ data.email }}</p>
                            <p v-if="data.phone" class="mt-0.5 text-xs text-slate-400">{{ data.phone }}</p>
                        </template>
                    </Column>
                    <Column header="Đơn vị">
                        <template #body="{ data }">
                            <p class="text-sm font-medium text-slate-700">
                                {{ data.organization_unit?.name ?? 'Chưa phân đơn vị' }}
                            </p>
                            <p v-if="data.job_title" class="mt-0.5 text-xs text-slate-400">{{ data.job_title }}</p>
                        </template>
                    </Column>
                    <Column header="Vai trò" header-class="hidden xl:table-cell" body-class="hidden xl:table-cell">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="role in data.roles"
                                    :key="role.id"
                                    class="rounded-full bg-violet-50 px-2 py-1 text-[10px] font-bold text-violet-700"
                                >
                                    {{ roleLabels[role.name] ?? role.name }}
                                </span>
                                <span v-if="!data.roles?.length" class="text-xs text-slate-400">Chưa gán</span>
                            </div>
                        </template>
                    </Column>
                    <Column header="Trạng thái" header-class="hidden lg:table-cell" body-class="hidden lg:table-cell">
                        <template #body="{ data }">
                            <AppStatusBadge
                                :active="data.is_active"
                                active-label="Hoạt động"
                                inactive-label="Đã vô hiệu hoá"
                            />
                        </template>
                    </Column>
                    <Column v-if="canUpdate || canDisable" header="Thao tác" class="w-36">
                        <template #body="{ data }">
                            <div class="flex justify-end gap-1">
                                <AppActionButton
                                    v-if="canUpdate"
                                    :href="route('users.edit', data.id)"
                                    icon="edit"
                                    :label="`Chỉnh sửa ${data.name}`"
                                />
                                <AppActionButton
                                    v-if="canDisable && data.id !== page.props.auth.user.id"
                                    :icon="data.is_active ? 'lock' : 'unlock'"
                                    :tone="data.is_active ? 'warning' : 'success'"
                                    :label="data.is_active ? `Vô hiệu hóa ${data.name}` : `Kích hoạt ${data.name}`"
                                    @click="toggleActive(data)"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
