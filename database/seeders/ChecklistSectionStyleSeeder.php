<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Checklist;
use App\Models\ChecklistSectionStyle;
use App\Models\SectionStyle;
use Illuminate\Database\Seeder;

final class ChecklistSectionStyleSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant()?->id;

        $heading1   = SectionStyle::where('style_code', 'H1')->where('tenant_id', $tenantId)->first();
        $heading2   = SectionStyle::where('style_code', 'H2')->where('tenant_id', $tenantId)->first();
        $subheading = SectionStyle::where('style_code', 'SH')->where('tenant_id', $tenantId)->first();
        $bodyText   = SectionStyle::where('style_code', 'BT')->where('tenant_id', $tenantId)->first();
        $highlighted = SectionStyle::where('style_code', 'HL')->where('tenant_id', $tenantId)->first();
        $note       = SectionStyle::where('style_code', 'NT')->where('tenant_id', $tenantId)->first();

        $checklists = Checklist::where('tenant_id', $tenantId)
            ->orderBy('id')
            ->take(5)
            ->get();

        $assignments = [
            [
                'checklist_id'    => $checklists->get(0)?->id,
                'section_style_id' => $heading1?->id,
                'sort_order'      => 1,
            ],
            [
                'checklist_id'    => $checklists->get(1)?->id,
                'section_style_id' => $heading2?->id,
                'sort_order'      => 1,
            ],
            [
                'checklist_id'    => $checklists->get(2)?->id,
                'section_style_id' => $subheading?->id,
                'sort_order'      => 1,
            ],
            [
                'checklist_id'    => $checklists->get(3)?->id,
                'section_style_id' => $bodyText?->id,
                'sort_order'      => 1,
            ],
            [
                'checklist_id'    => $checklists->get(4)?->id,
                'section_style_id' => $highlighted?->id,
                'sort_order'      => 1,
            ],
            [
                'checklist_id'    => $checklists->get(0)?->id,
                'section_style_id' => $note?->id,
                'sort_order'      => 2,
            ],
        ];

        foreach ($assignments as $data) {
            if ($data['checklist_id'] === null || $data['section_style_id'] === null) {
                continue;
            }

            ChecklistSectionStyle::updateOrCreate(
                [
                    'checklist_id'    => $data['checklist_id'],
                    'section_style_id' => $data['section_style_id'],
                    'tenant_id'       => $tenantId,
                ],
                [
                    'sort_order' => $data['sort_order'],
                    'is_active'  => true,
                ]
            );
        }
    }
}
