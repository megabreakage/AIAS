<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Continent;

use App\Http\Resources\BaseResourceCollection;
use Illuminate\Http\Request;

final class ContinentCollection extends BaseResourceCollection
{
    public string $collects = ContinentResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
