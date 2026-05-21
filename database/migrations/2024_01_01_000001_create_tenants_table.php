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
        Schema::connection('central')->create('tenants', function (Blueprint $table): void {
            $table->id(); // Primary Key, auto-increaments
            $table->uuid('identifier')->unique()->index();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete(); // Must have an existing user with `tenant-admin` role assigned to them
            $table->string('name')->unique()->index();
            $table->string('domain')->nullable(); // if available it should be unique from existing domains
            $table->string('logo')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('data_center')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenants');
    }
};
