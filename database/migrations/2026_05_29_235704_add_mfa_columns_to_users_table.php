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
        Schema::connection('central')->table('users', function (Blueprint $table): void {
            $table->string('mfa_method')->nullable()->after('is_active');
            $table->text('mfa_secret')->nullable()->after('mfa_method');
            $table->boolean('mfa_enabled')->default(false)->after('mfa_secret');
            $table->timestamp('mfa_confirmed_at')->nullable()->after('mfa_enabled');
            $table->json('mfa_backup_codes')->nullable()->after('mfa_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'mfa_method',
                'mfa_secret',
                'mfa_enabled',
                'mfa_confirmed_at',
                'mfa_backup_codes',
            ]);
        });
    }
};
