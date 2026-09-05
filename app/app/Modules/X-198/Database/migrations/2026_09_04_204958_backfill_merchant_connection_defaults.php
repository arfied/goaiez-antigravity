<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * X-198 — rows written before 2026_09_04_201248 added the two columns hold
 * NULL; 2026_09_04_204959's change() rebuilds them NOT NULL and refuses on
 * such a row (goaiez_antig_money, 2026-09-05 02:2x, SQLSTATE[23502]). This
 * migration sorts before 204959 by name and fills the NULLs with the default
 * 204959 declares. Data only — no Schema call; a no-op where no NULL exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('merchant_connections')
            ->whereNull('merchant_status')
            ->update(['merchant_status' => 'external_gateway']);

        DB::table('merchant_connections')
            ->whereNull('merchant_relationship')
            ->update(['merchant_relationship' => 'external_gateway']);
    }

    public function down(): void
    {
        // The NULLs are not restored: a default that 204959 makes NOT NULL cannot be un-filled.
    }
};
