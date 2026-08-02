<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->serialize($notification));

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $databaseNotification = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $databaseNotification->markAsRead();

        return response()->json([
            'notification' => $this->serialize($databaseNotification->refresh()),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'event' => $notification->data['event'] ?? 'task_updated',
            'title' => $notification->data['title'] ?? 'Cập nhật công việc',
            'message' => $notification->data['message'] ?? '',
            'url' => $notification->data['url'] ?? route('tasks.index'),
            'task_id' => $notification->data['task_id'] ?? null,
            'actor' => $notification->data['actor'] ?? null,
            'created_at' => $notification->data['created_at'] ?? $notification->created_at?->toIso8601String(),
            'read_at' => $notification->read_at?->toIso8601String(),
        ];
    }
}
