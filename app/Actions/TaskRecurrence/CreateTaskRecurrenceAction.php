<?php

namespace App\Actions\TaskRecurrence;

use App\Models\TaskRecurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateTaskRecurrenceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): TaskRecurrence
    {
        return DB::transaction(fn (): TaskRecurrence => TaskRecurrence::create([
            ...$data,
            'creator_id' => $actor->id,
        ]));
    }
}
