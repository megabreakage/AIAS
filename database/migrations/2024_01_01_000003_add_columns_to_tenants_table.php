<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        // No-op: these columns are now defined in the base tenants migration.
    }

    public function down(): void
    {
        // No-op.
    }
};
