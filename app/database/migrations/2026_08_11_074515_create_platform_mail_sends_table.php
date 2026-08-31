<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per message this platform hands to a transport — the ceiling meter.
 *
 * Decision 2095, and the reason it is a table rather than a counter: **Google
 * Workspace's 2,000-messages-per-day-per-user limit is a rolling 24-hour
 * window, not a calendar day** (`knowledge.workspace.google.com`, Gmail sending
 * limits in Google Workspace, read 2026-08-11), and exceeding it locks the
 * sending account out **for up to 24 hours**. A per-day counter keyed on a date
 * would permit 2,000 sends at 23:00 and 2,000 more at 00:01, which is the exact
 * shape that trips the limit — and the symptom is not an error, it is every
 * tenant's mail stopping at once with the queue draining normally.
 *
 * So the window is computed from timestamps: `COUNT(*) WHERE sent_at > now() -
 * 24 hours`. Exact, no bucket boundary to be wrong about, and prunable by date.
 *
 * ⚠️ **NOT TENANT-OWNED AND CARRYING NO TENANT AT ALL.** The limit is a fact
 * about *our* Workspace user, shared by every tenant on the platform, and the
 * platform mail that competes for it (a sign-in link, a support notice) belongs
 * to no tenant in the first place. A `business_id` here would invite a
 * per-tenant reading of a ceiling that is not per tenant — 2059's warning about
 * two ceilings on one spend, one table earlier.
 *
 * ⚠️ **NO ADDRESS, NO SUBJECT, NO NOTIFICATION CLASS.** A row is a mailer name,
 * a sending account and a timestamp. This is a meter and it must not become a
 * send log: a platform-scoped table naming every address we have ever mailed is
 * the marketing list `opt_outs` stores a hash to avoid being, and the
 * notification class would leak which tenants are being recovered from a
 * one-star review to anybody who could read the row.
 *
 * ⚠️ **IT COUNTS ATTEMPTS HANDED TO A TRANSPORT, NOT DELIVERIES.** Google's
 * ceiling counts what it accepted, so a message the transport refused is not
 * charged against it — but the meter is written *after* the transport returns,
 * so a refusal never lands here at all. What the meter cannot see is a send
 * made from that Workspace account by any other means: a person using the web
 * client, another application on the same mailbox. That is a real undercount
 * and it is the reason the alert threshold has headroom rather than sitting at
 * the ceiling.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_mail_sends', function (Blueprint $table): void {
            $table->id();

            /*
             * Which configured mailer carried it — `gmail`, `smtp`, `log`.
             *
             * The mailer rather than the transport, because the ceiling belongs
             * to a configured account and two mailers can share a transport.
             */
            $table->string('mailer', 64);

            /*
             * The account the ceiling belongs to.
             *
             * For Gmail this is the Workspace user the internal app sends as;
             * for SES it is the configured sending identity. Held as an opaque
             * string because the limit is per account and the meter has to be
             * able to say *which* account is near it — with two, one exhausted
             * and one idle looks identical to one at half.
             *
             * ⚠️ **IT IS ONE OF OUR OWN ADDRESSES AND NEVER A RECIPIENT'S.**
             * That distinction is the whole reason a column holding an email
             * address is acceptable on a platform-scoped table here and is
             * refused three tables away — `inbound_messages.to_number` carries
             * the identical argument for the identical reason (1623).
             */
            $table->string('sending_account', 255);

            $table->timestamp('sent_at')->useCurrent()->index();
        });

        DB::statement('ALTER TABLE platform_mail_sends ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE platform_mail_sends FORCE ROW LEVEL SECURITY');

        // `USING (true)`: there is no tenant column to predicate on and the
        // reader runs with no tenant established. Named rather than omitted, so
        // that the posture is a decision in `pg_policies` and not an absence.
        DB::statement(
            'CREATE POLICY platform_mail_sends_all ON platform_mail_sends USING (true) WITH CHECK (true)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_mail_sends');
    }
};
