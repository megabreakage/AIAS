<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Enums\LevelOfOperations;
use App\Models\Tenant\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Acme Corporation',
                'address' => '123 Business Ave, Suite 100',
                'office_location' => 'Nairobi',
                'latitude' => -1.2920659,
                'longitude' => 36.8219462,
                'postal_code' => '00100',
                'country_id' => 1,
                'level_of_operations' => LevelOfOperations::International,
                'trading_name' => 'Acme Corp',
                'website' => 'https://acme.example.com',
                'email' => 'info@acme.example.com',
                'phone' => '+254700000001',
                'description' => 'Global leader in quality management solutions.',
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Global Tech Solutions',
                'address' => '456 Innovation Drive',
                'office_location' => 'Mombasa',
                'latitude' => -4.0434771,
                'longitude' => 39.6682065,
                'postal_code' => '80100',
                'country_id' => 1,
                'level_of_operations' => LevelOfOperations::Regional,
                'trading_name' => 'GTS',
                'website' => 'https://gts.example.com',
                'email' => 'contact@gts.example.com',
                'phone' => '+254700000002',
                'description' => 'Regional technology services and consulting firm.',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Green Energy Ltd',
                'address' => '789 Sustainability Road',
                'office_location' => 'Kisumu',
                'latitude' => -0.1021700,
                'longitude' => 34.7617000,
                'postal_code' => '40100',
                'country_id' => 1,
                'level_of_operations' => LevelOfOperations::Local,
                'trading_name' => 'GreenE',
                'website' => 'https://greenenergy.example.com',
                'email' => 'hello@greenenergy.example.com',
                'phone' => '+254700000003',
                'description' => 'Renewable energy provider focused on solar and wind.',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'SafeGuard Insurance',
                'address' => '321 Finance Plaza, 5th Floor',
                'office_location' => 'Nairobi',
                'latitude' => -1.2863900,
                'longitude' => 36.8172500,
                'postal_code' => '00200',
                'country_id' => 1,
                'level_of_operations' => LevelOfOperations::Regional,
                'trading_name' => 'SafeGuard',
                'website' => 'https://safeguard.example.com',
                'email' => 'support@safeguard.example.com',
                'phone' => '+254700000004',
                'description' => 'Insurance and risk management services provider.',
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Prime Logistics',
                'address' => '555 Transport Highway',
                'office_location' => 'Nakuru',
                'latitude' => -0.3031000,
                'longitude' => 36.0800000,
                'postal_code' => '20100',
                'country_id' => 1,
                'level_of_operations' => LevelOfOperations::Local,
                'trading_name' => 'Prime Log',
                'website' => 'https://primelogistics.example.com',
                'email' => 'ops@primelogistics.example.com',
                'phone' => '+254700000005',
                'description' => 'Supply chain and logistics management company.',
                'is_active' => true,
                'is_featured' => false,
            ],
        ];

        foreach ($companies as $data) {
            Company::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
