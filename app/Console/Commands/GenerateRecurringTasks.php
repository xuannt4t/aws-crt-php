<?php

namespace App\Console\Commands;

use App\Actions\TaskRecurrence\GenerateTasksFromRecurrenceAction;
use App\Models\TaskRecurrence;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

final class GenerateRecurringTasks extends Command
{
    protected $signature = 'tasks:generate-recurring';

    protected $description = 'Sinh công việc từ các mẫu công việc lặp lại định kỳ đang hoạt động';

    public function handle(GenerateTasksFromRecurrenceAction $action): int
    {
        $until = Carbon::now(config('app.timezone'))->startOfDay();

        TaskRecurrence::query()
            ->where('is_active', true)
            ->chunkById(100, function (Collection $recurrences) use ($action, $until): void {
                foreach ($recurrences as $recurrence) {
                    $action->execute($recurrence, $until);
                }
            });

        return self::SUCCESS;
    }
}
