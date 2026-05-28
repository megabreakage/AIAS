<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PriorityLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriorityLevel>
 */
final class PriorityLevelFactory extends Factory
{
    protected $model = PriorityLevel::class;

    public function definition(): array
    {
        return [
            'identifier' => fake()->uuid(),
            'tenant_id'  => function_exists('tenant') && tenant() ? tenant()->id : 'test-tenant',
            'name'       => fake()->unique()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'level'      => fake()->numberBetween(1, 10),
            'color'      => fake()->optional()->hexColor(),
            'is_active'  => fake()->boolean(80),
        ];
    }
}
