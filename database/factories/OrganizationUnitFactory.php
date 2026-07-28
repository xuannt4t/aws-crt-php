<?php

namespace Database\Factories;

use App\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationUnit>
 */
class OrganizationUnitFactory extends Factory
{
    protected $model = OrganizationUnit::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->company(),
            'code' => fake()->unique()->regexify('[A-Z]{3}[0-9]{3}'),
            'is_active' => true,
        ];
    }
}
