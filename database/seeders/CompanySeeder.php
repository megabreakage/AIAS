<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

final class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = tenant()?->id;

        $companies = [
            [
                'name'     => 'Acme Corporation',
                'code'     => 'ACME',
                'industry' => 'Manufacturing',
                'email'    => 'info@acmecorp.example.com',
                'phone'    => '+1-800-226-3333',
                'address'  => '123 Main Street, Springfield, IL 62701, USA',
            ],
            [
                'name'     => 'Pinnacle Financial Services',
                'code'     => 'PFS',
                'industry' => 'Financial Services',
                'email'    => 'contact@pinnaclefs.example.com',
                'phone'    => '+1-212-555-0100',
                'address'  => '45 Wall Street, New York, NY 10005, USA',
            ],
            [
                'name'     => 'GreenLeaf Energy Ltd',
                'code'     => 'GLE',
                'industry' => 'Energy',
                'email'    => 'hello@greenleafenergy.example.com',
                'phone'    => '+44-20-7946-0958',
                'address'  => '10 Energy House, London, EC2A 4NE, UK',
            ],
            [
                'name'     => 'NextGen Tech Solutions',
                'code'     => 'NGTS',
                'industry' => 'Technology',
                'email'    => 'support@nextgentech.example.com',
                'phone'    => '+1-415-555-0199',
                'address'  => '1 Innovation Drive, San Francisco, CA 94105, USA',
            ],
            [
                'name'     => 'Horizon Healthcare Group',
                'code'     => 'HHG',
                'industry' => 'Healthcare',
                'email'    => 'enquiries@horizonhealth.example.com',
                'phone'    => '+1-312-555-0177',
                'address'  => '200 Medical Plaza, Chicago, IL 60601, USA',
            ],
            [
                'name'     => 'BlueSky Logistics',
                'code'     => 'BSL',
                'industry' => 'Logistics',
                'email'    => 'ops@blueskylogistics.example.com',
                'phone'    => '+61-2-9876-5432',
                'address'  => '88 Harbour Drive, Sydney, NSW 2000, Australia',
            ],
        ];

        foreach ($companies as $data) {
            Company::updateOrCreate(
                ['name' => $data['name'], 'tenant_id' => $tenantId],
                [
                    'code'      => $data['code'],
                    'industry'  => $data['industry'],
                    'email'     => $data['email'],
                    'phone'     => $data['phone'],
                    'address'   => $data['address'],
                    'is_active' => true,
                ]
            );
        }
    }
}
