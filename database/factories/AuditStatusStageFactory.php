<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditStatusStageStatus;
use App\Models\Tenant\AuditStatusStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditStatusStage>
 */
class AuditStatusStageFactory extends Factory
{
    protected $model = AuditStatusStage::class;

    public function definition(): array
    {
        return [
            'audit_id' => null,
            'status' => AuditStatusStageStatus::Scheduled->value,
            'changed_at' => now(),
            'changed_by' => null,
            'notes' => fake()->optional(0.5)->sentence(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state([
            'status' => AuditStatusStageStatus::Scheduled->value,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state([
            'status' => AuditStatusStageStatus::InProgress->value,
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => AuditStatusStageStatus::Completed->value,
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'status' => AuditStatusStageStatus::Closed->value,
        ]);
    }
}
