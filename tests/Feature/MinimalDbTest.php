<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

it('can refresh database', function (): void {
    expect(true)->toBeTrue();
});
