<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Country;

use App\Http\Resources\BaseResourceCollection;

class CountryCollection extends BaseResourceCollection
{
    public $collects = CountryResource::class;
}
