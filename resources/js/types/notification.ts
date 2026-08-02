export interface NotificationActor {
    id: number;
    name: string;
    avatar_url: string | null;
}

export interface NotificationItem {
    id: string;
    event: string;
    title: string;
    message: string;
    url: string;
    task_id: number | null;
    actor: NotificationActor | null;
    created_at: string;
    read_at: string | null;
}

export interface NotificationResponse {
    notifications: NotificationItem[];
    unread_count: number;
}
