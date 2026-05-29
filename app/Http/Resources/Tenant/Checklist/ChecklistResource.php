<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Checklist;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ChecklistResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function resourceData(Request $request): array
    {
        return [
            'identifier' => $this->identifier,
            'tenant_id' => $this->tenant_id,
            'reference_number' => $this->reference_number,
            'name' => $this->name,
            'quality_controller_id' => $this->quality_controller_id,
            'preamble_id' => $this->preamble_id,
            'checklist_type_id' => $this->checklist_type_id,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'preamble' => $this->whenLoaded('preamble', fn () => $this->preamble ? [
                'id' => $this->preamble->id,
                'identifier' => $this->preamble->identifier,
                'name' => $this->preamble->name,
                'reference_number' => $this->preamble->reference_number,
            ] : null),
            'checklist_type' => $this->whenLoaded('checklistType', fn () => $this->checklistType ? [
                'id' => $this->checklistType->id,
                'identifier' => $this->checklistType->identifier,
                'name' => $this->checklistType->name,
            ] : null),
            'quality_controller' => $this->whenLoaded('qualityController', fn () => $this->qualityController ? [
                'id' => $this->qualityController->id,
                'identifier' => $this->qualityController->identifier,
                'name' => trim(($this->qualityController->first_name ?? '').' '.($this->qualityController->last_name ?? '')),
            ] : null),
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
