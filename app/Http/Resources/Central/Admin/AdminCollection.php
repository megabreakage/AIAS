<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Admin;

use App\Http\Resources\BaseResourceCollection;

class AdminCollection extends BaseResourceCollection
{
    public $collects = AdminResource::class;
}
