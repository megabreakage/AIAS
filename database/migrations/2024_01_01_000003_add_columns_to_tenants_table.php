<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('id');
            $table->string('plan')->default('starter')->after('name');
            $table->string('status')->default('active')->after('plan');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['name', 'plan', 'status']);
        });
    }
};
