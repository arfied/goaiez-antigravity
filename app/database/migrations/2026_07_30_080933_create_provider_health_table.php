<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (business, provider): the current health of that integration
 * (DATA-MODEL §5.2). Refreshed by the token-refresh job.
 *
 * ⛔ **"DRIVES THE RECONNECT NOTIFICATION" IS WHAT THIS SAID UNTIL 2026-08-21
 * AND IT NEVER DID** (7262). `TokenService::markUnusable()` writes this row and
 * calls `promptReconnect()` as two independent statements; nothing reads the
 * table to decide whether to prompt, and deleting every row would not stop a
 * single notification. ⛔ **Nothing reads the table at all**: its one reader in
 * `app/`, `GoogleBusinessService::available()`, has no caller — the untruncated
 * enumeration is in `tests/Feature/Architecture/VisibilityTest.php`.
 *
 * ⚠️ **IT IS NOT A TABLE THAT WANTS DELETING, AND THAT WAS CONSIDERED.** `28`
 * §9.8's health board names these three columns by function — *"integration
 * status lights with last-error drilldown"* — and §10.1's account health score
 * names *"integration health (disconnected OAuth, dead pixel = heavy penalty)"*.
 * Both are **Ops-facing**, and they want a fact the owner-facing path
 * deliberately discards: `VisibilityReading` collapses `quota_exhausted` into
 * plain "unavailable" so a tenant is never shown our capacity problem.
 *
 * ⚠️ **The owner-facing answer does NOT come from here and never did.** It comes
 * from `oauth_connections.status`, through `SearchConsoleProperties::connectionState()`
 * and `VisibilityReadings::read()`, onto `Livewire\Account\Visibility`. This
 * table is that fact's unread Ops-side mirror.
 *
 * Deliberately has no created_at — DATA-MODEL gives it `updated_at` only,
 * because the row is pure current state with no meaningful birth date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_health', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            $table->string('provider');

            $table->timestamp('last_sync_at')->nullable();
            $table->string('token_status')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamp('updated_at')->nullable();

            // Doubles as the business_id-leading index every tenant query and
            // RLS check wants.
            $table->unique(['business_id', 'provider']);
        });

        DB::statement('ALTER TABLE provider_health ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE provider_health FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON provider_health
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_health');
    }
};
