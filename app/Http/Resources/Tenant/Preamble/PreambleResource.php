<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Preamble;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PreambleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'identifier' => $this->identifier,
            'reference_number' => $this->reference_number,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'effective_date' => $this->effective_date?->toDateString(),
            'is_featured' => $this->is_featured,
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
