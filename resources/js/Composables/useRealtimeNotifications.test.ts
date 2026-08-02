import { describe, expect, it } from 'vitest';
import { mergeRealtimeNotification } from './useRealtimeNotifications';
import type { NotificationItem } from '@/types/notification';

const notification = (id: string, readAt: string | null = null): NotificationItem => ({
    id,
    event: 'assigned',
    title: `Thông báo ${id}`,
    message: `Nội dung ${id}`,
    url: `/tasks/${id}`,
    task_id: Number(id) || 1,
    actor: null,
    created_at: '2026-08-02T08:00:00+07:00',
    read_at: readAt,
});

describe('mergeRealtimeNotification', () => {
    it('prepends a new notification and keeps only ten records', () => {
        const current = Array.from({ length: 10 }, (_, index) => notification(String(index + 1)));

        const result = mergeRealtimeNotification(current, notification('11'));

        expect(result).toHaveLength(10);
        expect(result[0].id).toBe('11');
        expect(result.at(-1)?.id).toBe('9');
    });

    it('replaces an existing id instead of duplicating it', () => {
        const current = [notification('1'), notification('2')];
        const updated = { ...notification('2'), title: 'Nội dung mới' };

        const result = mergeRealtimeNotification(current, updated);

        expect(result).toHaveLength(2);
        expect(result[0]).toEqual(updated);
    });
});
