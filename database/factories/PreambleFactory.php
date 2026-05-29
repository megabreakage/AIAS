<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant\Preamble;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Preamble>
 */
class PreambleFactory extends Factory
{
    protected $model = Preamble::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->slug(2),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(Preamble::STATUSES),
            'effective_date' => fake()->optional()->date(),
            'is_featured' => fake()->boolean(20),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => Preamble::STATUS_DRAFT]);
    }

    public function active(): static
    {
        return $this->state(['status' => Preamble::STATUS_ACTIVE]);
    }

    public function archived(): static
    {
        return $this->state(['status' => Preamble::STATUS_ARCHIVED]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}
