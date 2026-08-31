<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two columns California's Automatic Renewal Law needs (2980–2999).
 *
 * ⚠️ **NEITHER OF THESE IS A SUBSCRIPTION STATUS AND NEITHER MAY EVER BECOME
 * ONE.** 2056 keeps webhooks the source of truth on both gateways, and the
 * failure this migration is most likely to invite is a screen reading
 * `cancellation_requested_at` where it should read `status` — a tenant shown as
 * cancelled because they pressed a button, while the vendor never received the
 * call. `status` still moves only in `Subscriptions::applyStripeSubscription()`
 * and `::applyAuthorizeNetSubscription()`, from a verified event.
 *
 * `cancellation_requested_at` records **our own act**: this tenant asked, at
 * this moment, through a surface we render. That is a fact no webhook carries
 * and no vendor stores, and it is what the cancellation screen, the renewal
 * reminder and any later dispute all read.
 *
 * ⚠️ **AND IT IS LOAD-BEARING ON THE ANNUAL TERM, NOT MERELY INFORMATIONAL.**
 * `applyAuthorizeNetSubscription()` gains one arm scoped to *this column being
 * set*: a `canceled` notification for a row with a future `annual_term_ends_on`
 * that **we** asked for leaves the paid term entitled, where the same
 * notification with no request behind it still cancels at once. 2748's rule
 * that "canceled and terminated are acts and stay acts" is unchanged for every
 * cancellation this application did not initiate.
 *
 * `renewal_reminded_for` is the renewal **date** most recently reminded about,
 * not a boolean and not a timestamp of the send. 15–45 days' notice is required
 * once per renewal, so the gate has to be per *term*: a boolean would silence
 * next year's notice for ever, and a send timestamp would need arithmetic
 * against a moving anniversary to answer "have we done this one yet". A date
 * answers it by equality.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->timestamp('cancellation_requested_at')->nullable();

            // `date`, matching `annual_term_ends_on` and `authorize_net_starts_on`
            // and for their reason: a renewal falls on a day, and inventing a
            // time on it makes "have we reminded about this one" answer
            // differently either side of the dateline.
            $table->date('renewal_reminded_for')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['cancellation_requested_at', 'renewal_reminded_for']);
        });
    }
};
