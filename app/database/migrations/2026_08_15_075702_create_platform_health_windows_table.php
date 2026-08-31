<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the platform's own machinery did this hour — P23's counters.
 *
 * ## The subject is the platform, never a tenant
 *
 * `sending_health_windows` is the per-tenant twin of this table and answers a
 * different question: *"is THIS tenant's list healthy."* This one answers *"is
 * the machinery running"* — did the scheduler tick, did a worker pick a job up,
 * are the model providers answering, is somebody posting forged webhooks. None
 * of those is a fact about a business, and every one of them is a fact about us.
 *
 * ⚠️ **THEREFORE NO TENANT AND NO RLS**, on `platform_halt_incidents`' precedent
 * (2119) and `compliance_suppressions`' before it (483). A nullable
 * `business_id` would force a policy that admits NULL, and a policy admitting
 * NULL admits every row — which is how a tenant table quietly stops being one.
 * The allowlist entry in `TenancyTest` carries the argument in full.
 *
 * ⚠️ **WHAT REPLACES THE SCOPE.** There is nothing here to scope. A row is a
 * signal name, a vendor or endpoint name of ours, an hour, and two counters.
 * **No customer, no phone number, no address, no business id** — the AI counters
 * are written from inside a tenant's queued job and still record only the
 * provider, because a per-tenant model-error rate is the tenant dashboard's job
 * and putting one tenant's name in a platform-wide row support reads routinely
 * is the leak 2119 refused for the halt incidents.
 *
 * ## Why one row per hour rather than one row per event
 *
 * A row per vendor call is an unbounded table written on the hottest path this
 * application has. Hourly buckets bound it at (signals × sources × 24) rows a
 * day — a few dozen — and every read this feature makes is a SUM over a window
 * anyway. `sending_health_windows` made the same call for the same reason, and
 * its increment idiom is reused here verbatim.
 *
 * ⚠️ **`last_at` IS NOT DERIVABLE FROM `window_start` AND IS WHAT MAKES THE
 * HEARTBEAT WORK.** The bucket says *which hour* something was seen in, to the
 * hour; a heartbeat needs *when it was last seen*, to the minute, because the
 * whole question is whether the last beat is fifteen minutes old. Without this
 * column a scheduler that died at 09:05 would read as alive until 10:00.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_health_windows', function (Blueprint $table): void {
            $table->id();

            // The `PlatformHealthSignal` enum's value. A string cast to a PHP
            // backed enum, never a database enum — `CLAUDE.md`'s rule, and the
            // set here churns by construction: every new vendor and every new
            // watched subject adds one.
            $table->string('signal', 40);

            // Which vendor, endpoint or process this row counts — `anthropic`,
            // `stripe`, `scheduler`. ⚠️ **A NAME OF OURS, NEVER A CREDENTIAL, A
            // URL WITH A QUERY STRING OR ANYTHING A PERSON TYPED.** `VendorLog`
            // makes the same rule for the same reason: a vendor's own error
            // string can carry the access token that produced it.
            $table->string('source', 60);

            // Always UTC, and always the start of an hour. A local-time bucket
            // would double-count one hour and lose another every time the
            // clocks move, on a counter whose whole purpose is comparing an
            // interval against a threshold.
            $table->timestamp('window_start');

            // Observations, and the subset of them that failed. `total` is
            // written on every observation including the failures, so a rate is
            // `failures / total` and never `failures / (total + failures)`.
            //
            // ⚠️ **A SIGNAL MAY LEGITIMATELY COUNT ONLY FAILURES**, and the
            // webhook signature counter is one: nothing increments a total on
            // the happy path, so `total === failures` there and the check that
            // reads it is an absolute count rather than a rate. That is stated
            // on `PlatformHealthSignal` beside each case, because a rate
            // computed over a failure-only signal is always 100% and would fire
            // on the first forged request anybody sent us.
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('failures')->default(0);

            // The most recent observation in this bucket — see the class
            // docblock. Nullable for no reason other than that a row created by
            // a future writer that forgets it should be visibly wrong rather
            // than silently claim the epoch.
            $table->timestamp('last_at')->nullable();

            $table->timestamps();

            // ⚠️ **LOAD-BEARING RATHER THAN TIDY.** Every write is an
            // `INSERT … ON CONFLICT DO UPDATE`, and without this index there is
            // nothing to conflict on: every observation would insert a fresh row
            // and every rate would divide by a denominator scattered across
            // hundreds of them.
            $table->unique(['signal', 'source', 'window_start']);

            // The reads are "this signal, since this hour" and the prune is
            // "everything before this hour".
            $table->index(['signal', 'window_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_health_windows');
    }
};
