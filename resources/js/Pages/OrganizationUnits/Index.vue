<script setup lang="ts">
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import TreeTable from 'primevue/treetable';
import Column from 'primevue/column';
import Button from 'primevue/button';
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
const canManage = computed(() => page.props.auth.user.is_system_admin);

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

const destroy = (unit: OrganizationUnit) => {
    if (confirm(`Xoá đơn vị "${unit.name}"?`)) {
        router.delete(route('organization-units.destroy', unit.id));
    }
};
</script>

<template>
    <Head title="Cơ cấu tổ chức" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Cơ cấu tổ chức</h2>
                <Link v-if="canManage" :href="route('organization-units.create')">
                    <Button label="Thêm đơn vị" icon="pi pi-plus" size="small" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <p v-if="organizationUnits.length === 0" class="text-sm text-gray-500">
                        Chưa có đơn vị nào. Tạo đơn vị gốc để bắt đầu.
                    </p>

                    <TreeTable v-else :value="treeNodes">
                        <Column field="name" header="Tên đơn vị" expander />
                        <Column field="code" header="Mã" />
                        <Column header="Trạng thái">
                            <template #body="{ node }">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                    :class="
                                        node.data.is_active
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-gray-100 text-gray-600'
                                    "
                                >
                                    {{ node.data.is_active ? 'Hoạt động' : 'Ngừng hoạt động' }}
                                </span>
                            </template>
                        </Column>
                        <Column v-if="canManage" header="Hành động">
                            <template #body="{ node }">
                                <div class="flex gap-2">
                                    <Link
                                        :href="route('organization-units.edit', node.data.id)"
                                        class="text-sm text-indigo-600 hover:underline"
                                    >
                                        Sửa
                                    </Link>
                                    <button
                                        type="button"
                                        class="text-sm text-red-600 hover:underline"
                                        @click="destroy(node.data)"
                                    >
                                        Xoá
                                    </button>
                                </div>
                            </template>
                        </Column>
                    </TreeTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
