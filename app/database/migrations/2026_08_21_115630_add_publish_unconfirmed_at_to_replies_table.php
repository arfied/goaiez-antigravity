<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The record that we asked the provider to publish and were never answered (6525).
 *
 * ⛔ **THIS COLUMN EXISTS BECAUSE A RETRYABLE FAILURE THROWN AFTER ZERNIO
 * ACCEPTED IS INDISTINGUISHABLE FROM ONE THROWN BEFORE IT.**
 * `PostReplyJob::execute()` writes nothing to `replies` before the vendor call
 * and rethrows a retryable failure so the queue comes back — so a timeout
 * reading the response released the claim on a reply that was in fact live, and
 * the `$tries` ladder asked Google to publish the same text twice more. Nothing
 * on the row said an attempt had ever left this application.
 *
 * ⚠️ **A FACT ABOUT AN EVENT, AND THE THIRD OF ITS KIND ON THIS TABLE.**
 * `posted_at` records that the provider accepted; `provider_declined_at` records
 * that it refused; this records that it said **nothing at all**. All three are
 * written on the same code path from the same round trip, and all three are
 * cleared together by `ReviewReplies::clearPostingFailure()` — see 6724, whose
 * argument gains a third column here rather than a second.
 *
 * ⚠️ **IT IS OUR EPISTEMIC POSITION, NOT A CLAIM ABOUT GOOGLE, AND THE NAME
 * SAYS SO.** `provider_unanswered_at` would assert the request reached the
 * provider, which is precisely what is not known: a cURL timeout covers both
 * "never connected" and "sent and waited". *Unconfirmed* is the only word that
 * is true of every row this column carries.
 *
 * ⚠️ **NULL ON EVERY ROW THAT EXISTS TODAY AND NO BACKFILL** — 6729's rule,
 * unchanged. Reconstructing "we asked and were not answered" from an English
 * string this application wrote to itself would be an inference offered as
 * evidence.
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
            $table->timestamp('publish_unconfirmed_at')->nullable()->after('provider_declined_at');
        });
    }

    public function down(): void
    {
        Schema::table('replies', function (Blueprint $table): void {
            $table->dropColumn('publish_unconfirmed_at');
        });
    }
};
