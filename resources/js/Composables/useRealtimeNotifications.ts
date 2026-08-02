import { ref } from 'vue';
import axios from 'axios';
import { realtimeEcho } from '@/echo';
import type { NotificationItem, NotificationResponse } from '@/types/notification';

type IncomingHandler = (notification: NotificationItem) => void;

interface ReverbConnection {
    bind(event: 'connected', callback: () => void): void;
    unbind(event: 'connected', callback: () => void): void;
}

export const mergeRealtimeNotification = (
    current: NotificationItem[],
    incoming: NotificationItem,
): NotificationItem[] => [incoming, ...current.filter((item) => item.id !== incoming.id)].slice(0, 10);

export const useRealtimeNotifications = () => {
    const notifications = ref<NotificationItem[]>([]);
    const unreadCount = ref(0);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    let activeUserId: number | null = null;
    let connectedHandler: (() => void) | null = null;

    const refresh = async () => {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await axios.get<NotificationResponse>(route('notifications.index'));
            notifications.value = response.data.notifications;
            unreadCount.value = response.data.unread_count;
        } catch {
            error.value = 'Không thể tải thông báo. Vui lòng thử lại.';
        } finally {
            isLoading.value = false;
        }
    };

    const markAsRead = async (notification: NotificationItem) => {
        if (notification.read_at) {
            return notification;
        }

        try {
            const response = await axios.patch<{
                notification: NotificationItem;
                unread_count: number;
            }>(route('notifications.read', notification.id));

            notifications.value = notifications.value.map((item) =>
                item.id === notification.id ? response.data.notification : item,
            );
            unreadCount.value = response.data.unread_count;
            error.value = null;

            return response.data.notification;
        } catch {
            error.value = 'Không thể đánh dấu thông báo đã đọc.';
            return notification;
        }
    };

    const markAllAsRead = async () => {
        try {
            await axios.patch(route('notifications.read-all'));
            const readAt = new Date().toISOString();
            notifications.value = notifications.value.map((item) => ({
                ...item,
                read_at: item.read_at ?? readAt,
            }));
            unreadCount.value = 0;
            error.value = null;
        } catch {
            error.value = 'Không thể đánh dấu tất cả thông báo đã đọc.';
        }
    };

    const connection = (): ReverbConnection | null => {
        if (!realtimeEcho) {
            return null;
        }

        const connector = realtimeEcho.connector as unknown as {
            pusher?: { connection?: ReverbConnection };
        };

        return connector.pusher?.connection ?? null;
    };

    const connect = (userId: number, onIncoming?: IncomingHandler) => {
        if (!realtimeEcho || activeUserId === userId) {
            return;
        }

        activeUserId = userId;
        realtimeEcho.private(`App.Models.User.${userId}`).notification((payload: NotificationItem) => {
            const incoming: NotificationItem = {
                ...payload,
                read_at: payload.read_at ?? null,
            };
            const alreadyUnread = notifications.value.some((item) => item.id === incoming.id && !item.read_at);

            notifications.value = mergeRealtimeNotification(notifications.value, incoming);

            if (!alreadyUnread) {
                unreadCount.value += 1;
            }

            onIncoming?.(incoming);
        });

        connectedHandler = () => {
            void refresh();
        };
        connection()?.bind('connected', connectedHandler);
    };

    const disconnect = () => {
        if (activeUserId !== null) {
            realtimeEcho?.leave(`App.Models.User.${activeUserId}`);
        }

        if (connectedHandler) {
            connection()?.unbind('connected', connectedHandler);
        }

        activeUserId = null;
        connectedHandler = null;
    };

    return {
        notifications,
        unreadCount,
        isLoading,
        error,
        refresh,
        markAsRead,
        markAllAsRead,
        connect,
        disconnect,
    };
};
