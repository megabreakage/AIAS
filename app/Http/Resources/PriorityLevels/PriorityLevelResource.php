<?php

declare(strict_types=1);

namespace App\Http\Resources\PriorityLevels;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PriorityLevelResource extends JsonResource
{
    protected ?string $message = null;

    protected array $metadata = [];

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function addMetadata(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->identifier,
            'name' => $this->name,
            'description' => $this->description,
            'level' => $this->level,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->identifier,
                'name' => $this->createdBy->full_name ?? $this->createdBy->name ?? null,
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => [
                'id' => $this->updatedBy->identifier,
                'name' => $this->updatedBy->full_name ?? $this->updatedBy->name ?? null,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    public function with(Request $request): array
    {
        $extra = [];

        if ($this->message !== null) {
            $extra['message'] = $this->message;
        }

        if (! empty($this->metadata)) {
            $extra['metadata'] = $this->metadata;
        }

        return $extra;
    }
}
