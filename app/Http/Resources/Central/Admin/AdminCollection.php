<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class AdminCollection extends ResourceCollection
{
    public string $collects = AdminResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
