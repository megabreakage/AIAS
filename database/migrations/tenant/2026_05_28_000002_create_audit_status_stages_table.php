<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_status_stages', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier')->unique()->index();
            $table->foreignId('audit_id')->constrained('audits')->cascadeOnDelete();
            $table->string('status')->default('scheduled')->index();
            $table->timestamp('changed_at');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_status_stages');
    }
};
