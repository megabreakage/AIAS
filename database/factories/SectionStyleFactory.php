<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant\SectionStyle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionStyle>
 */
class SectionStyleFactory extends Factory
{
    protected $model = SectionStyle::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->slug(2),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'columns' => fake()->numberBetween(1, 4),
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

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}
