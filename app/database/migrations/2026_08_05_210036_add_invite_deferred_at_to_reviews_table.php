<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The invite a pause deferred, so that resuming can find it again.
 *
 * ⚠️ **DECISION 822 DEFERS NOTHING TODAY — IT DESTROYS.** That decision suppresses
 * the invite half of routing while a tenant is paused and leaves triage alone,
 * which is right. What it did not account for is *where* the suppression lands:
 * `ReviewRouter` empties the `offered` snapshot, `routed_destinations` persists as
 * `[]`, and `routing_decision` is set — after which `route()`'s own once-only
 * guard refuses to look at that review ever again, and `ReviewInvites` treats the
 * empty snapshot as the authority on what was offered. So a review submitted
 * during a pause is not waiting to be invited. It has been recorded as having
 * been offered nothing, permanently, and no amount of re-dispatching
 * `SendReviewInviteJob` after a resume changes that: the sender's third gate
 * reads the snapshot and refuses.
 *
 * Measured rather than reasoned: an unpaused control routed to
 * `[{google, threshold 5}]` and sent an invite; the same submission during a
 * pause routed to `no_action` with `[]`, and a post-resume re-dispatch produced
 * no invite at all. A sweep built on the assumption that the work was merely
 * deferred would have been a no-op in production while passing any test that
 * stubbed the sender — decisions 256, 285, 361 and 411's vacuity, one more time.
 *
 * ## Why a column rather than reading the skip rows
 *
 * The obvious source is `automation_runs`: `SendReviewInviteJob` does open a
 * `skipped` row reading `tenant paused`. It cannot be used. `recordSkip()` writes
 * no `input` at all, and `SendReviewInviteJob` does not override `input()`, so a
 * skip row carries **no review id** — there is nothing in it to re-dispatch.
 * `ReanalyseReviews` drives off review *state* for the same reason, and this
 * column is the state that was missing.
 *
 * ## Why the marker is written for a pause and never for the owner's toggle
 *
 * `send_review_requests = false` also empties the snapshot, and it must **not**
 * set this. That toggle is a standing choice the owner made, not a temporary stop
 * — an owner who has switched solicitation off has not asked us to queue up every
 * review in the meantime and send them all the moment they change their mind.
 * A pause is the opposite: `28` §9.5's own framing is that it is temporary, and
 * `43` I12's *"a throttled send is deferred and replanned, never dropped"* is the
 * principle this column restores. Conflating the two would turn a preference into
 * a backlog.
 *
 * ## The CHECK
 *
 * `reviews_google_is_never_routed` is widened rather than joined by a second
 * constraint, because this column is part of what routing writes and rule 1's
 * subject is unchanged: a Google review is never routed, so it can never have had
 * an invite deferred. Decisions 216, 359 and 383's pattern — the claim and the
 * constraint land together — and 386's warning about a constraint narrower than
 * its own stated reason, which is why the widening happens here rather than being
 * left for a later reader to notice.
 *
 * Nullable, and null for every review written before this migration: those were
 * routed under the old behaviour and there is no honest way to tell which of them
 * lost an invite to a pause. Backfilling a guess would put a fabricated deferral
 * into the one column the sweep acts on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->timestampTz('invite_deferred_at')->nullable()->after('routed_at');
        });

        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_google_is_never_routed');

        DB::statement(<<<'SQL'
            ALTER TABLE reviews
                ADD CONSTRAINT reviews_google_is_never_routed
                CHECK (
                    source <> 'google'
                    OR (
                        routing_decision IS NULL
                        AND routed_destinations IS NULL
                        AND routed_at IS NULL
                        AND invite_deferred_at IS NULL
                        AND status <> 'in_triage'
                    )
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reviews DROP CONSTRAINT reviews_google_is_never_routed');

        DB::statement(<<<'SQL'
            ALTER TABLE reviews
                ADD CONSTRAINT reviews_google_is_never_routed
                CHECK (
                    source <> 'google'
                    OR (
                        routing_decision IS NULL
                        AND routed_destinations IS NULL
                        AND routed_at IS NULL
                        AND status <> 'in_triage'
                    )
                )
        SQL);

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn('invite_deferred_at');
        });
    }
};
