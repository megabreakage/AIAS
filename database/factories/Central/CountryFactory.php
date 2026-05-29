<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\Continent;
use App\Models\Central\Country;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Country> */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        $name = fake()->unique()->country();

        return [
            'identifier' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'continent_id' => Continent::factory(),
            'short_code' => strtoupper(fake()->lexify('??')),
            'iso_code' => strtoupper(fake()->lexify('??')),
            'currency' => strtoupper(fake()->lexify('???')),
            'currency_name' => fake()->word().' Dollar',
            'currency_sign' => fake()->randomElement(['$', '€', '£', '¥', '₹']),
            'country_code' => '+'.fake()->numberBetween(1, 999),
            'phone_digits' => fake()->numberBetween(7, 12),
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

    public function forContinent(Continent $continent): static
    {
        return $this->state(['continent_id' => $continent->id]);
    }
}
