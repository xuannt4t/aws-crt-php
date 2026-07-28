<script setup lang="ts">
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import type { PageProps, User } from '@/types';

defineProps<{
    users: User[];
}>();

const page = usePage<PageProps>();
const canManage = computed(() => page.props.auth.user.is_system_admin);

const toggleActive = (user: User) => {
    const routeName = user.is_active ? 'users.disable' : 'users.enable';
    router.patch(route(routeName, user.id));
};
</script>

<template>
    <Head title="Người dùng" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Người dùng</h2>
                <Link v-if="canManage" :href="route('users.create')">
                    <Button label="Thêm người dùng" icon="pi pi-plus" size="small" />
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <p v-if="users.length === 0" class="text-sm text-gray-500">Chưa có người dùng nào.</p>

                    <DataTable v-else :value="users" data-key="id" paginator :rows="20">
                        <Column field="name" header="Họ tên" />
                        <Column field="email" header="Email" />
                        <Column header="Đơn vị">
                            <template #body="{ data }">
                                {{ data.organization_unit?.name ?? '—' }}
                            </template>
                        </Column>
                        <Column header="Trạng thái">
                            <template #body="{ data }">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                    :class="data.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ data.is_active ? 'Hoạt động' : 'Đã vô hiệu hoá' }}
                                </span>
                            </template>
                        </Column>
                        <Column v-if="canManage" header="Hành động">
                            <template #body="{ data }">
                                <div class="flex gap-3">
                                    <Link :href="route('users.edit', data.id)" class="text-sm text-indigo-600 hover:underline">
                                        Sửa
                                    </Link>
                                    <button type="button" class="text-sm text-amber-600 hover:underline" @click="toggleActive(data)">
                                        {{ data.is_active ? 'Vô hiệu hoá' : 'Kích hoạt' }}
                                    </button>
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
