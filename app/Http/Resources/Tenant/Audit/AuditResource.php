<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Audit;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AuditResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function resourceData(Request $request): array
    {
        return [
            'identifier' => $this->identifier,
            'tenant_id' => $this->tenant_id,
            'reference_number' => $this->reference_number,
            'name' => $this->name,
            'checklist_id' => $this->checklist_id,
            'task_type_id' => $this->task_type_id,
            'scope' => $this->scope instanceof \App\Enums\AuditScope ? $this->scope->value : $this->scope,
            'department_id' => $this->department_id,
            'audit_start_date' => $this->audit_start_date?->toISOString(),
            'audit_end_date' => $this->audit_end_date?->toISOString(),
            'lead_auditor_id' => $this->lead_auditor_id,
            'quality_manager_id' => $this->quality_manager_id,
            'add_appendix' => $this->add_appendix,
            'description' => $this->description,
            'is_featured' => $this->is_featured,
            'checklist' => $this->whenLoaded('checklist', fn () => $this->checklist ? [
                'id' => $this->checklist->id,
                'identifier' => $this->checklist->identifier,
                'name' => $this->checklist->name,
            ] : null),
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'identifier' => $this->department->identifier,
                'name' => $this->department->name,
            ] : null),
            'lead_auditor' => $this->whenLoaded('leadAuditor', fn () => $this->leadAuditor ? [
                'id' => $this->leadAuditor->id,
                'identifier' => $this->leadAuditor->identifier,
                'name' => trim(($this->leadAuditor->first_name ?? '').' '.($this->leadAuditor->last_name ?? '')),
            ] : null),
            'quality_manager' => $this->whenLoaded('qualityManager', fn () => $this->qualityManager ? [
                'id' => $this->qualityManager->id,
                'identifier' => $this->qualityManager->identifier,
                'name' => trim(($this->qualityManager->first_name ?? '').' '.($this->qualityManager->last_name ?? '')),
            ] : null),
            'latest_status' => $this->whenLoaded('latestStatus', fn () => $this->latestStatus ? [
                'identifier' => $this->latestStatus->identifier,
                'status' => $this->latestStatus->status instanceof \App\Enums\AuditStatusStageStatus
                    ? $this->latestStatus->status->value
                    : $this->latestStatus->status,
                'changed_at' => $this->latestStatus->changed_at?->toISOString(),
                'notes' => $this->latestStatus->notes,
            ] : null),
            'status_stages' => $this->whenLoaded('statusStages', function () {
                return $this->statusStages->map(fn ($stage) => [
                    'identifier' => $stage->identifier,
                    'status' => $stage->status instanceof \App\Enums\AuditStatusStageStatus
                        ? $stage->status->value
                        : $stage->status,
                    'changed_at' => $stage->changed_at?->toISOString(),
                    'changed_by' => $stage->changed_by,
                    'notes' => $stage->notes,
                    'created_at' => $stage->created_at?->toISOString(),
                ]);
            }),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'identifier' => $this->creator?->identifier,
                'name' => trim(($this->creator?->first_name ?? '').' '.($this->creator?->last_name ?? '')),
            ]),
            'updater' => $this->whenLoaded('updater', fn () => [
                'id' => $this->updater?->id,
                'identifier' => $this->updater?->identifier,
                'name' => trim(($this->updater?->first_name ?? '').' '.($this->updater?->last_name ?? '')),
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
