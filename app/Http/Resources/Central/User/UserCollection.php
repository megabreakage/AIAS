<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\User;

use App\Http\Resources\BaseResourceCollection;

final class UserCollection extends BaseResourceCollection
{
    public $collects = UserResource::class;
}
