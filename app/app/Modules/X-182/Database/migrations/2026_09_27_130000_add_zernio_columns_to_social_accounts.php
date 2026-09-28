<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('provider')->default('zernio');
            $table->string('provider_profile_ref')->nullable();
            $table->string('account_ref')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('last_error', 255)->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();

            $table->string('account_handle')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn([
                'provider',
                'provider_profile_ref',
                'account_ref',
                'status',
                'location_id',
                'last_error',
                'connected_at',
                'disconnected_at',
            ]);
            $table->string('account_handle')->nullable(false)->change();
        });
    }
};
