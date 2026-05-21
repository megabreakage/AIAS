<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->identifier,
            'name' => $this->name,
            'domain' => $this->domain,
            'logo' => $this->logo,
            'status' => $this->status?->value ?? $this->status,
            'owner_id' => $this->owner_id,
            'country_id' => $this->country_id,
            'data_center' => $this->data_center,
            'domains' => $this->whenLoaded('domains', fn () => $this->domains->pluck('domain')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
