<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Priority;
use Illuminate\Database\Seeder;

final class PrioritySeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant()?->id;

        $priorities = [
            [
                'name'        => 'Critical',
                'description' => 'Requires immediate attention. A critical issue that could cause significant harm or disruption if not addressed urgently.',
                'level'       => 1,
                'color'       => '#dc2626',
            ],
            [
                'name'        => 'High',
                'description' => 'High importance issue that must be addressed promptly to prevent escalation or significant impact.',
                'level'       => 2,
                'color'       => '#ea580c',
            ],
            [
                'name'        => 'Medium',
                'description' => 'Moderate importance issue that should be addressed in a timely manner within the standard review cycle.',
                'level'       => 3,
                'color'       => '#d97706',
            ],
            [
                'name'        => 'Low',
                'description' => 'Low importance issue that can be addressed at the next available opportunity without immediate urgency.',
                'level'       => 4,
                'color'       => '#65a30d',
            ],
            [
                'name'        => 'Informational',
                'description' => 'Informational observation with no immediate action required. Noted for awareness and future consideration.',
                'level'       => 5,
                'color'       => '#0284c7',
            ],
        ];

        foreach ($priorities as $data) {
            Priority::updateOrCreate(
                ['name' => $data['name'], 'tenant_id' => $tenantId],
                [
                    'description' => $data['description'],
                    'level'       => $data['level'],
                    'color'       => $data['color'],
                    'is_active'   => true,
                ]
            );
        }
    }
}
