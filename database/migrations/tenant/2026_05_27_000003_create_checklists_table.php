<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier')->unique()->index();
            $table->string('tenant_id')->index();
            $table->string('reference_number')->unique()->index()->nullable();
            $table->string('name');
            $table->unsignedBigInteger('quality_controller_id')->nullable();
            $table->foreignId('preamble_id')->nullable()->constrained('preambles')->nullOnDelete();
            $table->foreignId('checklist_type_id')->nullable()->constrained('checklist_types')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('is_featured');
            $table->index('quality_controller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklists');
    }
};
