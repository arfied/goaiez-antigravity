<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How far through one mailbox's history we have read — T137 §3 rail 3.
 *
 * ⚠️ **THIS TABLE EXISTS BECAUSE A GMAIL PUSH NOTIFICATION DOES NOT SAY WHAT
 * CHANGED.** Verified against `developers.google.com/workspace/gmail/api/guides/
 * push` on 2026-08-12 (page dated 2026-07-22): the decoded payload is
 * `{"emailAddress": "...", "historyId": "..."}` and the `historyId` is the
 * mailbox's **current** record, not the range. To learn what arrived you call
 * `users.history.list` with a `startHistoryId` you were already holding — so
 * something has to hold it, durably, across deploys.
 *
 * ⛔ **A LOST CURSOR IS LOST REPLIES, WHICH IS WHY IT IS NOT IN THE CACHE.**
 * `users.history.list` answers **HTTP 404** for a `startHistoryId` that is too
 * old (*"Supplying an invalid or out of date `startHistoryId` typically returns
 * an HTTP 404 error code"* — same reference, `users.history/list`, dated
 * 2026-04-15), and Google keeps history for *"at least a week, though
 * occasionally only hours"*. A cursor in a cache that a deploy clears means the
 * next notification has nothing to start from, and the replies that arrived in
 * between are unreachable for ever with nothing anywhere saying so.
 *
 * ⚠️ **NOT TENANT-OWNED, AND IT NAMES NO TENANT.** One Workspace mailbox serves
 * every client — that is the whole premise of 2097's shared relay and of the
 * eight-character tracking code. A `business_id` here would be a claim that a
 * mailbox belongs to a tenant, which is exactly the conflation 2072 warns
 * against between the platform's own account and a tenant's connected one.
 * `PlatformMailSend` two migrations earlier is the same shape for the same
 * reason.
 *
 * ⚠️ **NO PERSONAL DATA, DELIBERATELY.** A mailer name, one of our own mailbox
 * addresses, and two opaque counters Google issued. Nothing about who wrote to
 * us, nothing about what they said — the row is a bookmark. `platform_mail_sends`
 * makes the identical distinction in the identical words: one of *our* addresses
 * is not a recipient's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_inbox_cursors', function (Blueprint $table): void {
            $table->id();

            /*
             * Which mailer's inbox this bookmarks — `gmail` today.
             *
             * Present so that a second read path (SES inbound receipt, a tenant
             * mailbox over OAuth) cannot silently share one tenant's bookmark
             * with another's. The unique index below is over the pair.
             */
            $table->string('mailer', 64);

            /*
             * The mailbox the cursor belongs to.
             *
             * `me` on a single-account install, an explicit Workspace address
             * once there is more than one. ⚠️ **ONE OF OUR OWN ADDRESSES AND
             * NEVER A CORRESPONDENT'S** — `platform_mail_sends.sending_account`
             * carries the same column with the same argument, and that argument
             * is what makes an email address acceptable on a platform-scoped
             * table here and refused three tables away.
             */
            $table->string('mailbox', 255);

            /*
             * Google's `historyId` for the last record this application has
             * finished with.
             *
             * ⚠️ **A STRING, AND THE API'S OWN TYPE IS THE REASON.** The Gmail
             * reference types `historyId` as a string on every resource that
             * carries it; the values are large and monotonic, and storing them
             * as a bigint would work until it did not. Nothing here does
             * arithmetic on it — it is handed back to Google exactly as it
             * arrived, which is the only operation it has.
             */
            $table->string('history_id', 64)->nullable();

            /*
             * When `users.watch` was last renewed, and when it stops.
             *
             * ⚠️ **THE RENEWAL IS PART OF THE FEATURE AND NOT A FOLLOW-UP.**
             * The push guide is explicit: *"You must call the watch at least
             * once every 7 days or you'll stop receiving updates for the
             * user"*, and recommends daily. A watch that silently expires is a
             * reply path that stops with no error anywhere — the failure this
             * whole codebase records most often. The column is here so the
             * expiry is *observable*; ⛔ **nothing in `app/` renews it yet, and
             * that is stated rather than implied** (see `GmailInbox`).
             */
            $table->timestamp('watch_expires_at')->nullable();

            $table->timestamps();

            // One bookmark per mailbox per mailer. Two rows would be two
            // answers to "where were we", and whichever query ran first would
            // win silently — which is how a reply gets read twice or not at all.
            $table->unique(['mailer', 'mailbox']);
        });

        DB::statement('ALTER TABLE mail_inbox_cursors ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE mail_inbox_cursors FORCE ROW LEVEL SECURITY');

        // `USING (true)`: no tenant column to predicate on, and the reader runs
        // with no tenant established — an inbound reply names none until the
        // tracking code resolves one. Named rather than omitted so the posture
        // is a decision in `pg_policies` and not an absence, which is
        // `platform_mail_sends`' own reasoning.
        DB::statement(
            'CREATE POLICY mail_inbox_cursors_all ON mail_inbox_cursors USING (true) WITH CHECK (true)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_inbox_cursors');
    }
};
