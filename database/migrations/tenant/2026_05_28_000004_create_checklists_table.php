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
            $table->uuid('identifier')->unique()->index();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('checklist_type_id')->nullable()->index();
            $table->unsignedBigInteger('preamble_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklists');
    }
};
