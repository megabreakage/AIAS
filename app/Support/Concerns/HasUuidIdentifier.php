<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use Illuminate\Support\Str;

/**
 * Auto-generates a UUID identifier on model creation and sets 'identifier'
 * as the route key name. Mirrors the behaviour of BaseModel for non-tenant
 * models that need string-based route binding.
 */
trait HasUuidIdentifier
{
    protected static function bootHasUuidIdentifier(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->identifier)) {
                $model->identifier = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }
}
