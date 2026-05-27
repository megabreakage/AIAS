<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\SectionStyle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SectionStyle> */
class SectionStyleFactory extends Factory
{
    protected $model = SectionStyle::class;

    public function definition(): array
    {
        return [
            'identifier' => (string) Str::uuid(),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'columns' => fake()->numberBetween(1, 4),
            'is_active' => true,
            'is_featured' => false,
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
