<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Company;

use App\Http\Resources\BaseResourceCollection;

class CompanyCollection extends BaseResourceCollection
{
    public $collects = CompanyResource::class;
}
