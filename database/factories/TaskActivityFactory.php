<?php

namespace Database\Factories;

use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskActivity>
 */
final class TaskActivityFactory extends Factory
{
    protected $model = TaskActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'actor_id' => User::factory(),
            'type' => TaskActivityType::Created->value,
            'payload' => null,
        ];
    }
}
