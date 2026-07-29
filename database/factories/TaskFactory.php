<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
final class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'organization_unit_id' => OrganizationUnit::factory(),
            'parent_id' => null,
            'creator_id' => User::factory(),
            'assignee_id' => null,
            'title' => fake()->sentence(6),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::Draft,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'progress' => 0,
            'planned_quantity' => null,
            'actual_quantity' => null,
            'quantity_unit' => null,
            'due_at' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'completed_at' => null,
        ];
    }

    public function withQuantity(int $planned = 500, int $actual = 0, ?string $unit = 'hồ sơ'): static
    {
        return $this->state(fn (): array => [
            'planned_quantity' => $planned,
            'actual_quantity' => $actual,
            'quantity_unit' => $unit,
            'progress' => Task::progressFromQuantity($planned, $actual),
        ]);
    }
}
