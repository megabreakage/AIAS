<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->identifier,
            'identifier' => $this->identifier,
            'title' => $this->title,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'country_code' => $this->country_code,
            'phone' => $this->phone,
            'preferred_timezone' => $this->preferred_timezone,
            'office_location' => $this->office_location,
            'avatar' => $this->avatar,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at?->toISOString(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'identifier' => $this->creator?->identifier,
                'name' => trim(($this->creator?->first_name ?? '').' '.($this->creator?->last_name ?? '')),
            ]),
            'updated_by' => $this->whenLoaded('updater', fn () => [
                'identifier' => $this->updatedBy?->identifier,
                'name' => trim(($this->updatedBy?->first_name ?? '').' '.($this->updatedBy?->last_name ?? '')),
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
