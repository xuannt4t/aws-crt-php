<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Events\TaskActivityRecorded;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;

final class RecordTaskActivityAction
{
    /**
     * Không tự mở transaction: Action này luôn được gọi từ bên trong transaction
     * của Action nghiệp vụ, để hoạt động và dữ liệu cùng thành công hoặc cùng huỷ.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        User $actor,
        Task $task,
        TaskActivityType $type,
        array $payload = [],
    ): TaskActivity {
        $activity = $task->activities()->create([
            'actor_id' => $actor->id,
            'type' => $type,
            'payload' => $payload === [] ? null : $payload,
        ]);

        TaskActivityRecorded::dispatch($activity->id);

        return $activity;
    }
}
