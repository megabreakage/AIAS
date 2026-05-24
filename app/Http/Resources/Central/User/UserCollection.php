<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\User;

use Illuminate\Http\Resources\Json\ResourceCollection;

final class UserCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     */
    public $collects = UserResource::class;
}
