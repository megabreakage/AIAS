<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier')->unique()->index();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->enum('contact_type', ['primary', 'secondary', 'billing', 'technical'])->default('primary');
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('contact_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_contacts');
    }
};
