<script setup lang="ts">
import { computed, ref } from 'vue';
import AppConfirmDialog from '@/Components/AppConfirmDialog.vue';
import AppEmptyState from '@/Components/AppEmptyState.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppProjectStatusBadge from '@/Components/AppProjectStatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import ProjectMemberList from '@/Components/ProjectMemberList.vue';
import ProjectProgressBar from '@/Components/ProjectProgressBar.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { taskStatusClasses, taskStatusLabels } from '@/Constants/task';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { OrganizationUnit, Project, ProjectMember, Task, User } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedTasks {
    data: Task[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    project: Pick<
        Project,
        | 'id'
        | 'organization_unit_id'
        | 'owner_id'
        | 'code'
        | 'name'
        | 'description'
        | 'status'
        | 'start_date'
        | 'end_date'
        | 'closed_at'
        | 'close_reason'
        | 'progress'
        | 'open_task_count'
    > & {
        organization_unit?: Pick<OrganizationUnit, 'id' | 'name'>;
        owner?: Pick<User, 'id' | 'name'>;
    };
    members: ProjectMember[];
    users: Pick<User, 'id' | 'name'>[];
    tasks: PaginatedTasks;
    actions: {
        update: boolean;
        delete: boolean;
        manageMembers: boolean;
        close: boolean;
    };
}>();

const isConfirmingDelete = ref(false);
const isDeleting = ref(false);
const isClosing = ref(false);

const hasOpenTasks = computed(() => (props.project.open_task_count ?? 0) > 0);

const closeForm = useForm({
    close_reason: '',
});

const submitClose = () => {
    closeForm.patch(route('projects.close', props.project.id), {
        preserveScroll: true,
        onSuccess: () => {
            isClosing.value = false;
            closeForm.reset();
        },
    });
};

const openCloseDialog = () => {
    closeForm.clearErrors();
    closeForm.reset();
    isClosing.value = true;
};

