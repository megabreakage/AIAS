<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TenantResource extends BaseResource
{
    public function resourceData(Request $request): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->identifier,
            'reference_number' => $this->reference_number,
            'name' => $this->name,
            'domain' => $this->domain,
            'logo' => $this->logo,
            'status' => $this->status?->value ?? $this->status,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner?->identifier,
                'name' => trim(($this->owner?->first_name ?? '').' '.($this->owner?->last_name ?? '')),
                'email' => $this->owner->email,
            ]),
            'roles' => $this->whenLoaded('ownerRoles', fn () => $this->ownerRoles->pluck('name')),
            'country_id' => $this->country_id,
            'country' => $this->whenLoaded('country', fn () => [
                'identifier' => $this->country?->identifier,
                'name' => $this->country?->name,
                'short_code' => $this->country?->short_code,
            ]),
            'data_center' => $this->data_center,
            'domains' => $this->whenLoaded('domains', fn () => $this->domains->pluck('domain')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
