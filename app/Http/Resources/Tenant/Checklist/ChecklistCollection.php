<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Checklist;

use App\Http\Resources\BaseResourceCollection;

class ChecklistCollection extends BaseResourceCollection
{
    public $collects = ChecklistResource::class;
}
