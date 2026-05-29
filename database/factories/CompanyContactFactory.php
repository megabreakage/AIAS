<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContactType;
use App\Models\Tenant\CompanyContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyContact>
 */
class CompanyContactFactory extends Factory
{
    protected $model = CompanyContact::class;

    public function definition(): array
    {
        return [
            'company_id' => null,
            'user_id' => null,
            'contact_type' => ContactType::Primary,
        ];
    }

    public function primary(): static
    {
        return $this->state(['contact_type' => ContactType::Primary]);
    }

    public function secondary(): static
    {
        return $this->state(['contact_type' => ContactType::Secondary]);
    }

    public function billing(): static
    {
        return $this->state(['contact_type' => ContactType::Billing]);
    }

    public function technical(): static
    {
        return $this->state(['contact_type' => ContactType::Technical]);
    }
}
