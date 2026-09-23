<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_domain_requests', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('failure_reason')->nullable();
        });

        DB::statement("
            CREATE POLICY host_lookup ON custom_domain_requests
                FOR SELECT
                USING (status = 'verified')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS host_lookup ON custom_domain_requests');

        Schema::table('custom_domain_requests', function (Blueprint $table) {
            $table->dropColumn(['verified_at', 'last_checked_at', 'failure_reason']);
        });
    }
};
