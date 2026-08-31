<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReviewSource;
use App\Jobs\SendReviewInviteJob;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\ReviewRouter;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Send the invites a pause deferred, once the tenant is running again.
 *
 * THE OTHER HALF OF DECISION 822. `ReviewRouter` suppresses the invite half of
 * routing while a tenant is paused, correctly — and, until this command, it did
 * so by emptying the snapshot on a row nothing would ever look at again. A
 * fortnight-long pause silently lost every invite from that window. `TenantPause`'s
 * own docblock says analysis is recovered by `reviews:reanalyse` and is the model
 * for this; the invite half simply never had one.
 *
 * ⚠️ **RE-DISPATCHING `SendReviewInviteJob` IS NOT THE FIX, AND IT IS THE
 * OBVIOUS ONE.** It was the shape this slice was briefed as. It cannot work:
 * the sender's third gate calls `ReviewInvites::offerFor()`, which reads
 * `routed_destinations` as the authority on what was offered, and a pause wrote
 * `[]` there. Measured before this command was written — a post-resume
 * re-dispatch produced no invite at all, while an unpaused control produced one.
 * So the snapshot has to be **re-evaluated** first, which is
 * `ReviewRouter::reoffer()`, and only then is there anything for the job to send.
 * A version of this command without that step would have passed every test that
 * faked the queue and done nothing whatsoever in production — decisions 256, 285,
 * 361 and 411's vacuity.
 *
 * THE CANDIDATE SOURCE IS THE REVIEW, NOT THE SKIP ROW. `SendReviewInviteJob`
 * does open a `skipped` run reading `tenant paused`, and it is useless here:
 * `AutopilotJob::recordSkip()` writes no `input` at all and that job does not
 * override `input()`, so the row carries no review id. `reviews.invite_deferred_at`
 * is the state that was missing, and `ReanalyseReviews` drives off review state
 * for exactly this reason.
 *
 * THE ENUMERATION FOLLOWS `reviews:reanalyse`, AND FOR ITS REASONS — which are
 * `oauth:refresh-tokens`' reasons. This runs outside any tenant, `businesses` is
 * FORCE ROW LEVEL SECURITY on a policy keyed to the session tenant, so
 * `Business::all()` returns nothing and dropping a global scope does not help
 * because the policy is in the database. Reaching each business through its owner
 * grants this sweep no privilege a logged-in owner does not already have. Read
 * that command's docblock before changing this one.
 *
 * NO STALENESS FLOOR, UNLIKE `reviews:reanalyse`. That command waits fifteen
 * minutes so it never races an analysis still in flight. There is nothing in
 * flight here: the deferred invite was refused outright, and the tenant being
 * unpaused is the event this is waiting for rather than a duration. A ceiling
 * exists instead — see `ReviewRouter::MAX_DEFERRAL_DAYS`.
 */
#[Signature('reviews:reinvite')]
#[Description('Re-offer and send review invites that a tenant pause deferred')]
final class ReinviteDeferredReviews extends Command
{
    /**
     * Reviews considered per business per sweep.
     *
     * `ReanalyseReviews`' constant and its reasoning: a cap rather than a chunked
     * walk, so a backlog drains across sweeps. The starvation trap that decision
     * 365 records does not arise here, because a swept review always has its
     * marker cleared — nothing can sit at the front of this queue forever the way
     * a permanently-failing analysis could.
     */
    private const int PER_BUSINESS_LIMIT = 100;

