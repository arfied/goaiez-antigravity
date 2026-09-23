<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            DB::statement('ALTER TABLE webhook_subscriptions ALTER COLUMN secret TYPE text');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            DB::statement('ALTER TABLE webhook_subscriptions ALTER COLUMN secret TYPE character varying(255)');
        });
    }
};
