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
        Schema::connection('central')->table('oauth_access_tokens', function (Blueprint $table): void {
            $table->string('tenant_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('oauth_access_tokens', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });
    }
};
