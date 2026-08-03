<script setup lang="ts">
import { ref } from 'vue';
import AppConfirmDialog from '@/Components/AppConfirmDialog.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppPageHeader from '@/Components/AppPageHeader.vue';
import AppRichTextContent from '@/Components/AppRichTextContent.vue';
import AppTaskPriorityBadge from '@/Components/AppTaskPriorityBadge.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import InputError from '@/Components/InputError.vue';
import TaskActivityTimeline from '@/Components/TaskActivityTimeline.vue';
import TaskAttachmentList from '@/Components/TaskAttachmentList.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { taskStatusClasses, taskStatusLabels } from '@/Constants/task';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps, Task, TaskActivity, TaskAttachment, TaskComment } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedComments {
    data: TaskComment[];
    current_page: number;
    last_page: number;
    total: number;
    links: PaginationLink[];
}

interface PaginatedActivities {
    data: TaskActivity[];
    current_page: number;
    last_page: number;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    task: Task;
    comments: PaginatedComments;
    attachments: TaskAttachment[];
    activities: PaginatedActivities;
    actions: {
        dispatch: boolean;
        start: boolean;
        submit: boolean;
        updateProgress: boolean;
        recall: boolean;
        comment: boolean;
        attach: boolean;
    };
}>();

const { can } = usePermissions();
const page = usePage<PageProps>();
const isTransitioning = ref(false);
const isConfirmingRecall = ref(false);
const progressForm = useForm({
    value: props.task.progress,
});
const commentForm = useForm({
    body: '',
});

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

const updateProgress = () => {
    progressForm
        .transform((data) => ({ progress: data.value }))
        .patch(route('tasks.progress.update', props.task.id), {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.progress) {
                    progressForm.setError('value', errors.progress);
                }
            },
        });
};

const recallSubmission = () => {
    router.patch(
        route('tasks.recall', props.task.id),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                isTransitioning.value = true;
            },
            onFinish: () => {
                isTransitioning.value = false;
                isConfirmingRecall.value = false;
            },
        },
    );
};

