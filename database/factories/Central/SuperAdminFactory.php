<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\SuperAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<SuperAdmin> */
class SuperAdminFactory extends Factory
{
    protected $model = SuperAdmin::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'identifier' => (string) Str::uuid(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'country_code' => '+254',
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
