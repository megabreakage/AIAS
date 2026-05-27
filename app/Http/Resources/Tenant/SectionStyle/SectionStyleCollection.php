<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\SectionStyle;

use App\Http\Resources\BaseResourceCollection;

class SectionStyleCollection extends BaseResourceCollection
{
    public $collects = SectionStyleResource::class;
}
