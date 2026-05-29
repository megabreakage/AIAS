<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\ChecklistType;

use App\Http\Resources\BaseResourceCollection;

class ChecklistTypeCollection extends BaseResourceCollection
{
    public $collects = ChecklistTypeResource::class;
}
