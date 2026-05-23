<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->identifier,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'preferred_timezone' => $this->preferred_timezone,
            'office_location' => $this->office_location,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'identifier' => $this->createdBy?->identifier,
                'name' => trim(($this->createdBy?->first_name ?? '').' '.($this->createdBy?->last_name ?? '')),
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => [
                'identifier' => $this->updatedBy?->identifier,
                'name' => trim(($this->updatedBy?->first_name ?? '').' '.($this->updatedBy?->last_name ?? '')),
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