    public function handle(): int
    {
        // Asked before anything is enumerated, on ReanalyseReviews' reasoning:
        // each job would refuse individually and correctly, but only after opening
        // a `skipped` run row, so a killed automation would write one row per
        // candidate every time the schedule fired.
        //
        // ⚠️ THE MARKERS ARE LEFT SET, WHICH IS THE POINT. A thrown kill switch
        // means "not now", and this whole column exists so that "not now" stops
        // meaning "never". They are picked up by the first sweep after it is
        // cleared, unless they have gone stale in the meantime — which is the
        // ceiling doing its job rather than this gate failing.
        if (SendReviewInviteJob::killSwitchThrownFor('review.invite.email')) {
            $this->info('Review invites are switched off; nothing re-offered.');

            return self::SUCCESS;
        }

        $offered = 0;
        $cancelled = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$offered, &$cancelled): void {
                foreach ($users as $user) {
                    [$o, $c] = $this->sweepOwner((int) $user->getKey());

                    $offered += $o;
                    $cancelled += $c;
                }
            });

        // Never leave a security context established after a console command. The
        // PostgreSQL session variable outlives this process's connection under any
        // pooler, and a worker inheriting it would start as whichever tenant the
        // loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($offered === 0 && $cancelled === 0
            ? 'No invites are waiting on a resume.'
            : "Re-offered {$offered} ".str('invite')->plural($offered)
                .", cancelled {$cancelled} stale ".str('deferral')->plural($cancelled).'.');

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     *
     * @return array{int, int} offered, cancelled
     */
    private function sweepOwner(int $userId): array
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by the
        // user just set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $offered = 0;
        $cancelled = 0;

        foreach ($businessIds as $businessId) {
            [$o, $c] = $this->sweepBusiness((int) $businessId);

            $offered += $o;
            $cancelled += $c;
        }

        return [$offered, $cancelled];
    }

    /**
     * @return array{int, int} offered, cancelled
     */
    private function sweepBusiness(int $businessId): array
    {
        // Inside the tenant from here down. Every query below is an ordinary
        // scoped Eloquent query with RLS beneath it — no withoutGlobalScope, no
        // raw cross-tenant read.
        Tenancy::set($businessId);

        // ⚠️ A STILL-PAUSED TENANT IS SKIPPED WHOLE, AND NOTHING IS CANCELLED FOR
        // THEM EITHER. `ReviewRouter::reoffer()` re-checks this per review and
        // would refuse anyway; the point of asking here is the *cancellation*
        // path, which does not go through reoffer() and would otherwise expire
        // every deferral of a tenant who paused for a week — destroying the
        // invites during the very condition the marker exists to survive.
        //
        // The deferral clock therefore runs against wall time and is only ever
        // *read* while the tenant is running. A long pause does consume it: an
        // owner returning after ten days finds those invites stale, which is
        // correct, because the customer wrote their feedback ten days ago.
        if (app(TenantPause::class)->isCurrentTenantPaused()) {
            return [0, 0];
        }

        // ⚠️ AND A SUSPENDED ONE, FOR THE CANCELLATION PATH'S SAKE ABOVE ALL.
        // `reoffer()` re-checks the suspension itself and would refuse; what
        // this line protects is `cancelDeferredInvite()`, which does not go
        // through it. Without this, a three-day compliance hold would expire
        // every deferral inside it and record each one as a decision not to ask
        // the customer — destroying the invites during exactly the condition the
        // marker exists to survive (890), and doing it under our own stop rather
        // than the owner's.
        if (app(TenantSuspension::class)->isCurrentTenantSuspended()) {
            return [0, 0];
        }

        $router = app(ReviewRouter::class);

        $candidates = Review::query()
            ->whereNotNull('invite_deferred_at')
            // Belt and braces against the CHECK: a Google review can never carry
            // the marker, and a sweeper is the easiest place in a codebase to
            // widen a predicate by accident (ReanalyseReviews' own words).
            ->where('source', ReviewSource::FirstParty)
            // Ascending on id: the oldest deferrals drain first, and an ascending
            // order raises none of the NULLS-FIRST problems the ConventionsTest
            // lint exists for.
            ->orderBy('id')
            ->limit(self::PER_BUSINESS_LIMIT)
            ->get();

        $offered = 0;
        $cancelled = 0;

        foreach ($candidates as $review) {
            $deferredAt = $review->invite_deferred_at;

            if ($deferredAt !== null && $deferredAt->diffInDays(now()) >= ReviewRouter::MAX_DEFERRAL_DAYS) {
                $router->cancelDeferredInvite($review);

                $cancelled++;

                continue;
            }

            $decision = $router->reoffer($review);

            // ⚠️ ONLY WHEN THERE IS SOMETHING TO SEND. reoffer() clears the marker
            // whatever it decides, so a review that is no longer invitable — the
            // owner switched solicitation off during the pause, or disabled every
            // destination — is finished with rather than dispatched at. Dispatching
            // regardless would open a run row per review to prove a negative the
            // snapshot already records.
            if ($decision === null || ! $decision->invited()) {
                continue;
            }

            // `location_id` is NOT NULL with a foreign key — ReviewRouter's own
            // docblock says so, and reoffer() has already refused a review whose
            // location does not resolve. No null branch to carry.
            SendReviewInviteJob::dispatch(
                $businessId,
                (int) $review->location_id,
                (int) $review->id,
            );

            $offered++;
        }

        return [$offered, $cancelled];
    }
}
