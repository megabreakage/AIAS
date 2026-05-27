<?php

declare(strict_types=1);

use App\Models\PriorityLevel;

it('has correct table name for PriorityLevel', function (): void {
    $model = new PriorityLevel();

    expect($model->getTable())->toBe('priority_levels');
});

it('uses identifier as route key for PriorityLevel', function (): void {
    $model = new PriorityLevel();

    expect($model->getRouteKeyName())->toBe('identifier');
});

it('has fillable attributes for PriorityLevel', function (): void {
    $model = new PriorityLevel();
    $fillable = $model->getFillable();

    expect($fillable)->toContain('identifier')
        ->toContain('created_by')
        ->toContain('updated_by');
});

it('uses soft deletes for PriorityLevel', function (): void {
    $model = new PriorityLevel();

    expect(method_exists($model, 'trashed'))->toBeTrue();
});
