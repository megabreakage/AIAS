<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Audit;

use App\Http\Resources\BaseResourceCollection;

class AuditCollection extends BaseResourceCollection
{
    public $collects = AuditResource::class;
}
