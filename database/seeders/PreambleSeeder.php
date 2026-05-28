<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Preamble;
use Illuminate\Database\Seeder;

final class PreambleSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant()?->id;

        $preambles = [
            [
                'title'   => 'Executive Summary',
                'content' => 'This document provides an executive overview of the audit engagement, summarizing key objectives, scope, methodology, and high-level findings for leadership review.',
                'type'    => 'executive',
            ],
            [
                'title'   => 'Scope and Objectives',
                'content' => 'This preamble defines the scope and objectives of the audit. It outlines the areas under review, the period covered, and the specific goals the audit aims to achieve.',
                'type'    => 'scope',
            ],
            [
                'title'   => 'Methodology',
                'content' => 'The audit was conducted in accordance with established standards. This section describes the approach, techniques, and tools used to gather evidence and evaluate controls.',
                'type'    => 'methodology',
            ],
            [
                'title'   => 'Regulatory Compliance',
                'content' => 'This preamble addresses compliance requirements under applicable regulations and frameworks. It identifies the regulatory context governing the audit and the criteria used for evaluation.',
                'type'    => 'compliance',
            ],
            [
                'title'   => 'Risk Assessment Overview',
                'content' => 'This section provides a high-level risk assessment overview, describing the risk landscape, inherent risk factors, and the overall risk appetite of the organisation under review.',
                'type'    => 'risk',
            ],
            [
                'title'   => 'Management Representation',
                'content' => 'Management acknowledges responsibility for the accuracy of information provided during this audit and confirms that all material information has been disclosed to the audit team.',
                'type'    => 'general',
            ],
        ];

        foreach ($preambles as $data) {
            Preamble::updateOrCreate(
                ['title' => $data['title'], 'tenant_id' => $tenantId],
                [
                    'content'   => $data['content'],
                    'type'      => $data['type'],
                    'is_active' => true,
                ]
            );
        }
    }
}
