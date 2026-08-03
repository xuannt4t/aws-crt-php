<?php

namespace App\Actions\TaskRecurrence;

use App\Enums\RecurrenceFrequency;
use App\Models\TaskRecurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateTaskRecurrenceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, TaskRecurrence $taskRecurrence, array $data): TaskRecurrence
    {
        return DB::transaction(function () use ($taskRecurrence, $data): TaskRecurrence {
            $taskRecurrence->update($this->normalizeFrequencyColumns($data));

            return $taskRecurrence->refresh();
        });
    }

    /**
     * `weekdays`/`day_of_month` chỉ có nghĩa với một số `frequency` nhất định.
     * FormRequest dùng `prohibitedIf` để CẤM gửi cột không liên quan lên,
     * nhưng client bỏ qua field đó (không gửi) chứ không gửi `null`, nên
     * `Model::update()` sẽ không đụng tới giá trị cũ — cột trở nên "mồ côi"
     * sau khi đổi `frequency`. Ở đây chuẩn hoá lại trước khi ghi: cột không
     * còn liên quan tới `frequency` mới luôn được set về `null`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeFrequencyColumns(array $data): array
    {
        if (! array_key_exists('frequency', $data)) {
            return $data;
        }

        $frequency = $data['frequency'] instanceof RecurrenceFrequency
            ? $data['frequency']
            : RecurrenceFrequency::from($data['frequency']);

        if ($frequency !== RecurrenceFrequency::Weekly) {
            $data['weekdays'] = null;
        }

        if (! in_array($frequency, [RecurrenceFrequency::Monthly, RecurrenceFrequency::Quarterly], true)) {
            $data['day_of_month'] = null;
        }

        return $data;
    }
}
