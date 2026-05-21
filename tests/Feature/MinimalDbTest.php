<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can refresh database', function (): void {
    expect(true)->toBeTrue();
});
