<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\SectionStyle;

use App\Http\Resources\BaseResourceCollection;

class SectionStyleCollection extends BaseResourceCollection
{
    public string $collects = SectionStyleResource::class;
}
