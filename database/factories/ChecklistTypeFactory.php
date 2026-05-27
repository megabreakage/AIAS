<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant\ChecklistType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistType>
 */
class ChecklistTypeFactory extends Factory
{
    protected $model = ChecklistType::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->slug(2),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'is_active' => true,
            'is_featured' => fake()->boolean(20),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}
