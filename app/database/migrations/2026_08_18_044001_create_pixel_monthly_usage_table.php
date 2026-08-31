<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 4 — the monthly event cap and the
 * counter it needs (decision 5000s).
 *
 * §11: *"Monthly cap (`free_event_cap_monthly`). At cap: pageviews continue,
 * others dropped, `events_dropped++`, ops alerted once. Never bill, never
 * hard-fail."* §21's frozen decisions pin the figure: *"Event cap — 500k/
 * tenant/month."*
 *
 * ⚠️ **NOT `tenant_usage`.** §9's operational schema names a `tenant_usage`
 * table carrying `events`/`events_dropped`/`emails_sent`/`llm_tokens` — a
 * platform-wide usage ledger this application does not have and this
 * migration does not build. Adding four columns nothing but this one row
 * needs to a table shaped for a ledger this codebase never adopted would be
 * two sources of truth for "how many pixel events has this tenant sent this
 * month" the moment anything else touched it. This table holds exactly what
 * §11 row 4 needs and nothing `tenant_usage`'s other three columns would have
 * added.
 *
 * ⚠️ **A VOLUME CAP, NOT A COST CAP — 3293's deletion of the dollar ceiling
 * does not reach this row.** `CLAUDE.md`'s commercial-model section deletes
 * the per-tenant *dollar* cost cap in favour of the credit balance; this is
 * `29` §2's free-tier infrastructure budget (§2.1: *"Cloudflare Workers 100k
 * req/day free … design to stay inside this"*), never billed and never
 * spending a credit ledger. Nothing here debits `credit_ledger`, and 3297's
 * enumeration is unaffected: this path was never a money path.
 *
 * ⚠️ **TENANT-OWNED, UNLIKE ITS TWO NEIGHBOURS IN THIS DIRECTORY**
 * (`zernio_account_days`, `voice_usage_events`). Both of those are exempt
 * from row-level security because the rows that matter most carry no
 * business, or must remain visible to a platform-wide budget query. Neither
 * argument applies here: `PixelCollector` only ever reaches this gate *after*
 * `Tenancy::set()`, so every row genuinely belongs to one business and the
 * ordinary tenant-isolation policy is the right one — `audit_log`'s shape,
 * not `voice_usage_events`'s.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pixel_monthly_usage', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // The first day of the calendar month this row counts. A `date`
            // rather than a `year`/`month` pair, so `ORDER BY` and range
            // queries need no arithmetic — matched against `ZernioAccountDay`'s
            // month-boundary reasoning, though the billing-cycle question that
            // motivates that class does not apply to a free-tier volume cap.
            $table->date('month');

            $table->unsignedBigInteger('events_total')->default(0);
            $table->unsignedBigInteger('events_dropped')->default(0);

            $table->timestamps();

            // One row per business per month — the unique index the atomic
            // `INSERT … ON CONFLICT` upsert in `App\Services\Pixel\
            // MonthlyEventCap` relies on to make a concurrent increment safe.
            $table->unique(['business_id', 'month']);
        });

        DB::statement('ALTER TABLE pixel_monthly_usage ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE pixel_monthly_usage FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON pixel_monthly_usage
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('pixel_monthly_usage');
    }
};
