<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Country;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CountryResource extends BaseResource
{
    public function resourceData(Request $request): array
    {
        return [
            'identifier' => $this->identifier,
            'name' => $this->name,
            'slug' => $this->slug,
            'continent' => $this->whenLoaded('continent', fn () => [
                'id' => $this->continent?->id,
                'identifier' => $this->continent?->identifier,
                'name' => $this->continent?->name,
            ]),
            'short_code' => $this->short_code,
            'iso_code' => $this->iso_code,
            'currency' => $this->currency,
            'currency_name' => $this->currency_name,
            'currency_sign' => $this->currency_sign,
            'country_code' => $this->country_code,
            'phone_digits' => $this->phone_digits,
            'status' => $this->status,
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'identifier' => $this->creator?->identifier,
                'name' => trim(($this->creator?->first_name ?? '').' '.($this->creator?->last_name ?? '')),
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => [
                'id' => $this->updatedBy?->id,
                'identifier' => $this->updatedBy?->identifier,
                'name' => trim(($this->updatedBy?->first_name ?? '').' '.($this->updatedBy?->last_name ?? '')),
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
