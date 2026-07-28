<script setup lang="ts">
import { computed, ref } from 'vue';
import AppConfirmDialog from '@/Components/AppConfirmDialog.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppStatusBadge from '@/Components/AppStatusBadge.vue';
import { usePermissions } from '@/Composables/usePermissions';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import TreeTable from 'primevue/treetable';
import Column from 'primevue/column';
import type { OrganizationUnit, PageProps } from '@/types';

interface OrganizationUnitNode {
    key: string;
    data: OrganizationUnit;
    children: OrganizationUnitNode[];
}

const props = defineProps<{
    organizationUnits: OrganizationUnit[];
}>();

const page = usePage<PageProps>();
const { can } = usePermissions();
const canCreate = computed(() => can('organization.create'));
const canUpdate = computed(() => can('organization.update'));
const canDelete = computed(() => can('organization.delete'));
const deleteError = computed(() => (page.props.errors as Record<string, string>).organization_unit);
const unitToDelete = ref<OrganizationUnit | null>(null);

const activeUnitCount = computed(() => props.organizationUnits.filter((unit) => unit.is_active).length);
const rootUnitCount = computed(() => props.organizationUnits.filter((unit) => unit.parent_id === null).length);

const treeNodes = computed<OrganizationUnitNode[]>(() => {
    const byId = new Map<number, OrganizationUnitNode>();

    for (const unit of props.organizationUnits) {
        byId.set(unit.id, { key: String(unit.id), data: unit, children: [] });
    }

    const roots: OrganizationUnitNode[] = [];

    for (const unit of props.organizationUnits) {
        const node = byId.get(unit.id)!;

        if (unit.parent_id !== null && byId.has(unit.parent_id)) {
            byId.get(unit.parent_id)!.children.push(node);
        } else {
            roots.push(node);
        }
    }

    return roots;
});

const destroy = () => {
    if (!unitToDelete.value) {
        return;
    }

    router.delete(route('organization-units.destroy', unitToDelete.value.id), {
        onFinish: () => {
            unitToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head title="Cơ cấu tổ chức" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                title="Cơ cấu tổ chức"
                description="Quản lý các đơn vị, phòng ban và quan hệ phân cấp trong doanh nghiệp."
                eyebrow="Quản trị"
            >
                <template v-if="canCreate" #actions>
                    <Link :href="route('organization-units.create')" class="app-button-primary">
                        <AppIcon name="plus" class="size-4" />
                        Thêm đơn vị
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <InputError
            v-if="deleteError"
            class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3"
            :message="deleteError"
        />

        <div v-if="organizationUnits.length > 0" class="mb-5 grid gap-3 sm:grid-cols-3">
            <div class="app-panel px-5 py-4">
                <p class="text-xs font-semibold text-slate-500">Tổng đơn vị</p>
                <p class="mt-2 font-display text-2xl font-extrabold text-ink-950">{{ organizationUnits.length }}</p>
            </div>
            <div class="app-panel px-5 py-4">
                <p class="text-xs font-semibold text-slate-500">Đơn vị cấp cao nhất</p>
                <p class="mt-2 font-display text-2xl font-extrabold text-ink-950">{{ rootUnitCount }}</p>
            </div>
            <div class="app-panel px-5 py-4">
                <p class="text-xs font-semibold text-slate-500">Đang hoạt động</p>
                <p class="mt-2 font-display text-2xl font-extrabold text-emerald-700">{{ activeUnitCount }}</p>
            </div>
        </div>

        <section class="app-panel overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                <div>
                    <h2 class="font-display text-base font-bold text-ink-950">Sơ đồ phân cấp</h2>
                    <p class="mt-1 text-xs text-slate-500">Mở từng nhánh để xem các đơn vị trực thuộc.</p>
                </div>
            </div>

            <AppEmptyState
                v-if="organizationUnits.length === 0"
                icon="building"
                title="Chưa có đơn vị nào"
                description="Tạo đơn vị gốc đầu tiên để bắt đầu xây dựng cơ cấu tổ chức."
            >
                <template v-if="canCreate" #action>
                    <Link :href="route('organization-units.create')" class="app-button-primary">
                        <AppIcon name="plus" class="size-4" />
                        Tạo đơn vị gốc
                    </Link>
                </template>
            </AppEmptyState>

            <div v-else class="overflow-x-auto">
                <TreeTable :value="treeNodes" table-style="min-width: 640px">
                    <Column field="name" header="Tên đơn vị" expander>
                        <template #body="{ node }">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-800">{{ node.data.name }}</p>
                                <p class="mt-0.5 text-xs text-slate-400 sm:hidden">{{ node.data.code }}</p>
                            </div>
                        </template>
                    </Column>
                    <Column
                        field="code"
                        header="Mã"
                        header-class="hidden sm:table-cell"
                        body-class="hidden sm:table-cell"
                    >
                        <template #body="{ node }">
                            <code class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">
                                {{ node.data.code }}
                            </code>
                        </template>
                    </Column>
                    <Column header="Trạng thái" header-class="hidden md:table-cell" body-class="hidden md:table-cell">
                        <template #body="{ node }">
                            <AppStatusBadge :active="node.data.is_active" />
                        </template>
                    </Column>
                    <Column v-if="canUpdate || canDelete" header="Thao tác" class="w-28">
                        <template #body="{ node }">
                            <div class="flex justify-end gap-1">
                                <Link
                                    v-if="canUpdate"
                                    :href="route('organization-units.edit', node.data.id)"
                                    class="app-icon-button"
                                    :aria-label="`Sửa ${node.data.name}`"
                                    title="Sửa"
                                >
                                    <AppIcon name="edit" class="size-4" />
                                </Link>
                                <button
                                    v-if="canDelete"
                                    type="button"
                                    class="app-icon-button hover:bg-red-50 hover:text-red-600"
                                    :aria-label="`Xoá ${node.data.name}`"
                                    title="Xoá"
                                    @click="unitToDelete = node.data"
                                >
                                    <AppIcon name="trash" class="size-4" />
                                </button>
                            </div>
                        </template>
                    </Column>
                </TreeTable>
            </div>
        </section>

        <AppConfirmDialog
            :show="unitToDelete !== null"
            title="Xoá đơn vị?"
            :description="`Đơn vị “${unitToDelete?.name ?? ''}” sẽ được chuyển vào trạng thái đã xoá. Hành động có thể bị từ chối nếu đơn vị vẫn còn dữ liệu liên quan.`"
            confirm-label="Xoá đơn vị"
            @cancel="unitToDelete = null"
            @confirm="destroy"
        />
    </AuthenticatedLayout>
</template>
