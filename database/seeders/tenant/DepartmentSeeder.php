<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Finance & Accounting',
                'address' => '1st Floor, Block A',
                'office_location' => 'Nairobi',
                'latitude' => -1.2920659,
                'longitude' => 36.8219462,
                'postal_code' => '00100',
                'country_id' => 1,
                'description' => 'Manages financial operations, reporting, and budgeting.',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Human Resources',
                'address' => '2nd Floor, Block A',
                'office_location' => 'Nairobi',
                'latitude' => -1.2920659,
                'longitude' => 36.8219462,
                'postal_code' => '00100',
                'country_id' => 1,
                'description' => 'Handles recruitment, employee relations, and workforce development.',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Information Technology',
                'address' => '3rd Floor, Block B',
                'office_location' => 'Nairobi',
                'latitude' => -1.2920659,
                'longitude' => 36.8219462,
                'postal_code' => '00100',
                'country_id' => 1,
                'description' => 'Oversees technology infrastructure, systems, and cybersecurity.',
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Operations',
                'address' => 'Ground Floor, Block C',
                'office_location' => 'Nairobi',
                'latitude' => -1.2920659,
                'longitude' => 36.8219462,
                'postal_code' => '00100',
                'country_id' => 1,
                'description' => 'Coordinates day-to-day business operations and logistics.',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Quality Assurance',
                'address' => '4th Floor, Block B',
                'office_location' => 'Nairobi',
                'latitude' => -1.2920659,
                'longitude' => 36.8219462,
                'postal_code' => '00100',
                'country_id' => 1,
                'description' => 'Ensures product and process quality through auditing and continuous improvement.',
                'is_active' => true,
                'is_featured' => true,
            ],
        ];

        foreach ($departments as $data) {
            Department::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
