<?php

namespace App\Console\Commands;

use App\Actions\TaskRecurrence\GenerateTasksFromRecurrenceAction;
use App\Models\TaskRecurrence;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GenerateRecurringTasks extends Command
{
    protected $signature = 'tasks:generate-recurring';

    protected $description = 'Sinh công việc từ các mẫu công việc lặp lại định kỳ đang hoạt động';

    public function handle(GenerateTasksFromRecurrenceAction $action): int
    {
        $until = Carbon::now(config('app.timezone'))->startOfDay();
        $failed = 0;

        TaskRecurrence::query()
            ->where('is_active', true)
            ->with('creator')
            ->chunkById(100, function (Collection $recurrences) use ($action, $until, &$failed): void {
                foreach ($recurrences as $recurrence) {
                    // Một mẫu hỏng (lỗi ghi dữ liệu, event/broadcast thất bại...)
                    // không được phép chặn các mẫu còn lại của lần chạy đêm.
                    try {
                        $action->execute($recurrence, $until);
                    } catch (Throwable $e) {
                        $failed++;

                        Log::error('Không sinh được công việc cho mẫu công việc định kỳ, bỏ qua mẫu này.', [
                            'task_recurrence_id' => $recurrence->id,
                            'exception' => $e,
                        ]);

                        $this->error("Mẫu #{$recurrence->id}: {$e->getMessage()}");
                    }
                }
            });

        if ($failed > 0) {
            $this->error("Có {$failed} mẫu công việc định kỳ sinh thất bại.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
