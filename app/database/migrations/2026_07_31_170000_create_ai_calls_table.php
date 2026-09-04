<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every model call this platform makes, and what it cost.
 *
 * TENANT-OWNED, WITH A NON-NULLABLE KEY — unlike `places_api_calls`, which is
 * the deliberate opposite. That table records spend from the free public audit,
 * which runs *before any tenant exists*, so its `business_id` is nullable and it
 * sits on the TenancyTest allowlist with that written down. Nothing here
 * runs before a tenant exists: every AI call in this system serves a review, a
 * reply, or a piece of content belonging to one business.
 *
 * The non-nullable key is the enforcement, not a description. `CLAUDE.md` rule
 * 43 makes per-tenant cost caps mandatory and server-side, and a cap is checked
 * by summing this table — so a row that cannot be attributed to a tenant is
 * spend that cannot be capped. Making that unrepresentable at the column is
 * cheaper than a test asserting nobody ever writes one.
 *
 * WHY THE MODEL AND THE TASK ARE BOTH STORED, when the task implies the model
 * today: because it will not. AiRouter reads `platform_settings` to pick the
 * model for a task, so the mapping changes without a deploy — and the question
 * this table exists to answer after a surprising bill is "which model was
 * serving analysis last Tuesday", which a derived answer cannot give.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema::create removed for ai_calls to fix duplicates

        DB::statement('ALTER TABLE ai_calls ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE ai_calls FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON ai_calls
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_calls');
    }
};
