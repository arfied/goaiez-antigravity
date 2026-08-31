<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A seventh recipient status: the send whose outcome nobody can establish.
 *
 * ⛔ **THE SIX EXISTING STATUSES CANNOT SAY "WE DO NOT KNOW", AND THAT IS WHY A
 * CAMPAIGN COULD TEXT A STRANGER TWICE** (7069, closed at 7180). A
 * `TextNotDeliverable` thrown out of `RunCampaignJob::sendTo()` left the row
 * `Pending`, and `Pending` means *not yet attempted* — so the next pass
 * re-selected the contact and sent a second marketing message to a member of
 * the public. `PlatformMessageSender` had already rolled the `send_key` claim
 * back on the way out, deliberately, so nothing downstream could refuse it
 * either.
 *
 * **None of the six could be reused and each refusal is a database constraint
 * rather than a preference:**
 *
 *   `sent`       `campaign_recipients_sent_rows_are_complete` requires a
 *                `provider_message_id`, and the whole shape of this outcome is
 *                that the carrier's handle is exactly what is missing.
 *   `refused`    `campaign_recipients_refusal_sent_nothing` requires that
 *                nothing was sent. This row may well have been.
 *   `failed`     Means *"the carrier could not be reached, the queue retries"*
 *                and is outstanding, which is the retry this closes.
 *   `pending`    *Not yet attempted.* It was attempted.
 *   `skipped`    *We got here and deliberately did not.* We did.
 *   `duplicate`  A claim that the message already exists once. Nothing here
 *                establishes that it exists at all.
 *
 * ⚠️ **AN `unknown` ROW NAMES NOTHING AND CARRIES NO TIME**, so the three
 * existing CHECKs all hold against it unchanged: no `refusal_reason` (the
 * reason CHECK ties one to `refused`), no `provider_message_id` and no
 * `sent_at`. ⛔ **`sent_at` IS DELIBERATELY LEFT NULL** even though populating
 * it would make `SendCollisionArbiter` count the touch — that column is
 * documented on this table as *"was messaged"*, and widening it to *"may have
 * been messaged"* is a second interpretation of one column. Raised at 7192,
 * not taken here.
 *
 * ⚠️ **NO DOWN-CONVERSION OF EXISTING ROWS IS NEEDED IN EITHER DIRECTION.**
 * Nothing has ever written the value, so `down()` only has to put the old
 * CHECK back; it drops nothing and rewrites nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE campaign_recipients DROP CONSTRAINT campaign_recipients_status_is_known');

        DB::statement(<<<'SQL'
            ALTER TABLE campaign_recipients
                ADD CONSTRAINT campaign_recipients_status_is_known
                CHECK (status IN ('pending', 'sent', 'duplicate', 'refused', 'skipped', 'failed', 'unknown'))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE campaign_recipients DROP CONSTRAINT campaign_recipients_status_is_known');

        DB::statement(<<<'SQL'
            ALTER TABLE campaign_recipients
                ADD CONSTRAINT campaign_recipients_status_is_known
                CHECK (status IN ('pending', 'sent', 'duplicate', 'refused', 'skipped', 'failed'))
        SQL);
    }
};
