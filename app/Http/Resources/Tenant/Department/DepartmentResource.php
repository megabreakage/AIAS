<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Department;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class DepartmentResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function resourceData(Request $request): array
    {
        return [
            'identifier' => $this->identifier,
            'tenant_id' => $this->tenant_id,
            'reference_number' => $this->reference_number,
            'name' => $this->name,
            'address' => $this->address,
            'office_location' => $this->office_location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'postal_code' => $this->postal_code,
            'country_id' => $this->country_id,
            'department_head' => $this->department_head,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'head' => $this->whenLoaded('head', fn () => $this->head ? [
                'id' => $this->head->id,
                'identifier' => $this->head->identifier,
                'name' => trim(($this->head->first_name ?? '').' '.($this->head->last_name ?? '')),
            ] : null),
            'members' => $this->whenLoaded('members', function () {
                return $this->members->map(fn ($member) => [
                    'id' => $member->id,
                    'identifier' => $member->identifier,
                    'user_id' => $member->user_id,
                    'member_user' => $member->relationLoaded('memberUser') && $member->memberUser ? [
                        'id' => $member->memberUser->id,
                        'identifier' => $member->memberUser->identifier,
                        'name' => trim(($member->memberUser->first_name ?? '').' '.($member->memberUser->last_name ?? '')),
                    ] : null,
                    'created_at' => $member->created_at?->toISOString(),
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
