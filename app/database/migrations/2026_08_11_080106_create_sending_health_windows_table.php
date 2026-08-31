<?php

declare(strict_types=1);

use App\Services\Messaging\SendingHealth;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant sending outcomes, in hourly buckets — T137 §3.2, and the numbers
 * 2102's automatic trip actually reads.
 *
 * ## Why this table exists when `number_health_daily` already counts things
 *
 * That table is **per number** and it is the carrier-reputation scorer (doc `51`
 * §4). This is **per tenant** and it is the containment for 2101: R8 sends every
 * tenant's traffic over the GOAIEZ 10DLC brand from our own pool, so *"the
 * tenant carries the legal basis while the platform carries the carrier
 * reputation"*. When one tenant's list starts generating complaints, the damage
 * lands on every other tenant's delivery — and the only thing that can contain
 * it is knowing **which tenant**, which a per-number score cannot answer while a
 * pool number is shared.
 *
 * ⚠️ **R8 MAKES THE TWO NEARLY THE SAME TODAY AND THAT IS EXACTLY WHY THIS IS
 * SEPARATE.** One number per tenant means per-number and per-tenant currently
 * agree, so a reviewer could reasonably ask why both exist. They agree only
 * while the mapping is one-to-one: the shared Lane A pool number belongs to no
 * tenant, a tenant with a second number would split its own counts across two
 * scores, and a number recycled between tenants would carry the first one's
 * history into the second's trip decision. **The containment must key on the
 * thing being contained.**
 *
 * ## Hourly buckets, not a rolling scan of `outreach_messages`
 *
 * The trip is a **precondition of every send** (2113), so its input is read on
 * the hot path — once per message, not once per campaign. A `COUNT(*) … WHERE
 * created_at > now() - interval '24 hours'` over a tenant's outreach history is
 * a table scan per message, and it gets slower exactly as a tenant sends more,
 * which is precisely when the trip matters. An hourly bucket is a fixed 24-row
 * read and an upsert per event.
 *
 * ⚠️ **AND IT IS WHY THE TRIP CAN BE OBSERVED MID-FLIGHT.** A campaign runner
 * that checked a rate once at enqueue would authorise ten thousand messages on
 * one reading. Because the counters update as the campaign runs and the guard is
 * consulted per message, a campaign trips itself partway through — which is the
 * only version of this that helps, since the complaints arrive *because* the
 * campaign is running.
 *
 * ## §3.5: partitioned and prunable from day one
 *
 * ⚠️ **PRUNABLE IS THE PROPERTY THAT MATTERS AND IT IS STRUCTURAL HERE, NOT A
 * CRON JOB'S GOOD INTENTIONS.** `window_start` is the partition key in every
 * sense that counts: rows are immutable once their hour closes, a delete is a
 * range delete on an indexed column, and no row is ever updated after its bucket
 * passes. Declarative Postgres partitioning is deliberately **not** used yet —
 * it cannot be added to a table Laravel's schema builder created without raw
 * DDL, it complicates the unique constraint the upsert depends on, and at one
 * row per tenant per hour this table is four orders of magnitude smaller than
 * the DLR stream §3.5 is really about. **The prune is what makes the claim true,
 * and it is proven by "the prune drops old buckets and keeps recent ones" in
 * `SendingHealthTest`** — asserting a deleted-row count rather than a docblock.
 * ⚠️ **This sentence first cited a `MessagingRetentionTest` that does not
 * exist**, which is 314–316's shape: a docblock claiming enforcement is what
 * stops the next reviewer looking. ⛔ **What is still owed is the scheduled
 * caller** — `SendingHealth::prune()` has a test but no cron entry, so today
 * nothing prunes on its own.
 *
 * ## What is counted, and the one that is not a count
 *
 * `sent`, `delivered`, `failed` and `opted_out` are outcomes of messages this
 * tenant sent. **`complaints` is not**: a complaint is a carrier-reported spam
 * report, and this platform has no carrier feedback loop wired yet. The column
 * ships with a writer for the one signal that genuinely stands in for it today —
 * a STOP arriving in reply to a message we sent — and the distinction is
 * recorded in {@see SendingHealth} rather than lost in a
 * column name. ⚠️ **An opt-out is not a complaint and the two must not be
 * summed**: unsubscribing is a person exercising a right the product is required
 * to offer, and treating every one as a complaint would trip the halt on a
 * healthy list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sending_health_windows', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // The hour this bucket covers, truncated to the hour, always UTC.
            // ⚠️ **UTC RATHER THAN THE TENANT'S ZONE, DELIBERATELY.** A rolling
            // 24-hour complaint rate is a rate over wall-clock time and has
            // nothing to do with anybody's business hours; bucketing in a local
            // zone would make the window shift under a DST boundary and would
            // silently double or drop an hour of a trip decision twice a year.
            $table->timestamp('window_start');

            // The channel, because SMS and email complaint physics are not the
            // same and a shared bucket would let healthy email traffic dilute an
            // SMS complaint rate below the trip threshold.
            $table->string('channel');

            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('opted_out')->default(0);
            $table->unsignedInteger('complaints')->default(0);

            $table->timestamps();

            // The upsert target. Without this the counters race: two queue
            // workers finishing a send in the same hour would each insert a row
            // and every rate would read low by half — an under-read that
            // suppresses the trip, which is the wrong direction to fail in.
            $table->unique(['business_id', 'window_start', 'channel']);

            // The read the guard makes on every send: one tenant, one channel,
            // the last N hours.
            $table->index(['business_id', 'channel', 'window_start']);

            // The prune. A range delete needs this to avoid scanning every
            // tenant's history to find one expired hour.
            $table->index('window_start');
        });

        DB::statement('ALTER TABLE sending_health_windows ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE sending_health_windows FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON sending_health_windows
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('sending_health_windows');
    }
};
