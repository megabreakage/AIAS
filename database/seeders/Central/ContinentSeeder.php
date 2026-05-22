<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Central\Continent;
use Illuminate\Database\Seeder;

class ContinentSeeder extends Seeder
{
    public function run(): void
    {
        $continents = [
            [
                'name' => 'Africa',
                'slug' => 'africa',
                'short_code' => 'AF',
                'iso_code' => 'AF',
                'status' => true,
            ],
            [
                'name' => 'Antarctica',
                'slug' => 'antarctica',
                'short_code' => 'AN',
                'iso_code' => 'AN',
                'status' => true,
            ],
            [
                'name' => 'Asia',
                'slug' => 'asia',
                'short_code' => 'AS',
                'iso_code' => 'AS',
                'status' => true,
            ],
            [
                'name' => 'Europe',
                'slug' => 'europe',
                'short_code' => 'EU',
                'iso_code' => 'EU',
                'status' => true,
            ],
            [
                'name' => 'North America',
                'slug' => 'north-america',
                'short_code' => 'NA',
                'iso_code' => 'NA',
                'status' => true,
            ],
            [
                'name' => 'Oceania',
                'slug' => 'oceania',
                'short_code' => 'OC',
                'iso_code' => 'OC',
                'status' => true,
            ],
            [
                'name' => 'South America',
                'slug' => 'south-america',
                'short_code' => 'SA',
                'iso_code' => 'SA',
                'status' => true,
            ],
        ];

        foreach ($continents as $data) {
            $continent = Continent::on('central')->firstOrCreate(
                ['name' => $data['name']],
                $data,
            );

            if ($continent->wasRecentlyCreated) {
                $this->command->info("Created continent: {$data['name']}");
            } else {
                $this->command->line("Continent already exists: {$data['name']}");
            }
        }
    }
}
