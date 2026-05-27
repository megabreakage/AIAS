<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Company;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CompanyResource extends BaseResource
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
            'level_of_operations' => $this->level_of_operations?->value,
            'trading_name' => $this->trading_name,
            'website' => $this->website,
            'email' => $this->email,
            'phone' => $this->phone,
            'logo' => $this->logo,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'contacts' => $this->whenLoaded('contacts', function () {
                return $this->contacts->map(fn ($contact) => [
                    'id' => $contact->id,
                    'identifier' => $contact->identifier,
                    'contact_type' => $contact->contact_type?->value,
                    'user_id' => $contact->user_id,
                    'contact_user' => $contact->relationLoaded('contactUser') && $contact->contactUser ? [
                        'id' => $contact->contactUser->id,
                        'identifier' => $contact->contactUser->identifier,
                        'name' => trim(($contact->contactUser->first_name ?? '').' '.($contact->contactUser->last_name ?? '')),
                    ] : null,
                    'created_at' => $contact->created_at?->toISOString(),
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
