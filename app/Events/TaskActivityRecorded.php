<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class TaskActivityRecorded
{
    use Dispatchable;

    public function __construct(public readonly int $activityId) {}
}
