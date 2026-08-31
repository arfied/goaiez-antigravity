<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The id this application put on the carrier's wire — the thread back to a send
 * whose outcome nobody could establish (7066, 7192(d), built at 7360).
 *
 * ⛔ **`send_key` WAS NEVER SENT TO INFOBIP AND WAS DESCRIBED AS THOUGH IT
 * HAD BEEN.** 7192(d) closes with *"the `send_key` is kept on the `Unknown`
 * row so that lookup has something to start from"*, and `RunCampaignJob`'s
 * `markUnknown()` repeats it — but `InfobipClient::send()` put only
 * `webhooks.callbackData` on the wire, and `PlatformTexter` fills that with the
 * **business id**. The vendor held no value `GET /sms/3/logs?messageId=…` could
 * match, so the thing kept for the lookup was a purely local identifier and
 * every unknown row written before this migration is unanswerable for ever.
 *
 * ## Why a new column rather than reusing `send_key`
 *
 * ⛔ **TWO REASONS AND THE SECOND IS A PRIVACY DEFECT.** `SendKey` is
 * deterministic across attempts by design, so a `Failed` attempt followed by a
 * real send submits the same id twice and the vendor's answer for the first is
 * indistinguishable from its answer for the second. And `SendKey` is a
 * **sha256 of the recipient** — this table's own model docblock says it hashes
 * them *"precisely so the value can reach a row, a log line and a Horizon tag
 * without a mobile number reaching any of them"* — so putting it on the wire
 * would hand a third party a stable per-recipient token, identical on every
 * message that person ever receives from us.
 *
 * **What is stored here is a product prefix and a v4 UUID**, minted by
 * `InfobipClient::mintHandle()`, correlated with nothing.
 *
 * ## The partial index, and why the window is `updated_at`
 *
 * ⚠️ **THE SWEEP ASKS ONE QUESTION — *which of this tenant's rows are still
 * unresolved and still inside the vendor's forty-eight hours* — AND NO EXISTING
 * INDEX ANSWERS IT.** `['business_id', 'campaign_id', 'status']` leads on a
 * campaign this query does not have, so without this the hourly sweep scans
 * every recipient a tenant has ever enrolled. A partial index on the one status
 * that matters is a few rows wide on a table that may hold millions.
 *
 * ⚠️ **`updated_at` IS THE ATTEMPT TIME FOR EXACTLY THIS SET, AND THE 2687
 * MIGRATION NEXT DOOR REFUSED IT FOR A DIFFERENT QUESTION.** That one needed
 * the *first* deferral and every barren pass re-saved the row, so `updated_at`
 * could never be old enough. `unknown` is terminal: `batch()` does not select
 * it, `closeIfFinished()` does not count it, and the reconciler is the only
 * writer that can touch it — and when it does, the row stops being `unknown`.
 * So the last write **is** the attempt, and being wrong about it costs one
 * wasted vendor call that answers nothing, never a wrong conclusion.
 *
 * ⛔ **THE CLAUSE "WHEN IT DOES, THE ROW STOPS BEING `unknown`" STOPPED BEING
 * TRUE ON 2026-08-22, AND THE CONCLUSION SURVIVES FOR A REASON WORTH READING**
 * (7502). `UnknownSendReconciler::recordFailure()` now writes
 * `carrier_answered_at` on a row that **stays** `unknown` — that is what stops a
 * carrier's terminal *"it did not arrive"* being asked about forty-seven more
 * times. **`updated_at` is still the attempt time**, because that write disables
 * timestamps on purpose: an ordinary
 * `save()` would move the attempt time to the moment we were told, and
 * `SendCollisionArbiter` — which the paragraph above does not mention and which
 * is the reader that matters — counts an `unknown` row as a live marketing
 * touch for as long as `updated_at` sits inside the touch window. ⛔ **So the
 * cost of being wrong about this column is no longer one wasted vendor call**:
 * it is a real person's second campaign blocked for another day, once an hour,
 * on the strength of a full stop rather than a send.
 *
 * ⚠️ **NO CHECK CONSTRAINT TIES THE HANDLE TO A STATUS**, deliberately. The
 * three existing CHECKs bind `refused` and `sent` because a wrong value there
 * is a claim about whether somebody was messaged; a handle claims nothing at
 * all — it is a question we can ask, not an answer. `markUnknown()` is the only
 * writer today and a lint would be the wrong instrument for a column whose
 * whole meaning is *"there is something here worth asking about"*.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table): void {
            // ⚠️ **AFTER `send_key`, BECAUSE THE TWO ARE CONSTANTLY MISTAKEN
            // FOR EACH OTHER** and a reader running `\d campaign_recipients`
            // should meet them together. One is ours and local; one is ours and
            // on somebody else's wire.
            $table->string('send_handle')->nullable()->after('send_key');
        });

        DB::statement(<<<'SQL'
            CREATE INDEX campaign_recipients_unresolved
                ON campaign_recipients (business_id, updated_at)
                WHERE status = 'unknown'
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS campaign_recipients_unresolved');

        Schema::table('campaign_recipients', function (Blueprint $table): void {
            $table->dropColumn('send_handle');
        });
    }
};
