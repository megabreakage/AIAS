<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priority_levels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('identifier')->unique()->index();
            $table->string('tenant_id')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('level');
            $table->string('color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priority_levels');
    }
};
