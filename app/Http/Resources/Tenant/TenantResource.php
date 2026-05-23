<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
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
                'identifier' => $this->owner?->identifier,
                'name' => trim(($this->owner?->first_name ?? '').' '.($this->owner?->last_name ?? '')),
                'role' => $this->owner?->roles?->firstWhere('name', 'tenant-admin')?->name,
            ]),
            'country' => $this->whenLoaded('country', fn () => [
                'identifier' => $this->country?->identifier,
                'name' => $this->country?->name,
                'short_code' => $this->country?->short_code,
            ]),
            'data_center' => $this->data_center,
            'domains' => $this->whenLoaded('domains', fn () => $this->domains->pluck('domain')),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'identifier' => $this->createdBy?->identifier,
                'name' => trim(($this->createdBy?->first_name ?? '').' '.($this->createdBy?->last_name ?? '')),
            ]),
            'updated_by' => $this->whenLoaded('updater', fn () => [
                'identifier' => $this->updatedBy?->identifier,
                'name' => trim(($this->updatedBy?->first_name ?? '').' '.($this->updatedBy?->last_name ?? '')),
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
