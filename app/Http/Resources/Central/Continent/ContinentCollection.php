<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Continent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class ContinentCollection extends ResourceCollection
{
    public string $collects = ContinentResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
