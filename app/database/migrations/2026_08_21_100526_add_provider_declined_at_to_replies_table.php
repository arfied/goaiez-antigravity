<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The evidence that a reply reached Google and Google refused it (6685).
 *
 * ⛔ **THIS COLUMN EXISTS SO THAT ONE SENTENCE ON ONE SCREEN CANNOT BE SAID
 * ABOUT A REPLY GOOGLE HAS NEVER SEEN.** `ReplyPublicationStatus` derived
 * `ReplyPublicationState::NotAccepted` — *"We tried to publish this and Google
 * did not take it."* — from `replies.error_message` being non-null, and
 * `PostReplyJob::handoff()` writes that column for `integration_disabled` and
 * `not_connected`, **neither of which leaves this application**. The ladder's
 * earlier arms hid it while `gbp.zernio_enabled` was false; the moment that row
 * is true and a location is connected, those rows assert a decision a third
 * party never made.
 *
 * ⚠️ **A FACT ABOUT AN EVENT, NOT A STORED STATE** (6527). `ReplyPublicationState`
 * must never become a column and this is not it: three of its four cases stay
 * derived from the live registry and the live connection, and this is the
 * fourth's evidence — the same shape as `posted_at`, written by the same code
 * path, from the same vendor round trip.
 *
 * ⚠️ **NULL ON EVERY ROW THAT EXISTS TODAY, AND THAT IS THE CORRECTION RATHER
 * THAN A GAP.** A legacy row falls to `NotPublishedYet` — *"This is not on
 * Google."* — which is true of every one of them, including the handed-off rows
 * that are asserting the opposite right now. **No backfill**: see 6729.
 *
 * No RLS statement here. `replies` was created with `ENABLE` + `FORCE` and a
 * `tenant_isolation` policy on `business_id`, and a policy is a property of the
 * table rather than of its columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('replies', function (Blueprint $table): void {
            $table->timestamp('provider_declined_at')->nullable()->after('posted_by');
        });
    }

    public function down(): void
    {
        Schema::table('replies', function (Blueprint $table): void {
            $table->dropColumn('provider_declined_at');
        });
    }
};
