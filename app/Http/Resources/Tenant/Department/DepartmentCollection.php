<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Department;

use App\Http\Resources\BaseResourceCollection;

class DepartmentCollection extends BaseResourceCollection
{
    public $collects = DepartmentResource::class;
}
