<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The record that this platform — rather than the owner — asked for a stranded
 * reply to be published again (11040, 11041).
 *
 * ⛔ **IT RECORDS A DISPATCH AND NEVER A PUBLICATION, AND THE NAME IS THE ONLY
 * PLACE THAT DISTINCTION CAN BE MADE UNAMBIGUOUS.** This table already carries
 * three columns about what a third party did — `posted_at` (accepted),
 * `provider_declined_at` (refused) and `publish_unconfirmed_at` (said nothing at
 * all) — and every one of them is written from a vendor round trip.
 * `publish_retry_dispatched_at` is written **before** anything leaves this
 * application, by `ReviewReplies::markPublishRetryDispatched()`, and asserts
 * exactly one thing: `reviews:retry-stranded-replies` put `PostReplyJob` on the
 * queue for this row. `publish_retried_at` would read as an attempt that
 * happened, which is a claim only `automation_runs` can make.
 *
 * ⛔ **IT IS THE BOUND 7126 SAID WAS MISSING, AND THAT IS THE WHOLE OF WHY IT IS
 * A COLUMN RATHER THAN A DERIVED READ.** `reconcileUnconfirmedPublication()`
 * refused to re-dispatch because *"bounding that needs a per-reply record of how
 * many times this has already fired, which is a column this slice does not
 * add"*: an unanswered attempt clears, re-dispatches, goes unanswered again, and
 * loops — republishing on a public listing with a notification to a member of
 * the public each time. This is that record, at a bound of **one**. A reply is
 * re-dispatched automatically once, ever, per owner decision.
 *
 * ⚠️ **NOT CLEARED BY `clearPostingFailure()`, WHICH IS THE OBVIOUS PLACE AND
 * THE WRONG ONE** (11041). That chokepoint exists so the three
 * what-did-Google-do columns are never cleared apart, and
 * `reconcileUnconfirmedPublication()` is one of its four callers — so putting
 * this column in it would hand the claim back on exactly the path whose loop the
 * claim exists to bound. It is released in `ReviewReplies::approve()` alone,
 * because a fresh approval is a fresh decision by a person.
 *
 * ⚠️ **NULL ON EVERY ROW THAT EXISTS TODAY AND NO BACKFILL, AND HERE THAT IS
 * THE BEHAVIOUR RATHER THAN A CONCESSION** (6729's rule). Null means "no
 * automatic retry has been dispatched for this reply", which is true of every
 * row on every deployment, so every reply stranded before this migration gets
 * its one automatic attempt on the first sweep after the blocker clears.
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
            $table->timestamp('publish_retry_dispatched_at')->nullable()->after('publish_unconfirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('replies', function (Blueprint $table): void {
            $table->dropColumn('publish_retry_dispatched_at');
        });
    }
};
