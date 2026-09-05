<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * X-198 — 2026_09_04_204958_backfill_merchant_connection_defaults ran DONE on
 * goaiez_antig_money (2026-09-05 02:4x) and updated no row: merchant_connections
 * is under FORCE ROW LEVEL SECURITY (2026_08_30_000030), so the owner role is
 * subject to tenant_isolation, a migration sets no app.business_id, and the
 * UPDATE matched nothing — decision 319's shape. NO FORCE for the length of
 * two statements, as 2026_08_10_224405 does; DDL is transactional here, so a
 * failure rolls the suspension back with everything else. A no-op where no
 * NULL exists (the test database). 204959 then makes both columns NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE merchant_connections NO FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            UPDATE merchant_connections
               SET merchant_status = 'external_gateway'
             WHERE merchant_status IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE merchant_connections
               SET merchant_relationship = 'external_gateway'
             WHERE merchant_relationship IS NULL
        SQL);

        DB::statement('ALTER TABLE merchant_connections FORCE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        // The NULLs are not restored: a default that 204959 makes NOT NULL cannot be un-filled.
    }
};