const confirmDelete = () => {
    router.delete(route('projects.destroy', props.project.id), {
        onStart: () => {
            isDeleting.value = true;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const formatDate = (value: string | null) => {
    if (!value) {
        return 'Chưa thiết lập';
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
    <Head :title="project.name" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                :title="project.name"
                :description="`Dự án #${project.code} · ${project.organization_unit?.name ?? 'Chưa có đơn vị'}`"
                eyebrow="Chi tiết dự án"
            >
                <template #actions>
                    <Link :href="route('projects.index')" class="app-button-secondary">
                        <AppIcon name="arrow-left" class="size-4" />
                        Danh sách
                    </Link>
                    <Link v-if="actions.update" :href="route('projects.edit', project.id)" class="app-button-secondary">
                        <AppIcon name="edit" class="size-4" />
                        Chỉnh sửa
                    </Link>
                    <button
                        v-if="actions.close"
                        type="button"
                        class="app-button-secondary"
                        @click="openCloseDialog"
                    >
                        <AppIcon name="lock" class="size-4" />
                        Đóng dự án
                    </button>
                    <button
                        v-if="actions.delete"
                        type="button"
                        class="app-button-secondary text-red-600 hover:bg-red-50"
                        @click="isConfirmingDelete = true"
                    >
                        <AppIcon name="trash" class="size-4" />
                        Xóa
                    </button>
                </template>
            </AppPageHeader>
        </template>

        <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
            <div class="space-y-5">
                <section class="app-panel p-5 sm:p-7">
                    <div class="flex flex-wrap items-center gap-2">
                        <AppProjectStatusBadge :status="project.status" />
                        <span
                            v-if="project.closed_at"
                            class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600"
                        >
                            Đã đóng {{ formatDate(project.closed_at) }}
                        </span>
                    </div>

                    <div class="mt-6">
                        <h2 class="font-display text-base font-bold text-ink-950">Mô tả</h2>
                        <p v-if="project.description" class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-600">
                            {{ project.description }}
                        </p>
                        <p v-else class="mt-3 text-sm italic text-slate-400">Chưa có mô tả chi tiết.</p>
                    </div>

                    <div v-if="project.close_reason" class="mt-6 rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">Lý do đóng dự án</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ project.close_reason }}</p>
                    </div>

                    <div class="mt-6">
                        <h2 class="font-display text-base font-bold text-ink-950">Tiến độ tổng thể</h2>
                        <div class="mt-3 max-w-sm">
                            <ProjectProgressBar :progress="project.progress ?? 0" />
                        </div>
                    </div>
                </section>

                <section class="app-panel overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 class="font-display text-base font-bold text-ink-950">Thành viên dự án</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ members.length }} thành viên tham gia dự án.</p>
                    </div>
                    <div class="px-5 py-5 sm:px-6">
                        <ProjectMemberList
                            :project-id="project.id"
                            :members="members"
                            :users="users"
                            :can-manage="actions.manageMembers"
                        />
                    </div>
                </section>

                <section class="app-panel overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 class="font-display text-base font-bold text-ink-950">Công việc thuộc dự án</h2>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ tasks.total }} công việc · hiển thị {{ tasks.from ?? 0 }}–{{ tasks.to ?? 0 }}
                        </p>
                    </div>

                    <AppEmptyState
                        v-if="tasks.data.length === 0"
                        icon="tasks"
                        title="Chưa có công việc"
                        description="Dự án này chưa có công việc nào được tạo."
                    />

                    <ul v-else class="divide-y divide-slate-100">
                        <li v-for="task in tasks.data" :key="task.id" class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
                            <div class="min-w-0">
                                <Link
                                    :href="route('tasks.show', task.id)"
                                    class="block truncate text-sm font-bold text-slate-800 hover:text-brand-700"
                                >
                                    {{ task.title }}
                                </Link>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ task.assignee ? task.assignee.name : 'Chưa phân công' }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold"
                                :class="taskStatusClasses[task.status]"
                            >
                                {{ taskStatusLabels[task.status] }}
                            </span>
                        </li>
                    </ul>

                    <nav
                        v-if="tasks.last_page > 1"
                        class="flex flex-wrap items-center justify-center gap-1 border-t border-slate-100 px-5 py-4"
                        aria-label="Phân trang công việc"
                    >
                        <template v-for="link in tasks.links" :key="link.label">
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
            </div>

            <aside class="app-panel h-fit p-5 sm:p-6">
                <h2 class="font-display text-base font-bold text-ink-950">Thông tin dự án</h2>

                <dl class="mt-5 space-y-5">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Chủ dự án</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">{{ project.owner?.name ?? 'Chưa có' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Đơn vị sở hữu</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">{{ project.organization_unit?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Ngày bắt đầu</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">{{ formatDate(project.start_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Ngày kết thúc</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">{{ formatDate(project.end_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Công việc còn mở</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">{{ project.open_task_count ?? 0 }}</dd>
                    </div>
                </dl>
            </aside>
        </div>

        <Modal :show="isClosing" max-width="md" @close="isClosing = false">
            <form class="p-6 sm:p-7" @submit.prevent="submitClose">
                <div class="flex size-11 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                    <AppIcon name="lock" class="size-5" />
                </div>
                <h2 class="mt-5 font-display text-lg font-bold tracking-[-0.02em] text-ink-950">Đóng dự án?</h2>

                <p v-if="!hasOpenTasks" class="mt-2 text-sm leading-6 text-slate-500">
                    Dự án không còn công việc mở. Bạn có thể đóng dự án ngay.
                </p>

                <template v-else>
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-semibold text-amber-800">
                            Dự án còn {{ project.open_task_count }} công việc chưa hoàn thành.
                        </p>
                        <p class="mt-1 text-xs leading-5 text-amber-700/80">
                            Vui lòng nhập lý do đóng dự án ngoại lệ trước khi tiếp tục.
                        </p>
                    </div>

                    <div class="mt-4">
                        <textarea
                            v-model="closeForm.close_reason"
                            rows="4"
                            maxlength="1000"
                            class="app-field resize-y"
                            placeholder="Nhập lý do đóng dự án khi còn công việc chưa hoàn thành (tối thiểu 10 ký tự)..."
                        />
                        <InputError class="mt-2" :message="closeForm.errors.close_reason" />
                    </div>
                </template>

                <div class="mt-7 flex justify-end gap-2">
                    <SecondaryButton @click="isClosing = false">Huỷ</SecondaryButton>
                    <button type="submit" class="app-button-primary" :disabled="closeForm.processing">
                        {{ closeForm.processing ? 'Đang đóng...' : 'Đóng dự án' }}
                    </button>
                </div>
            </form>
        </Modal>

        <AppConfirmDialog
            :show="isConfirmingDelete"
            title="Xóa dự án?"
            :description="`Dự án “${project.name}” sẽ được chuyển vào trạng thái đã xóa.`"
            confirm-label="Xóa dự án"
            :processing="isDeleting"
            @cancel="isConfirmingDelete = false"
            @confirm="confirmDelete"
        />
    </AuthenticatedLayout>
</template>
