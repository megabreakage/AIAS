<?php

declare(strict_types=1);

namespace Database\Seeders\Central;

use App\Models\Central\Continent;
use App\Models\Central\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            // Africa
            ['name' => 'Kenya', 'continent' => 'Africa', 'short_code' => 'KE', 'iso_code' => 'KEN', 'currency' => 'KES', 'currency_name' => 'Kenyan Shilling', 'currency_sign' => 'KSh', 'country_code' => '+254', 'phone_digits' => 9],
            ['name' => 'Nigeria', 'continent' => 'Africa', 'short_code' => 'NG', 'iso_code' => 'NGA', 'currency' => 'NGN', 'currency_name' => 'Nigerian Naira', 'currency_sign' => '₦', 'country_code' => '+234', 'phone_digits' => 10],
            ['name' => 'South Africa', 'continent' => 'Africa', 'short_code' => 'ZA', 'iso_code' => 'ZAF', 'currency' => 'ZAR', 'currency_name' => 'South African Rand', 'currency_sign' => 'R', 'country_code' => '+27', 'phone_digits' => 9],
            ['name' => 'Egypt', 'continent' => 'Africa', 'short_code' => 'EG', 'iso_code' => 'EGY', 'currency' => 'EGP', 'currency_name' => 'Egyptian Pound', 'currency_sign' => '£', 'country_code' => '+20', 'phone_digits' => 10],
            ['name' => 'Ghana', 'continent' => 'Africa', 'short_code' => 'GH', 'iso_code' => 'GHA', 'currency' => 'GHS', 'currency_name' => 'Ghanaian Cedi', 'currency_sign' => '₵', 'country_code' => '+233', 'phone_digits' => 9],

            // Asia
            ['name' => 'Japan', 'continent' => 'Asia', 'short_code' => 'JP', 'iso_code' => 'JPN', 'currency' => 'JPY', 'currency_name' => 'Japanese Yen', 'currency_sign' => '¥', 'country_code' => '+81', 'phone_digits' => 10],
            ['name' => 'India', 'continent' => 'Asia', 'short_code' => 'IN', 'iso_code' => 'IND', 'currency' => 'INR', 'currency_name' => 'Indian Rupee', 'currency_sign' => '₹', 'country_code' => '+91', 'phone_digits' => 10],
            ['name' => 'China', 'continent' => 'Asia', 'short_code' => 'CN', 'iso_code' => 'CHN', 'currency' => 'CNY', 'currency_name' => 'Chinese Yuan', 'currency_sign' => '¥', 'country_code' => '+86', 'phone_digits' => 11],
            ['name' => 'South Korea', 'continent' => 'Asia', 'short_code' => 'KR', 'iso_code' => 'KOR', 'currency' => 'KRW', 'currency_name' => 'South Korean Won', 'currency_sign' => '₩', 'country_code' => '+82', 'phone_digits' => 10],
            ['name' => 'Singapore', 'continent' => 'Asia', 'short_code' => 'SG', 'iso_code' => 'SGP', 'currency' => 'SGD', 'currency_name' => 'Singapore Dollar', 'currency_sign' => '$', 'country_code' => '+65', 'phone_digits' => 8],

            // Europe
            ['name' => 'United Kingdom', 'continent' => 'Europe', 'short_code' => 'GB', 'iso_code' => 'GBR', 'currency' => 'GBP', 'currency_name' => 'British Pound', 'currency_sign' => '£', 'country_code' => '+44', 'phone_digits' => 10],
            ['name' => 'Germany', 'continent' => 'Europe', 'short_code' => 'DE', 'iso_code' => 'DEU', 'currency' => 'EUR', 'currency_name' => 'Euro', 'currency_sign' => '€', 'country_code' => '+49', 'phone_digits' => 11],
            ['name' => 'France', 'continent' => 'Europe', 'short_code' => 'FR', 'iso_code' => 'FRA', 'currency' => 'EUR', 'currency_name' => 'Euro', 'currency_sign' => '€', 'country_code' => '+33', 'phone_digits' => 9],
            ['name' => 'Italy', 'continent' => 'Europe', 'short_code' => 'IT', 'iso_code' => 'ITA', 'currency' => 'EUR', 'currency_name' => 'Euro', 'currency_sign' => '€', 'country_code' => '+39', 'phone_digits' => 10],
            ['name' => 'Spain', 'continent' => 'Europe', 'short_code' => 'ES', 'iso_code' => 'ESP', 'currency' => 'EUR', 'currency_name' => 'Euro', 'currency_sign' => '€', 'country_code' => '+34', 'phone_digits' => 9],

            // North America
            ['name' => 'United States', 'continent' => 'North America', 'short_code' => 'US', 'iso_code' => 'USA', 'currency' => 'USD', 'currency_name' => 'United States Dollar', 'currency_sign' => '$', 'country_code' => '+1', 'phone_digits' => 10],
            ['name' => 'Canada', 'continent' => 'North America', 'short_code' => 'CA', 'iso_code' => 'CAN', 'currency' => 'CAD', 'currency_name' => 'Canadian Dollar', 'currency_sign' => '$', 'country_code' => '+1', 'phone_digits' => 10],
            ['name' => 'Mexico', 'continent' => 'North America', 'short_code' => 'MX', 'iso_code' => 'MEX', 'currency' => 'MXN', 'currency_name' => 'Mexican Peso', 'currency_sign' => '$', 'country_code' => '+52', 'phone_digits' => 10],
            ['name' => 'Costa Rica', 'continent' => 'North America', 'short_code' => 'CR', 'iso_code' => 'CRI', 'currency' => 'CRC', 'currency_name' => 'Costa Rican Colón', 'currency_sign' => '₡', 'country_code' => '+506', 'phone_digits' => 8],
            ['name' => 'Jamaica', 'continent' => 'North America', 'short_code' => 'JM', 'iso_code' => 'JAM', 'currency' => 'JMD', 'currency_name' => 'Jamaican Dollar', 'currency_sign' => '$', 'country_code' => '+1', 'phone_digits' => 7],

            // South America
            ['name' => 'Brazil', 'continent' => 'South America', 'short_code' => 'BR', 'iso_code' => 'BRA', 'currency' => 'BRL', 'currency_name' => 'Brazilian Real', 'currency_sign' => 'R$', 'country_code' => '+55', 'phone_digits' => 11],
            ['name' => 'Argentina', 'continent' => 'South America', 'short_code' => 'AR', 'iso_code' => 'ARG', 'currency' => 'ARS', 'currency_name' => 'Argentine Peso', 'currency_sign' => '$', 'country_code' => '+54', 'phone_digits' => 10],
            ['name' => 'Colombia', 'continent' => 'South America', 'short_code' => 'CO', 'iso_code' => 'COL', 'currency' => 'COP', 'currency_name' => 'Colombian Peso', 'currency_sign' => '$', 'country_code' => '+57', 'phone_digits' => 10],
            ['name' => 'Chile', 'continent' => 'South America', 'short_code' => 'CL', 'iso_code' => 'CHL', 'currency' => 'CLP', 'currency_name' => 'Chilean Peso', 'currency_sign' => '$', 'country_code' => '+56', 'phone_digits' => 9],
            ['name' => 'Peru', 'continent' => 'South America', 'short_code' => 'PE', 'iso_code' => 'PER', 'currency' => 'PEN', 'currency_name' => 'Peruvian Sol', 'currency_sign' => 'S/', 'country_code' => '+51', 'phone_digits' => 9],

            // Oceania
            ['name' => 'Australia', 'continent' => 'Oceania', 'short_code' => 'AU', 'iso_code' => 'AUS', 'currency' => 'AUD', 'currency_name' => 'Australian Dollar', 'currency_sign' => '$', 'country_code' => '+61', 'phone_digits' => 9],
            ['name' => 'New Zealand', 'continent' => 'Oceania', 'short_code' => 'NZ', 'iso_code' => 'NZL', 'currency' => 'NZD', 'currency_name' => 'New Zealand Dollar', 'currency_sign' => '$', 'country_code' => '+64', 'phone_digits' => 9],
            ['name' => 'Fiji', 'continent' => 'Oceania', 'short_code' => 'FJ', 'iso_code' => 'FJI', 'currency' => 'FJD', 'currency_name' => 'Fijian Dollar', 'currency_sign' => '$', 'country_code' => '+679', 'phone_digits' => 7],
            ['name' => 'Papua New Guinea', 'continent' => 'Oceania', 'short_code' => 'PG', 'iso_code' => 'PNG', 'currency' => 'PGK', 'currency_name' => 'Papua New Guinean Kina', 'currency_sign' => 'K', 'country_code' => '+675', 'phone_digits' => 8],
            ['name' => 'Samoa', 'continent' => 'Oceania', 'short_code' => 'WS', 'iso_code' => 'WSM', 'currency' => 'WST', 'currency_name' => 'Samoan Tālā', 'currency_sign' => 'T', 'country_code' => '+685', 'phone_digits' => 7],

            // Antarctica
            ['name' => 'Antarctica Territory', 'continent' => 'Antarctica', 'short_code' => 'AQ', 'iso_code' => 'ATA', 'currency' => 'USD', 'currency_name' => 'United States Dollar', 'currency_sign' => '$', 'country_code' => '+672', 'phone_digits' => 6],
        ];

        foreach ($countries as $data) {
            $continent = Continent::on('central')->where('name', $data['continent'])->first();

            if (!$continent) {
                $this->command->warn("Continent not found: {$data['continent']} — skipping {$data['name']}");

                continue;
            }

            unset($data['continent']);
            $data['continent_id'] = $continent->id;
            $data['slug'] = Str::slug($data['name']);

            $country = Country::on('central')->firstOrCreate(
                ['name' => $data['name']],
                $data,
            );

            if ($country->wasRecentlyCreated) {
                $this->command->info("Created country: {$data['name']}");
            } else {
                $this->command->line("Country already exists: {$data['name']}");
            }
        }
    }
}
