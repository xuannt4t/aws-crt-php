<?php

namespace Database\Factories;

use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Models\OrganizationUnit;
use App\Models\TaskRecurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskRecurrence>
 */
final class TaskRecurrenceFactory extends Factory
{
    protected $model = TaskRecurrence::class;

    public function definition(): array
    {
        return [
            'organization_unit_id' => OrganizationUnit::factory(),
            'project_id' => null,
            'creator_id' => User::factory(),
            'assignee_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'planned_quantity' => null,
            'quantity_unit' => null,
            'frequency' => RecurrenceFrequency::Weekly,
            'interval' => 1,
            'weekdays' => [1],
            'day_of_month' => null,
            'start_date' => now()->toDateString(),
            'due_time' => null,
            'is_active' => true,
            'last_generated_for' => null,
        ];
    }

    /**
     * @param  list<int>  $weekdays  ISO weekdays, 1 = Thứ Hai … 7 = Chủ Nhật.
     */
    public function weekly(array $weekdays = [1]): static
    {
        return $this->state(fn (): array => [
            'frequency' => RecurrenceFrequency::Weekly,
            'weekdays' => $weekdays,
            'day_of_month' => null,
        ]);
    }

    public function daily(int $interval = 1): static
    {
        return $this->state(fn (): array => [
            'frequency' => RecurrenceFrequency::Daily,
            'interval' => $interval,
            'weekdays' => null,
            'day_of_month' => null,
        ]);
    }

    public function monthly(int $dayOfMonth = 1): static
    {
        return $this->state(fn (): array => [
            'frequency' => RecurrenceFrequency::Monthly,
            'weekdays' => null,
            'day_of_month' => $dayOfMonth,
        ]);
    }

    public function quarterly(int $dayOfMonth = 1): static
    {
        return $this->state(fn (): array => [
            'frequency' => RecurrenceFrequency::Quarterly,
            'weekdays' => null,
            'day_of_month' => $dayOfMonth,
        ]);
    }
}
