<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LevelOfOperations;
use App\Models\Tenant\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->slug(2),
            'name' => fake()->unique()->company(),
            'address' => fake()->address(),
            'office_location' => fake()->city().', '.fake()->country(),
            'latitude' => null,
            'longitude' => null,
            'postal_code' => fake()->postcode(),
            'country_id' => null,
            'level_of_operations' => LevelOfOperations::Local,
            'trading_name' => fake()->optional(0.5)->company(),
            'website' => fake()->optional(0.7)->url(),
            'email' => fake()->optional(0.8)->companyEmail(),
            'phone' => fake()->optional(0.8)->phoneNumber(),
            'logo' => null,
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

    public function international(): static
    {
        return $this->state(['level_of_operations' => LevelOfOperations::International]);
    }

    public function regional(): static
    {
        return $this->state(['level_of_operations' => LevelOfOperations::Regional]);
    }
}
