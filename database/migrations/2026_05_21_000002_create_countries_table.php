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
        Schema::connection('central')->create('countries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('identifier')->unique()->index();
            $table->string('name')->unique();
            $table->string('slug')->nullable();
            $table->foreignId('continent_id')->constrained('continents')->cascadeOnDelete();
            $table->string('short_code', 10)->nullable()->index();
            $table->string('iso_code', 10)->nullable();
            $table->string('currency', 5)->nullable();
            $table->string('currency_name', 50)->nullable();
            $table->string('currency_sign', 5)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->unsignedTinyInteger('phone_digits')->nullable();
            $table->boolean('status')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('super_admins')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('super_admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('countries');
    }
};
