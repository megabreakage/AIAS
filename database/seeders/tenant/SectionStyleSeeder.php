<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Tenant\SectionStyle;
use Illuminate\Database\Seeder;

class SectionStyleSeeder extends Seeder
{
    public function run(): void
    {
        $styles = [
            [
                'name' => 'Single Column',
                'description' => 'Standard single-column layout for sequential question flow.',
                'columns' => 1,
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Two Column',
                'description' => 'Side-by-side two-column layout for comparison or grouped items.',
                'columns' => 2,
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Three Column',
                'description' => 'Three-column grid for dense data entry or multi-criteria sections.',
                'columns' => 3,
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Grid Layout',
                'description' => 'Four-column grid layout for matrix-style assessments.',
                'columns' => 4,
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Full Width',
                'description' => 'Expanded single-column layout for narrative responses and detailed observations.',
                'columns' => 1,
                'is_active' => true,
                'is_featured' => true,
            ],
        ];

        foreach ($styles as $data) {
            SectionStyle::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
