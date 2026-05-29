<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Tenant\PriorityLevel;
use Illuminate\Database\Seeder;

class PriorityLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [
                'name' => 'Low',
                'level' => 1,
                'color' => '#22c55e',
                'description' => 'Low priority — no immediate action required.',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Medium',
                'level' => 2,
                'color' => '#f59e0b',
                'description' => 'Medium priority — should be addressed within the planned timeline.',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'High',
                'level' => 3,
                'color' => '#ef4444',
                'description' => 'High priority — requires immediate attention and resolution.',
                'is_active' => true,
                'is_featured' => true,
            ],
        ];

        foreach ($levels as $data) {
            PriorityLevel::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
