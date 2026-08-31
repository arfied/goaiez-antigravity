<?php

declare(strict_types=1);

use App\Services\Billing\Subscriptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The claim that arbitrates California's pre-renewal notice (6969, 7100–7119).
 *
 * ⛔ **THIS COLUMN IS NOT A SECOND `renewal_reminded_for` AND MUST NEVER BE READ
 * AS ONE.** `renewal_reminded_for` is the **record**: the renewal a notice went
 * out about, written after the send, read by nothing else in the application.
 * This is the **claim**: the moment one reader took the right to send, written
 * before the send and deliberately short-lived. A screen that reported this as
 * "the notice was sent" would report a notice for every run that died on its
 * way to the queue.
 *
 * ## Why a claim at all
 *
 * `RenewalReminders::due()` gates on `renewal_reminded_for?->isSameDay(...)` —
 * a read, then a send, then a write, with no row lock and no arbitration between
 * them. Two readers inside that window both see "nobody has sent this" and both
 * send, and what they send is a statutory notice that cannot be taken back
 * (6969). Until this column the only thing preventing it was
 * `withoutOverlapping(360)` in `routes/console.php` — a mutex, held in one
 * process, with an expiry, **and taken only by the scheduled invocation**: a
 * human typing `php artisan billing:send-renewal-reminders` takes no lock at
 * all, because the command implements no `Isolatable`.
 *
 * ⛔ **A UNIQUE INDEX WAS REFUSED AND THE REASON IS NOT SCOPE** (6974(d) called
 * it "the right fix" and it is not one). `subscriptions.business_id` is itself
 * `unique()`, so there is exactly one row per business: `(business_id,
 * renewal_reminded_for)` is trivially unique and arbitrates nothing, and a
 * unique index on `renewal_reminded_for` alone would forbid two *different*
 * businesses from renewing on the same day. What arbitrates a read-then-write is
 * a **conditional UPDATE that fails for the second reader**, which is what
 * {@see Subscriptions::claimRenewalReminder()} issues.
 *
 * ## Why it expires, and why that is the whole point
 *
 * A claim that never expired would convert *"a notice sent twice"* into *"a
 * notice never sent"* for any run that died between claiming and dispatching —
 * and the two failures are not comparable. A duplicate notice is an annoyance;
 * a missing pre-renewal notice is a violation of Civil Code §1637 with the
 * customer's remedy attached to it. `Subscriptions::recordRenewalReminder()`'s
 * own docblock already ruled on that asymmetry and this column does not reverse
 * it: the claim covers exactly one queue dispatch and one row write, it lapses
 * long before the next daily sweep, and a crashed run therefore loses one of the
 * sixteen chances the 30→15 day window gives it rather than the notice.
 *
 * ⚠️ **NULLABLE WITH NO DEFAULT, AND NO BACKFILL.** An unclaimed row is the
 * ordinary state and `NULL` is the value that says so. Backfilling `now()` on
 * deploy would suppress every notice due in the following hour; backfilling any
 * past timestamp would say a claim happened that did not.
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
            // `timestamp`, not `date`, and it is the one column in this pair
            // where a time of day is the point: the claim is compared against
            // "less than an hour ago", which a calendar day cannot express.
            $table->timestamp('renewal_reminder_claimed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('renewal_reminder_claimed_at');
        });
    }
};
