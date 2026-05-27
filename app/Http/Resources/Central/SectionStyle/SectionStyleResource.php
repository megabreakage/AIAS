<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\SectionStyle;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class SectionStyleResource extends BaseResource
{
    public function resourceData(Request $request): array
    {
        return [
            'identifier' => $this->identifier,
            'name' => $this->name,
            'description' => $this->description,
            'columns' => $this->columns,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'identifier' => $this->creator?->identifier,
                'name' => trim(($this->creator?->first_name ?? '').' '.($this->creator?->last_name ?? '')),
            ]),
            'updated_by' => $this->whenLoaded('updater', fn () => [
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
