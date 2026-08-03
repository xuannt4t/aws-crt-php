<?php

namespace Database\Factories;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectTaskVisibility;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMember>
 */
final class ProjectMemberFactory extends Factory
{
    protected $model = ProjectMember::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'role' => ProjectMemberRole::Member,
            'task_visibility' => ProjectTaskVisibility::Own,
            'joined_at' => now(),
        ];
    }

    public function allTaskVisibility(): static
    {
        return $this->state(fn (array $attributes): array => [
            'task_visibility' => ProjectTaskVisibility::All,
        ]);
    }
}
