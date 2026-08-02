<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'primevue/usetoast';
import AppIcon from '@/Components/AppIcon.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import { useRealtimeNotifications } from '@/Composables/useRealtimeNotifications';
import type { NotificationItem } from '@/types/notification';

const props = defineProps<{
    userId: number;
}>();

const toast = useToast();
const root = ref<HTMLElement | null>(null);
const isOpen = ref(false);
const { notifications, unreadCount, isLoading, error, refresh, markAsRead, markAllAsRead, connect, disconnect } =
    useRealtimeNotifications();

const badge = computed(() => (unreadCount.value > 99 ? '99+' : String(unreadCount.value)));

const open = () => {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        void refresh();
    }
};

const visit = async (notification: NotificationItem) => {
    await markAsRead(notification);
    isOpen.value = false;
    router.visit(notification.url);
};

const relativeTime = (value: string) => {
    const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000);
    const formatter = new Intl.RelativeTimeFormat('vi', { numeric: 'auto' });

    if (Math.abs(seconds) < 60) {
        return formatter.format(seconds, 'second');
    }

    const minutes = Math.round(seconds / 60);
    if (Math.abs(minutes) < 60) {
        return formatter.format(minutes, 'minute');
    }

    const hours = Math.round(minutes / 60);
    if (Math.abs(hours) < 24) {
        return formatter.format(hours, 'hour');
    }

    return formatter.format(Math.round(hours / 24), 'day');
};

const closeFromOutside = (event: MouseEvent) => {
    if (isOpen.value && root.value && !root.value.contains(event.target as Node)) {
        isOpen.value = false;
    }
};

const closeFromKeyboard = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        isOpen.value = false;
    }
};

onMounted(() => {
    void refresh();
    connect(props.userId, (notification) => {
        toast.add({
            severity: 'info',
            summary: notification.title,
            detail: notification.message,
            life: 5000,
        });
    });
    document.addEventListener('click', closeFromOutside);
    document.addEventListener('keydown', closeFromKeyboard);
});

onBeforeUnmount(() => {
    disconnect();
    document.removeEventListener('click', closeFromOutside);
    document.removeEventListener('keydown', closeFromKeyboard);
});
</script>

<template>
    <div ref="root" class="relative ml-auto sm:ml-0">
        <button
            type="button"
            class="relative inline-flex size-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-white hover:text-brand-700 focus:outline-none focus:ring-4 focus:ring-brand-500/10"
            :aria-expanded="isOpen"
            aria-haspopup="dialog"
            aria-label="Mở trung tâm thông báo"
            @click.stop="open"
        >
            <AppIcon name="bell" class="size-5" />
            <span
                v-if="unreadCount > 0"
                class="absolute -right-0.5 -top-0.5 inline-flex min-w-5 items-center justify-center rounded-full border-2 border-[#f7f8f6] bg-red-500 px-1 text-[9px] font-extrabold leading-4 text-white"
            >
                {{ badge }}
            </span>
        </button>

        <section
            v-if="isOpen"
            class="absolute right-0 top-12 z-50 w-[min(380px,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-float"
            role="dialog"
            aria-label="Thông báo"
        >
            <header class="flex items-center justify-between border-b border-slate-100 px-4 py-3.5">
                <div>
                    <h2 class="font-display text-sm font-bold text-ink-950">Thông báo</h2>
                    <p class="mt-0.5 text-[11px] text-slate-400">{{ unreadCount }} thông báo chưa đọc</p>
                </div>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="text-xs font-semibold text-brand-700 hover:text-brand-900"
                    @click="markAllAsRead"
                >
                    Đọc tất cả
                </button>
            </header>

            <div v-if="isLoading && notifications.length === 0" class="px-4 py-10 text-center text-sm text-slate-400">
                Đang tải thông báo...
            </div>
            <div v-else-if="error && notifications.length === 0" class="px-4 py-8 text-center">
                <p class="text-sm font-semibold text-red-700">{{ error }}</p>
                <button type="button" class="mt-2 text-xs font-bold text-brand-700" @click="refresh">Thử lại</button>
            </div>
            <div v-else-if="notifications.length === 0" class="px-6 py-10 text-center">
                <span class="mx-auto flex size-11 items-center justify-center rounded-2xl bg-brand-50 text-brand-700">
                    <AppIcon name="bell" class="size-5" />
                </span>
                <p class="mt-3 text-sm font-bold text-slate-700">Chưa có thông báo</p>
                <p class="mt-1 text-xs text-slate-400">Các cập nhật công việc mới sẽ xuất hiện tại đây.</p>
            </div>
            <ul v-else class="max-h-[430px] divide-y divide-slate-100 overflow-y-auto">
                <li v-for="notification in notifications" :key="notification.id">
                    <button
                        type="button"
                        class="group flex w-full gap-3 px-4 py-3.5 text-left transition hover:bg-slate-50"
                        :class="notification.read_at ? 'bg-white' : 'bg-brand-50/65'"
                        @click="visit(notification)"
                    >
                        <AppUserAvatar
                            v-if="notification.actor"
                            :name="notification.actor.name"
                            :avatar-url="notification.actor.avatar_url"
                            size="sm"
                        />
                        <span
                            v-else
                            class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200"
                        >
                            <AppIcon name="calendar" class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-start gap-2">
                                <span class="line-clamp-1 text-xs font-bold text-slate-800">{{
                                    notification.title
                                }}</span>
                                <span
                                    v-if="!notification.read_at"
                                    class="mt-1.5 size-1.5 shrink-0 rounded-full bg-brand-600"
                                    aria-label="Chưa đọc"
                                />
                            </span>
                            <span class="mt-1 line-clamp-2 block text-xs leading-5 text-slate-500">
                                {{ notification.message }}
                            </span>
                            <time
                                :datetime="notification.created_at"
                                class="mt-1.5 block text-[10px] font-medium text-slate-400"
                            >
                                {{ relativeTime(notification.created_at) }}
                            </time>
                        </span>
                    </button>
                </li>
            </ul>

            <p
                v-if="error && notifications.length > 0"
                class="border-t border-red-100 bg-red-50 px-4 py-2 text-xs text-red-700"
            >
                {{ error }}
            </p>
        </section>
    </div>
</template>
