<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\Continent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Continent> */
class ContinentFactory extends Factory
{
    protected $model = Continent::class;

    public function definition(): array
    {
        $name = fake()->unique()->word().' Continent';

        return [
            'identifier' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'short_code' => strtoupper(fake()->lexify('??')),
            'iso_code' => strtoupper(fake()->lexify('??')),
            'status' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => false]);
    }

    public function active(): static
    {
        return $this->state(['status' => true]);
    }
}
