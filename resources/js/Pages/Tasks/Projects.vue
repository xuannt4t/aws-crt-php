<script setup lang="ts">
import { ref } from 'vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppProjectStatusBadge from '@/Components/AppProjectStatusBadge.vue';
import { taskContextDescriptions, taskContextLabels } from '@/Constants/task';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { Project } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedProjects {
    data: (Project & { task_count: number; overdue_task_count: number })[];
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
    };
}>();

const search = ref(props.filters.search ?? '');
const isLoading = ref(false);

const handleFilter = () => {
    router.get(
        route('tasks.projects'),
        { search: search.value || undefined },
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
    <Head :title="taskContextLabels.project" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                :title="taskContextLabels.project"
                :description="taskContextDescriptions.project"
                eyebrow="Task Core"
            />
        </template>

        <form class="app-panel mb-5 p-5 sm:p-6" @submit.prevent="handleFilter">
            <div class="flex flex-wrap items-end gap-4">
                <label class="min-w-[220px] flex-1 basis-72">
                    <span class="mb-1.5 block text-xs font-bold text-slate-600">Tìm kiếm</span>
                    <input v-model="search" type="search" class="app-field" placeholder="Mã hoặc tên dự án" />
                </label>
                <div class="flex gap-2">
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
                <h2 class="font-display text-base font-bold text-ink-950">Danh sách dự án</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ projects.total }} dự án · hiển thị {{ projects.from ?? 0 }}–{{ projects.to ?? 0 }}
                </p>
            </div>

            <AppEmptyState
                v-if="projects.data.length === 0"
                icon="folder"
                title="Chưa có dự án phù hợp"
                description="Không có dự án nào trong phạm vi bạn được xem, hoặc thử đổi từ khoá tìm kiếm."
            />

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[900px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/70 text-xs font-bold text-slate-500">
                            <th class="px-5 py-3 sm:px-6">Mã</th>
                            <th class="px-5 py-3">Tên dự án</th>
                            <th class="px-5 py-3">Đơn vị</th>
                            <th class="px-5 py-3">Chủ dự án</th>
                            <th class="px-5 py-3">Trạng thái</th>
                            <th class="px-5 py-3">Số việc</th>
                            <th class="px-5 py-3">Trễ hạn</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="project in projects.data"
                            :key="project.id"
                            class="cursor-pointer hover:bg-slate-50/60"
                            @click="router.visit(route('tasks.projects.show', project.id))"
                        >
                            <td class="px-2 py-4 text-sm font-bold text-slate-700 sm:px-6">
                                {{ project.code }}
                            </td>
                            <td class="max-w-xs px-5 py-4">
                                <Link
                                    :href="route('tasks.projects.show', project.id)"
                                    class="block truncate text-sm font-bold text-slate-800 hover:text-brand-700"
                                    @click.stop
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
                            <td class="px-2 py-4 text-sm font-semibold text-slate-600">
                                {{ project.task_count }}
                            </td>
                            <td class="px-2 py-4 text-sm font-semibold" :class="project.overdue_task_count > 0 ? 'text-red-700' : 'text-slate-400'">
                                {{ project.overdue_task_count }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="projects.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-1 border-t border-slate-100 px-5 py-4"
                aria-label="Phân trang dự án"
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
