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
        Schema::connection('central')->create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier')->unique()->index();
            $table->unsignedBigInteger('owner_id');
            $table->string('name')->unique()->index();
            $table->string('domain')->nullable()->unique();
            $table->string('logo')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('data_center')->nullable();
            $table->json('data')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenants');
    }
};
