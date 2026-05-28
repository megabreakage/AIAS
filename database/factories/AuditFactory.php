<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditScope;
use App\Models\Tenant\Audit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Audit>
 */
class AuditFactory extends Factory
{
    protected $model = Audit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => fake()->slug(2),
            'name' => fake()->unique()->words(3, true),
            'checklist_id' => null,
            'task_type_id' => null,
            'scope' => fake()->randomElement(AuditScope::cases())->value,
            'department_id' => null,
            'audit_start_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'audit_end_date' => fake()->optional(0.4)->dateTimeBetween('+1 month', '+6 months'),
            'lead_auditor_id' => null,
            'quality_manager_id' => null,
            'add_appendix' => fake()->boolean(20),
            'description' => fake()->optional(0.6)->paragraph(),
            'is_featured' => fake()->boolean(20),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function internal(): static
    {
        return $this->state(['scope' => AuditScope::Internal->value]);
    }

    public function external(): static
    {
        return $this->state(['scope' => AuditScope::External->value]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    public function withAppendix(): static
    {
        return $this->state(['add_appendix' => true]);
    }
}
