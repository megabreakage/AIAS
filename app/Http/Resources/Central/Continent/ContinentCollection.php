<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Continent;

use App\Http\Resources\BaseResourceCollection;

class ContinentCollection extends BaseResourceCollection
{
    public string $collects = ContinentResource::class;
}
