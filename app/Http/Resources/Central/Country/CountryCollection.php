<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Country;

use App\Http\Resources\BaseResourceCollection;
use Illuminate\Http\Request;

final class CountryCollection extends BaseResourceCollection
{
    public string $collects = CountryResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
