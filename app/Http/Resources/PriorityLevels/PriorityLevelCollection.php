<?php

declare(strict_types=1);

namespace App\Http\Resources\PriorityLevels;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class PriorityLevelCollection extends ResourceCollection
{
    public $collects = PriorityLevelResource::class;

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
        return $this->collection->toArray();
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
