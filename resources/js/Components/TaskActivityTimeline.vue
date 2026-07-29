<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import { taskStatusLabels } from '@/Constants/task';
import { Link } from '@inertiajs/vue3';
import type { TaskActivity, TaskActivityType, TaskStatus } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    activities: {
        data: TaskActivity[];
        current_page: number;
        last_page: number;
        total: number;
        links: PaginationLink[];
    };
}>();

const KNOWN_TYPES: TaskActivityType[] = [
    'created',
    'status_changed',
    'assigned',
    'progress_updated',
    'quantity_updated',
    'commented',
    'attachment_added',
    'attachment_removed',
];

type ActivityIcon = 'plus' | 'arrow-right' | 'user' | 'check' | 'message' | 'folder' | 'trash';

const iconFor: Record<TaskActivityType, ActivityIcon> = {
    created: 'plus',
    status_changed: 'arrow-right',
    assigned: 'user',
    progress_updated: 'check',
    quantity_updated: 'check',
    commented: 'message',
    attachment_added: 'folder',
    attachment_removed: 'trash',
};

const toneFor: Record<TaskActivityType, string> = {
    created: 'bg-slate-100 text-slate-600',
    status_changed: 'bg-brand-50 text-brand-700',
    assigned: 'bg-blue-50 text-blue-700',
    progress_updated: 'bg-amber-50 text-amber-700',
    quantity_updated: 'bg-amber-50 text-amber-700',
    commented: 'bg-violet-50 text-violet-700',
    attachment_added: 'bg-emerald-50 text-emerald-700',
    attachment_removed: 'bg-red-50 text-red-700',
};

const isKnown = (activity: TaskActivity) => KNOWN_TYPES.includes(activity.type);

const actorName = (activity: TaskActivity) => activity.actor?.name ?? 'Tài khoản đã xóa';

const statusLabel = (value: unknown) =>
    typeof value === 'string' ? (taskStatusLabels[value as TaskStatus] ?? value) : '';

const describe = (activity: TaskActivity): string => {
    const payload = activity.payload ?? {};

    switch (activity.type) {
        case 'created':
            return 'đã tạo công việc';
        case 'status_changed':
            return `chuyển trạng thái từ ${statusLabel(payload.from)} sang ${statusLabel(payload.to)}`;
        case 'assigned': {
            const from = payload.from_assignee_name as string | null;
            const to = payload.to_assignee_name as string | null;

            if (to === null) {
                return `bỏ phân công ${from ?? 'người phụ trách'}`;
            }

            return from === null ? `giao việc cho ${to}` : `chuyển phụ trách từ ${from} sang ${to}`;
        }
        case 'progress_updated':
            return `cập nhật tiến độ từ ${payload.from}% lên ${payload.to}%`;
        case 'commented':
            return `đã trao đổi: ${payload.excerpt as string}`;
        case 'attachment_added': {
            const names = (payload.original_names as string[] | undefined) ?? [];

            return `đính kèm ${payload.file_count} tệp: ${names.join(', ')}`;
        }
        case 'attachment_removed':
            return `xoá tệp ${payload.original_name as string}`;
        default:
            return '';
    }
};

const formatDateTime = (value: string) =>
    new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' });
</script>

<template>
    <section class="app-panel overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
            <h2 class="font-display text-base font-bold text-ink-950">Dòng thời gian</h2>
            <p class="mt-1 text-xs text-slate-500">
                {{ activities.total }} hoạt động đã được ghi nhận trong công việc này.
            </p>
        </div>

        <ol v-if="activities.data.length" class="divide-y divide-slate-100">
            <li
                v-for="activity in activities.data.filter(isKnown)"
                :key="activity.id"
                class="flex gap-4 px-5 py-4 sm:px-6"
            >
                <span
                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
                    :class="toneFor[activity.type]"
                >
                    <AppIcon :name="iconFor[activity.type]" class="size-4" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-700">
                        <span class="font-semibold text-slate-900">{{ actorName(activity) }}</span>
                        {{ ' ' }}{{ describe(activity) }}
                    </p>
                    <div class="mt-1 flex items-center gap-2">
                        <AppUserAvatar
                            :name="actorName(activity)"
                            :avatar-url="activity.actor?.avatar_url"
                            class="size-5"
                        />
                        <time class="text-xs text-slate-400" :datetime="activity.created_at">
                            {{ formatDateTime(activity.created_at) }}
                        </time>
                    </div>
                </div>
            </li>
        </ol>

        <p v-else class="px-5 py-10 text-center text-sm text-slate-400">Chưa có hoạt động nào.</p>

        <nav
            v-if="activities.last_page > 1"
            class="flex flex-wrap items-center justify-center gap-1 border-t border-slate-100 px-5 py-3"
            aria-label="Phân trang hoạt động"
        >
            <template v-for="link in activities.links" :key="link.label">
                <span
                    v-if="!link.url"
                    class="rounded-lg px-3 py-1.5 text-xs text-slate-300"
                    v-text="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    class="rounded-lg px-3 py-1.5 text-xs"
                    :class="link.active ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                    >{{ link.label }}</Link
                >
            </template>
        </nav>
    </section>
</template>
