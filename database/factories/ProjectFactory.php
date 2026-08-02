<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
final class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'organization_unit_id' => OrganizationUnit::factory(),
            'owner_id' => User::factory(),
            'code' => 'PRJ-'.fake()->unique()->numberBetween(1, 999999),
            'name' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => ProjectStatus::Active,
            'start_date' => null,
            'end_date' => null,
            'closed_at' => null,
            'close_reason' => null,
        ];
    }
}
