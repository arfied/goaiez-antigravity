<?php

declare(strict_types=1);

use App\Enums\LifecycleRung;
use App\Services\Billing\Subscriptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The claim that arbitrates the no-card trial ladder (9395–9398).
 *
 * ⛔ **IT IS THE CLAIM AND THE RECORD IN ONE COLUMN, WHICH IS THE OPPOSITE
 * CHOICE FROM `renewal_reminded_for` + `renewal_reminder_claimed_at`, AND THE
 * REASON IS THE CONSEQUENCE RATHER THAN THE SHAPE** (9396). That pair is split
 * because a pre-renewal notice is a Civil Code §1637 duty: a claim that never
 * lapsed would convert *"sent twice"* into *"never sent"*, and those two are not
 * comparable when one of them is a statutory failure with the customer's remedy
 * attached. **A trial warning is not a duty on anybody** — `RenewalReminders`'
 * own docblock says so in as many words, *"it is not a duty on every
 * subscription and inventing one would be its own kind of wrong"* — and a
 * no-card trial cannot auto-convert, because there is no payment instrument. So
 * the asymmetry runs the other way here: a duplicate *"4 days left"* is a
 * message this application sent about a fact that had already been stated, and a
 * missed one costs a person one of four warnings. **This column therefore never
 * lapses**, and the failure it leaves standing is the cheap one.
 *
 * ⛔ **IT IS WRITTEN ONLY FORWARD AND THERE IS NO BACKFILL, WHICH IS WHAT MAKES
 * IT ADDABLE AT ALL** (9398). `subscriptions` is `ENABLE` + `FORCE ROW LEVEL
 * SECURITY` on `app.business_id`; a migration establishes no tenant, so
 * `UPDATE subscriptions SET … ` from here matches **zero rows and reports
 * success** — the failure
 * `2026_08_14_113356_store_message_cost_entries_in_millicents` records in full,
 * and the reason {@see Subscriptions::noCardTrialEndsAt()} is derived rather
 * than stored. **Nothing has to be backfilled here**: `NULL` is the correct
 * value for every existing row, because no rung has ever been sent to anybody.
 *
 * ⚠️ **A ROW THAT PREDATES THIS COLUMN IS NOT SILENTLY CAUGHT UP, AND THAT IS
 * DELIBERATE.** The ladder fires on *equality* with a days-remaining figure, so
 * a business whose trial ran out three weeks ago matches no rung and is mailed
 * nothing. The alternative — `<=` on the landing rung — would announce
 * *"Paused, not gone"* to every abandoned signup in the database on the first
 * scheduled run, about a pause that happened weeks earlier.
 *
 * ⛔ **A `string` COLUMN CAST TO A PHP BACKED ENUM, NEVER A DATABASE `enum`.**
 * `CLAUDE.md`'s rule and `ArchitectureTest`'s lint; the CHECK below bounds the
 * value without becoming a second source of truth that cannot be reordered.
 *
 * ⚠️ **THE CHECK NAMES THE TRIAL RUNGS AND NOT EVERY RUNG.** The usage ladder's
 * three beats are a different clock — credits consumed, not days elapsed — and
 * a `usage_90` landing in this column would mean some future sender had reached
 * for the nearest-looking claim. The predicate is built from
 * {@see LifecycleRung::trialRungs()} so it cannot drift from the enum.
 *
 * No RLS statements here: `subscriptions` was created with `ENABLE`, `FORCE` and
 * its `tenant_isolation` policy in `2026_07_30_080943_create_subscriptions_table`,
 * and a column added to a protected table inherits the protection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('trial_rung_sent')->nullable();
        });

        $values = implode(', ', array_map(
            static fn (LifecycleRung $rung): string => "'".$rung->value."'",
            LifecycleRung::trialRungs(),
        ));

        DB::statement(
            'ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_trial_rung_sent_is_a_trial_rung '
            ."CHECK (trial_rung_sent IS NULL OR trial_rung_sent IN ({$values}))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_trial_rung_sent_is_a_trial_rung');

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('trial_rung_sent');
        });
    }
};
