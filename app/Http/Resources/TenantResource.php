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
            'id'         => $this->id,
            'name'       => $this->name ?? $this->data['name'] ?? null,
            'plan'       => $this->plan ?? $this->data['plan'] ?? null,
            'status'     => $this->status ?? $this->data['status'] ?? null,
            'domains'    => $this->whenLoaded('domains', fn () => $this->domains->pluck('domain')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
