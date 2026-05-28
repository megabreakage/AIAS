<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier')->unique()->index();
            $table->string('tenant_id')->index();
            $table->string('reference_number')->nullable()->unique()->index();
            $table->string('name');
            $table->foreignId('checklist_id')->nullable()->constrained('checklists')->nullOnDelete();
            $table->unsignedBigInteger('task_type_id')->nullable();
            $table->string('scope')->default('internal')->index();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->timestamp('audit_start_date');
            $table->timestamp('audit_end_date')->nullable();
            $table->unsignedBigInteger('lead_auditor_id')->nullable();
            $table->unsignedBigInteger('quality_manager_id')->nullable();
            $table->boolean('add_appendix')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_featured');
            $table->index('lead_auditor_id');
            $table->index('quality_manager_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
