<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SectionStyle;
use Illuminate\Database\Seeder;

final class SectionStyleSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant()?->id;

        $styles = [
            [
                'name'        => 'Heading 1',
                'style_code'  => 'H1',
                'description' => 'Primary heading style for major checklist sections.',
                'font_size'   => '24px',
                'font_weight' => 'bold',
                'color'       => '#1a1a2e',
            ],
            [
                'name'        => 'Heading 2',
                'style_code'  => 'H2',
                'description' => 'Secondary heading style for subsections.',
                'font_size'   => '20px',
                'font_weight' => 'bold',
                'color'       => '#16213e',
            ],
            [
                'name'        => 'Subheading',
                'style_code'  => 'SH',
                'description' => 'Tertiary style for grouping related checklist items.',
                'font_size'   => '16px',
                'font_weight' => 'semibold',
                'color'       => '#0f3460',
            ],
            [
                'name'        => 'Body Text',
                'style_code'  => 'BT',
                'description' => 'Standard body text style for checklist item descriptions.',
                'font_size'   => '14px',
                'font_weight' => 'normal',
                'color'       => '#333333',
            ],
            [
                'name'        => 'Highlighted',
                'style_code'  => 'HL',
                'description' => 'Highlighted style to draw attention to critical checklist items.',
                'font_size'   => '14px',
                'font_weight' => 'bold',
                'color'       => '#e94560',
            ],
            [
                'name'        => 'Note',
                'style_code'  => 'NT',
                'description' => 'Italic note style for supplementary information and guidance.',
                'font_size'   => '12px',
                'font_weight' => 'normal',
                'color'       => '#666666',
            ],
        ];

        foreach ($styles as $data) {
            SectionStyle::updateOrCreate(
                ['name' => $data['name'], 'tenant_id' => $tenantId],
                [
                    'style_code'  => $data['style_code'],
                    'description' => $data['description'],
                    'font_size'   => $data['font_size'],
                    'font_weight' => $data['font_weight'],
                    'color'       => $data['color'],
                    'is_active'   => true,
                ]
            );
        }
    }
}
