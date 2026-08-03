<?php

namespace App\Actions\TaskRecurrence;

use App\Models\TaskRecurrence;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ToggleTaskRecurrenceAction
{
    /**
     * Lật is_active.
     *
     * Tắt: không đụng last_generated_for.
     *
     * Bật lại: các kỳ rơi vào quãng tắt bị BỎ QUA chứ không sinh bù (spec mục
     * 4.3). Vì lệnh sinh luôn bắt đầu từ last_generated_for + 1 ngày, nếu để
     * nguyên mốc cũ thì lần chạy kế tiếp sẽ sinh bù toàn bộ quãng tắt. Nên khi
     * bật lại phải đẩy mốc lên hôm qua, để kỳ đầu tiên được sinh lại là kỳ của
     * hôm nay trở đi. Mẫu chưa từng sinh (last_generated_for null) giữ nguyên
     * null — đó là lần chạy đầu tiên, vẫn tính từ start_date như bình thường.
     */
    public function execute(User $actor, TaskRecurrence $taskRecurrence): TaskRecurrence
    {
        return DB::transaction(function () use ($taskRecurrence): TaskRecurrence {
            $isEnabling = ! $taskRecurrence->is_active;

            $attributes = ['is_active' => $isEnabling];

            if ($isEnabling && $taskRecurrence->last_generated_for !== null) {
                $resumeFrom = Carbon::now(config('app.timezone'))->startOfDay()->subDay();

                if ($taskRecurrence->last_generated_for->lt($resumeFrom)) {
                    $attributes['last_generated_for'] = $resumeFrom->toDateString();
                }
            }

            $taskRecurrence->update($attributes);

            return $taskRecurrence->refresh();
        });
    }
}
