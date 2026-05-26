<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tenant> */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->unique()->company(),
            'domain' => fake()->unique()->domainName(),
        ];
    }
}
