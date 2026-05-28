<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Checklist;
use App\Models\Tenant\ChecklistSectionStyle as ChecklistSectionStyleModel;
use App\Models\Tenant\SectionStyle;
use Illuminate\Database\Seeder;

class ChecklistSectionStyle extends Seeder
{
    public function run(): void
    {
        $checklists = Checklist::all();
        $styles = SectionStyle::all();

        if ($checklists->isEmpty() || $styles->isEmpty()) {
            return;
        }

        $singleColumn = $styles->firstWhere('name', 'Single Column');
        $twoColumn = $styles->firstWhere('name', 'Two Column');
        $threeColumn = $styles->firstWhere('name', 'Three Column');
        $fullWidth = $styles->firstWhere('name', 'Full Width');
        $gridLayout = $styles->firstWhere('name', 'Grid Layout');

        $assignments = [
            [
                'checklist_name' => 'ISO 9001 Compliance Checklist',
                'section_style_id' => $singleColumn?->id,
                'section_title' => 'General Requirements',
                'sort_order' => 1,
            ],
            [
                'checklist_name' => 'ISO 9001 Compliance Checklist',
                'section_style_id' => $twoColumn?->id,
                'section_title' => 'Document Control',
                'sort_order' => 2,
            ],
            [
                'checklist_name' => 'Workplace Safety Checklist',
                'section_style_id' => $threeColumn?->id,
                'section_title' => 'Hazard Identification',
                'sort_order' => 1,
            ],
            [
                'checklist_name' => 'Data Protection Checklist',
                'section_style_id' => $gridLayout?->id,
                'section_title' => 'Access Controls Matrix',
                'sort_order' => 1,
            ],
            [
                'checklist_name' => 'Supplier Evaluation Checklist',
                'section_style_id' => $fullWidth?->id,
                'section_title' => 'Supplier Assessment Summary',
                'sort_order' => 1,
            ],
        ];

        foreach ($assignments as $data) {
            $checklist = $checklists->firstWhere('name', $data['checklist_name']);

            if (! $checklist || ! $data['section_style_id']) {
                continue;
            }

            ChecklistSectionStyleModel::firstOrCreate(
                [
                    'checklist_id' => $checklist->id,
                    'section_style_id' => $data['section_style_id'],
                    'sort_order' => $data['sort_order'],
                ],
                [
                    'section_title' => $data['section_title'],
                ],
            );
        }
    }
}
