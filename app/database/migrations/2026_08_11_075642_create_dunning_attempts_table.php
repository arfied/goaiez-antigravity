<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every dunning attempt, and the record that decides a suspension
 * (T137 SL-11, decisions 2108 and 2133).
 *
 * ⚠️ **AUTHORIZE.NET HAS NO NATIVE DUNNING AND T137 SAYS SO IN THE ROSTER
 * ITSELF**: *"webhook consumer + dunning/retry + suspension logic built
 * explicitly (AuthNet has no native dunning — this is in-scope L1 work, not
 * assumed)"*. Verified against the vendor's live Recurring Billing documentation
 * (read 2026-08-11): a declined payment **suspends** the subscription, the
 * vendor does not retry it, and it is **terminated** if the merchant takes no
 * action before the next run date. There is no dunning email, no smart retry and
 * no published schedule. Everything a Stripe integration gets for free is a row
 * in this table.
 *
 * ⚠️ **THE TABLE IS THE DECISION, NOT A LOG OF IT.** The suspension rule is "N
 * declines and then the tenant loses the product", and N is counted from these
 * rows — so an attempt that ran and was not recorded silently extends a tenant's
 * grace period, and one recorded twice shortens it. That is why the schedule
 * position is a column with a unique key on it rather than a `COUNT(*)`: a
 * retried job must land on the row it already wrote.
 *
 * ⚠️ **AND IT IS DELIBERATELY NOT `activity_feed` OR `audit_log`.** Both of
 * those are written for a reader; this is written for a *rule*. `CLAUDE.md`
 * requires every automated action reach the activity feed and every sensitive
 * one reach the audit log, and dunning does both — but neither of those tables
 * may be the thing a suspension counts, because both are append-only records
 * whose shape is owned elsewhere and whose rows are filtered for display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dunning_attempts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            /*
             * Which failure this schedule is recovering from.
             *
             * ⚠️ NOT A FOREIGN KEY TO `subscriptions`. One business has one
             * subscription row (unique `business_id`), so the tenant column
             * already identifies it — and a second sequence can legitimately
             * start after the first one ended, which a foreign key would not
             * distinguish. This is what does: a monotonically increasing
             * sequence number per business, so "the current schedule" is the
             * highest one.
             */
            $table->unsignedInteger('sequence');

            /*
             * Which attempt within the schedule — 1, 2, 3.
             *
             * ⚠️ UNIQUE WITH THE SEQUENCE, AND THAT PAIR IS THE IDEMPOTENCY.
             * §3 rail 1: "retries and double-clicks can never duplicate a
             * message or a debit". A retried job writes attempt 2 of sequence 7
             * again and hits this index rather than adding a second row that
             * would count twice toward suspension.
             */
            $table->unsignedSmallInteger('attempt');

            // A string cast to App\Enums\DunningOutcome — never a Postgres enum
            // type (`CLAUDE.md`, decision 863).
            $table->string('outcome');

            /*
             * The vendor's own reason code, when there is one.
             *
             * ⚠️ A CODE, NEVER THE VENDOR'S TEXT. Authorize.Net's message text
             * quotes the value it rejected — a card's last four, a customer
             * description — and this row is read on a support screen.
             * `AuthorizeNetRequestFailed` makes the same rule at the boundary;
             * this is where it would otherwise be undone by whoever wanted a
             * friendlier column.
             */
            $table->string('reason_code')->nullable();

            $table->timestamp('attempted_at');

            /*
             * When the next attempt is due, or null if this outcome ended the
             * schedule.
             *
             * ⚠️ **BACKOFF WITH JITTER IS COMPUTED WHEN THE ROW IS WRITTEN, NOT
             * WHEN THE JOB RUNS.** A schedule recomputed at dispatch time drifts
             * every time the queue is busy, and — the part that matters — every
             * tenant whose card fails in the same hour retries in the same
             * second, which is a thundering herd at a payment gateway. Storing
             * the instant makes the spread real rather than nominal.
             */
            $table->timestamp('next_attempt_at')->nullable();

            $table->unique(['business_id', 'sequence', 'attempt']);

            // "Where is this tenant's current schedule up to" is the only
            // question the suspension rule asks.
            $table->index(['business_id', 'sequence']);
        });

        DB::statement('ALTER TABLE dunning_attempts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE dunning_attempts FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON dunning_attempts
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        // Attempt numbering starts at 1. A zeroth attempt is what an off-by-one
        // writes, and it would give every tenant one extra decline before
        // suspension — an error that only ever runs in the tenant's favour and
        // is therefore never reported.
        DB::statement(<<<'SQL'
            ALTER TABLE dunning_attempts
                ADD CONSTRAINT dunning_attempts_attempt_starts_at_one
                CHECK (attempt >= 1)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dunning_attempts');
    }
};
