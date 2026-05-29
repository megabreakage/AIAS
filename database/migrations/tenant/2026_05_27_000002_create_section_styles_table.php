<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_styles', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier')->unique()->index();
            $table->string('tenant_id');
            $table->string('name')->unique()->index();
            $table->text('description')->nullable();
            $table->unsignedInteger('columns')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('is_featured');
            $table->index('columns');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_styles');
    }
};