const submitComment = () => {
    commentForm.post(route('tasks.comments.store', props.task.id), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
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
            v-if="actions.dispatch || actions.start || actions.submit || actions.recall"
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
                <button
                    v-if="actions.recall"
                    type="button"
                    class="app-button-secondary"
                    :disabled="isTransitioning"
                    @click="isConfirmingRecall = true"
                >
                    <AppIcon name="arrow-left" class="size-4" />
                    Thu hồi yêu cầu kiểm tra
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
                        <Link
                            v-if="task.recurrence"
                            :href="route('task-recurrences.show', task.recurrence.id)"
                            class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700 hover:bg-brand-100"
                        >
                            <AppIcon name="calendar" class="size-3.5" />
                            Sinh từ mẫu: {{ task.recurrence.title }}
                        </Link>
                    </div>

                    <div class="mt-6">
                        <h2 class="font-display text-base font-bold text-ink-950">Mô tả</h2>
                        <AppRichTextContent v-if="task.description_html" :html="task.description_html" class="mt-3" />
                        <p v-else class="mt-3 text-sm italic text-slate-400">Chưa có mô tả chi tiết.</p>
                    </div>
                </section>

                <section class="app-panel overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="font-display text-base font-bold text-ink-950">Trao đổi</h2>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ comments.total }} nội dung trao đổi trong công việc này.
                                </p>
                            </div>
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700"
                            >
                                <AppIcon name="message" class="size-4" />
                            </span>
                        </div>
                    </div>

                    <form
                        v-if="actions.comment"
                        class="border-b border-slate-100 bg-slate-50/40 px-5 py-5 sm:px-6"
                        @submit.prevent="submitComment"
                    >
                        <label for="task-comment" class="text-xs font-bold text-slate-600"
                            >Thêm nội dung trao đổi</label
                        >
                        <textarea
                            id="task-comment"
                            v-model="commentForm.body"
                            rows="3"
                            maxlength="5000"
                            class="app-field mt-2 resize-y"
                            placeholder="Cập nhật tình hình, đặt câu hỏi hoặc phản hồi..."
                        />
                        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <InputError :message="commentForm.errors.body" />
                                <p v-if="!commentForm.errors.body" class="text-xs text-slate-400">
                                    {{ commentForm.body.length.toLocaleString('vi-VN') }}/5.000 ký tự
                                </p>
                            </div>
                            <button
                                type="submit"
                                class="app-button-primary"
                                :disabled="commentForm.processing || !commentForm.body.trim()"
                            >
                                <AppIcon name="arrow-right" class="size-4" />
                                {{ commentForm.processing ? 'Đang gửi...' : 'Gửi trao đổi' }}
                            </button>
                        </div>
                    </form>

                    <div v-if="comments.data.length" class="divide-y divide-slate-100">
                        <article
                            v-for="comment in comments.data"
                            :key="comment.id"
                            class="flex gap-3 px-5 py-5 sm:px-6"
                        >
                            <AppUserAvatar
                                :name="comment.author?.name ?? 'Tài khoản đã xóa'"
                                :avatar-url="comment.author?.avatar_url"
                                size="sm"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                    <h3 class="text-sm font-bold text-slate-800">
                                        {{ comment.author?.name ?? 'Tài khoản đã xóa' }}
                                        <span
                                            v-if="comment.author?.id === page.props.auth.user.id"
                                            class="text-brand-700"
                                        >
                                            (Bạn)
                                        </span>
                                    </h3>
                                    <time class="text-xs text-slate-400" :datetime="comment.created_at">
                                        {{ formatDateTime(comment.created_at) }}
                                    </time>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-600">
                                    {{ comment.body }}
                                </p>
                            </div>
                        </article>
                    </div>
                    <div v-else class="px-5 py-10 text-center">
                        <p class="text-sm font-semibold text-slate-500">Chưa có nội dung trao đổi.</p>
                        <p class="mt-1 text-xs text-slate-400">Hãy bắt đầu bằng một cập nhật ngắn về công việc.</p>
                    </div>

                    <nav
                        v-if="comments.last_page > 1"
                        class="flex flex-wrap items-center justify-center gap-1 border-t border-slate-100 px-5 py-4"
                        aria-label="Phân trang trao đổi"
                    >
                        <template v-for="link in comments.links" :key="link.label">
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

                <TaskAttachmentList
                    class="mt-6"
                    :task-id="task.id"
                    :attachments="attachments"
                    :can-attach="actions.attach"
                />

                <TaskActivityTimeline class="mt-6" :activities="activities" />
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
                            <span class="text-sm font-semibold text-slate-700">
                                {{ task.assignee.name }}
                                <span v-if="task.assignee.id === page.props.auth.user.id" class="text-brand-700">
                                    (Bạn)
                                </span>
                            </span>
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
                        <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Dự án</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-700">
                            <Link
                                v-if="task.project"
                                :href="route('projects.show', task.project.id)"
                                class="text-brand-700 hover:underline"
                            >
                                {{ task.project.name }}
                            </Link>
                            <span v-else class="text-slate-400">Không thuộc dự án</span>
                        </dd>
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

                <form
                    v-if="actions.updateProgress"
                    class="mt-6 rounded-xl border border-brand-100 bg-brand-50/60 p-4"
                    @submit.prevent="updateProgress"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-100 text-brand-700"
                        >
                            <AppIcon name="tasks" class="size-4" />
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-brand-950">Cập nhật tiến độ</h3>
                            <p class="mt-1 text-xs leading-5 text-brand-800/70">
                                Bạn là người phụ trách công việc này.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <input
                            v-model.number="progressForm.value"
                            type="range"
                            min="0"
                            max="100"
                            step="5"
                            class="h-2 min-w-0 flex-1 cursor-pointer accent-brand-600"
                            aria-label="Tiến độ công việc"
                        />
                        <label class="relative w-20 shrink-0">
                            <input
                                v-model.number="progressForm.value"
                                type="number"
                                min="0"
                                max="100"
                                class="app-field h-10 py-2 pr-7 text-center text-sm font-bold"
                                aria-label="Phần trăm tiến độ"
                            />
                            <span
                                class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400"
                            >
                                %
                            </span>
                        </label>
                    </div>

                    <InputError class="mt-2" :message="progressForm.errors.value" />

                    <button
                        type="submit"
                        class="app-button-primary mt-4 w-full justify-center"
                        :disabled="progressForm.processing"
                    >
                        <AppIcon name="check" class="size-4" />
                        {{ progressForm.processing ? 'Đang lưu...' : 'Lưu tiến độ' }}
                    </button>
                </form>
            </aside>
        </div>

        <AppConfirmDialog
            :show="isConfirmingRecall"
            title="Thu hồi yêu cầu kiểm tra?"
            description="Công việc sẽ quay về trạng thái Đang thực hiện để bạn tiếp tục cập nhật. Lịch sử gửi kiểm tra vẫn được giữ lại."
            confirm-label="Thu hồi yêu cầu"
            icon="arrow-left"
            tone="primary"
            :processing="isTransitioning"
            @cancel="isConfirmingRecall = false"
            @confirm="recallSubmission"
        />
    </AuthenticatedLayout>
</template>
