<script setup lang="ts">
import { ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppRichTextContent from '@/Components/AppRichTextContent.vue';
import AppTaskPriorityBadge from '@/Components/AppTaskPriorityBadge.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { taskStatusClasses, taskStatusLabels } from '@/Constants/task';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import type { Task } from '@/types';

const props = defineProps<{
    task: Task;
    actions: {
        dispatch: boolean;
        start: boolean;
        submit: boolean;
    };
}>();

const { can } = usePermissions();
const isTransitioning = ref(false);

const handleTransition = (routeName: 'tasks.dispatch' | 'tasks.start' | 'tasks.submit') => {
    router.patch(
        route(routeName, props.task.id),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isTransitioning.value = true;
            },
            onFinish: () => {
                isTransitioning.value = false;
            },
        },
    );
};

const formatDateTime = (value: string | null) => {
    if (!value) {
        return 'Chưa thiết lập';
    }

    return new Intl.DateTimeFormat('vi-VN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};
</script>

<template>
    <Head :title="task.title" />

    <AuthenticatedLayout>
        <template #header>
            <AppPageHeader
                :title="task.title"
                :description="`Công việc #${task.id} · ${task.organization_unit?.name ?? 'Chưa có đơn vị'}`"
                eyebrow="Chi tiết công việc"
            >
                <template #actions>
                    <Link :href="route('tasks.index')" class="app-button-secondary">
                        <AppIcon name="arrow-left" class="size-4" />
                        Danh sách
                    </Link>
                    <Link v-if="can('task.update')" :href="route('tasks.edit', task.id)" class="app-button-secondary">
                        <AppIcon name="edit" class="size-4" />
                        Chỉnh sửa
                    </Link>
                </template>
            </AppPageHeader>
        </template>

        <section
            v-if="actions.dispatch || actions.start || actions.submit"
            class="mb-5 flex flex-col gap-4 rounded-2xl border border-brand-100 bg-brand-50/70 p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-sm font-bold text-brand-950">Hành động tiếp theo</p>
                <p class="mt-1 text-xs text-brand-800/70">Trạng thái chỉ thay đổi qua luồng nghiệp vụ hợp lệ.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-if="actions.dispatch"
                    type="button"
                    class="app-button-primary"
                    :disabled="isTransitioning"
                    @click="handleTransition('tasks.dispatch')"
                >
                    <AppIcon name="arrow-right" class="size-4" />
                    Giao công việc
                </button>
                <button
                    v-if="actions.start"
                    type="button"
                    class="app-button-primary"
                    :disabled="isTransitioning"
                    @click="handleTransition('tasks.start')"
                >
                    <AppIcon name="tasks" class="size-4" />
                    Bắt đầu thực hiện
                </button>
                <button
                    v-if="actions.submit"
                    type="button"
                    class="app-button-primary"
                    :disabled="isTransitioning"
                    @click="handleTransition('tasks.submit')"
                >
                    <AppIcon name="check" class="size-4" />
                    Gửi kiểm tra
                </button>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
            <div class="space-y-5">
                <section class="app-panel p-5 sm:p-7">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-bold"
                            :class="taskStatusClasses[task.status]"
                        >
                            {{ taskStatusLabels[task.status] }}
                        </span>
                        <AppTaskPriorityBadge :priority="task.priority" show-prefix />
                        <span
                            v-if="task.is_overdue"
                            class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700"
                        >
                            Quá hạn
                        </span>
                    </div>

                    <div class="mt-6">
                        <h2 class="font-display text-base font-bold text-ink-950">Mô tả</h2>
                        <AppRichTextContent v-if="task.description_html" :html="task.description_html" class="mt-3" />
                        <p v-else class="mt-3 text-sm italic text-slate-400">Chưa có mô tả chi tiết.</p>
                    </div>
                </section>

                <section class="app-panel overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <h2 class="font-display text-base font-bold text-ink-950">Lịch sử trạng thái</h2>
                        <p class="mt-1 text-xs text-slate-500">Mỗi lần chuyển trạng thái được ghi nhận bất biến.</p>
                    </div>

                    <div v-if="task.status_histories?.length" class="divide-y divide-slate-100">
                        <div
                            v-for="history in task.status_histories"
                            :key="history.id"
                            class="flex gap-4 px-5 py-4 sm:px-6"
                        >
                            <span
                                class="mt-1 flex size-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700"
                            >
                                <AppIcon name="arrow-right" class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-700">
                                    {{ taskStatusLabels[history.from_status] }}
                                    <span class="mx-1 text-slate-300">→</span>
                                    {{ taskStatusLabels[history.to_status] }}
                                </p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ history.actor?.name ?? 'Tài khoản đã xóa' }} ·
                                    {{ formatDateTime(history.created_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-5 py-10 text-center text-sm text-slate-400">
                        Chưa có lần chuyển trạng thái nào.
                    </div>
                </section>
            </div>

            <aside class="app-panel h-fit p-5 sm:p-6">
                <h2 class="font-display text-base font-bold text-ink-950">Thông tin thực hiện</h2>

                <dl class="mt-5 space-y-5">
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Người phụ trách</dt>
                        <dd v-if="task.assignee" class="mt-2 flex items-center gap-2.5">
                            <AppUserAvatar
                                :name="task.assignee.name"
                                :avatar-url="task.assignee.avatar_url"
                                size="sm"
                            />
                            <span class="text-sm font-semibold text-slate-700">{{ task.assignee.name }}</span>
                        </dd>
                        <dd v-else class="mt-2 text-sm text-slate-400">Chưa phân công</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Người tạo</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">{{ task.creator?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Đơn vị sở hữu</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">{{ task.organization_unit?.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Thời hạn</dt>
                        <dd
                            class="mt-2 text-sm font-semibold"
                            :class="task.is_overdue ? 'text-red-700' : 'text-slate-700'"
                        >
                            {{ formatDateTime(task.due_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Tiến độ</dt>
                        <dd class="mt-2">
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-brand-600" :style="{ width: `${task.progress}%` }" />
                            </div>
                            <p class="mt-1.5 text-xs font-semibold text-slate-500">{{ task.progress }}%</p>
                        </dd>
                    </div>
                </dl>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
