<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\Preamble;

use App\Http\Resources\BaseResourceCollection;

class PreambleCollection extends BaseResourceCollection
{
    public $collects = PreambleResource::class;
}
