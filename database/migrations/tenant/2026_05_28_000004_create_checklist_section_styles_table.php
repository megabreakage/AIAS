<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_section_styles', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('checklist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_style_id')->constrained()->cascadeOnDelete();
            $table->string('section_title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['checklist_id', 'section_style_id', 'sort_order'], 'css_checklist_style_sort_unique');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_section_styles');
    }
};
