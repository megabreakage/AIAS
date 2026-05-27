<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->slug(2),
            'name' => fake()->unique()->words(3, true),
            'address' => fake()->optional(0.7)->address(),
            'office_location' => fake()->optional(0.6)->city().', '.fake()->optional(0.6)->country(),
            'latitude' => null,
            'longitude' => null,
            'postal_code' => fake()->optional(0.6)->postcode(),
            'country_id' => null,
            'department_head' => null,
            'description' => fake()->optional(0.6)->paragraph(),
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
