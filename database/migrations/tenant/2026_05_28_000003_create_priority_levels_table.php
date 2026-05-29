<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priority_levels', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier')->unique();
            $table->string('tenant_id')->index();
            $table->string('name');
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('color', 7)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name']);
            $table->index('is_active');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priority_levels');
    }
};
