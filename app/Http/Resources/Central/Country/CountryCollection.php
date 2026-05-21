<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Country;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class CountryCollection extends ResourceCollection
{
    public string $collects = CountryResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
