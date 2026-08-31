<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AutomationRunStatus;
use App\Enums\ReviewSource;
use App\Jobs\AnalyzeReviewJob;
use App\Models\AutomationRun;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use App\Services\Ai\AiSpend;
use App\Services\Reviews\PhiAnalysisConsent;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Re-dispatch analysis for first-party reviews no model ever looked at.
 *
 * THE HALF DECISION 351 DID NOT BUILD. That decision found `AnalyzeReviewJob`
 * holding its idempotency claim forever where no model looked, and released it —
 * but nothing re-claimed. The paths that matter here return normally, so the
 * queue never retries them; the only dispatch site was a *new* review insert;
 * and the tests that appeared to prove recovery re-invoked the job by hand,
 * which proves re-claimability rather than recovery. A vendor outage still
 * buried every review written during it, permanently and invisibly. This is what
 * makes the release mean something.
 *
 * THE QUEUE IS NOW A SECOND RE-CLAIMER, ON ONE PATH ONLY, AND THE CEILING HAS TO
 * KNOW. Releasing the claim from a `finally` made `tries`/`backoff()` live for a
 * job that *throws*: the key is null by the time the queue redelivers, so the
 * redelivery re-executes and opens its own `automation_runs` row. Three tries
 * per dispatch, three rows. An attempt ceiling counted off run rows is therefore
 * counting something it did not use to count, which is why MAX_ATTEMPTS states
 * two windows rather than one.
 *
 * THE PREDICATE IS THE FEATURE. `source = first_party` **and**
 * `moderation_flags IS NULL` is exactly the state `Review::displayable()` treats
 * as not displayable — a review nobody can see. A Google row is excluded here as
 * well as in the job and in a CHECK constraint, because `29` §2 rule 1 forbids
 * moderating one at all and a sweeper is the easiest place in a codebase to
 * widen a predicate by accident.
 *
 * THE ENUMERATION FOLLOWS `oauth:refresh-tokens`, AND FOR ITS REASONS. This runs
 * outside any tenant, and `businesses` is FORCE ROW LEVEL SECURITY on a policy
 * keyed to the session tenant, so `Business::all()` returns nothing and removing
 * a global scope does not help — the policy is in the database. Reaching each
 * business through its owner is the only one of the three ways out that grants
 * the sweep no privilege a logged-in owner does not already have. Read that
 * command's docblock before changing this one; the reasoning is set out there in
 * full and is not repeated here.
 *
 * THE FIFTEEN-MINUTE FLOOR is so this never races the job it is recovering. A
 * review submitted seconds ago has an analysis in flight; re-dispatching it
 * would be a harmless no-op against the held claim, but it would open a second
 * `automation_runs` row per review per sweep and make the ledger unreadable.
 */
#[Signature('reviews:reanalyse')]
#[Description('Re-dispatch analysis for first-party reviews no model has moderated')]
final class ReanalyseReviews extends Command
{
    /**
     * How long a review is left alone before it counts as stuck.
     *
     * Longer than the job's own retry ladder, so a review the queue is still
     * retrying is not swept in parallel with itself. The ladder that actually
     * runs is 60s then 300s — `AutopilotJob::backoff()` lists a third delay of
     * 900s that is never waited, because `tries` is 3 and there is no fourth
     * attempt to delay. So the real tail is about six minutes, and fifteen
     * clears it twice over.
     */
    private const int STALE_AFTER_MINUTES = 15;

    /**
     * How many recorded attempts one review may accumulate before it is left
     * alone.
     *
     * WHAT IS COUNTED, AND WHY TWO OUTCOMES ARE NOT. Rows in `automation_runs`
     * carrying this review id, **excluding `handed_off` and `skipped`**. A
     * handoff spent nothing and tried nothing — it is the record of an exhausted
     * cost cap or an unavailable provider path, not of an attempt — and a skip
     * is a toggle or the kill switch. Counting them meant a review stranded by
     * an exhausted *monthly* cap used up its whole allowance inside half an hour
     * of a condition that does not clear until the 1st of the next month, which
     * is decisions 351/356/357's burial returning in a milder form.
     *
     * WHAT THE WINDOW ACTUALLY IS. Two bounds, both stated, because the honest
     * number depends on which failure you have:
     *
     *   vendor outage   1 run per sweep    24 sweeps x 15 min ~ 6 hours
     *   throwing job    3 runs per sweep     8 sweeps x 15 min ~ 2 hours
     *
     * The first is the shape this sweeper exists for: an unreachable or
     * unreadable provider produces an `unavailable` verdict, the job *succeeds*,
     * and nothing retries. The second is an infrastructure fault, where
     * re-dispatching for six hours buys nothing that a person is not already
     * needed for.
     *
     * The ceiling is a stop on a runaway loop rather than a budget: at ~0.11c
     * per moderation call the entire allowance is under 3c for one review.
     *
     * A review at the ceiling is not lost: it is undisplayable, it reached
     * routing untouched, and the owner already has a feed item about it.
     */
    private const int MAX_ATTEMPTS = 24;

    /**
     * Reviews considered per business per sweep.
     *
     * A cap rather than a chunked walk: this runs every fifteen minutes, so a
     * backlog drains across sweeps rather than in one burst of model calls
     * against a provider that may still be unwell.
     *
     * THE CEILING IS APPLIED BEFORE THIS LIMIT, IN SQL, AND THAT ORDERING IS THE
     * FEATURE. Taking the oldest hundred and *then* dropping the exhausted ones
     * in PHP meant a business holding a hundred permanently-failing reviews with
     * low ids filled its own quota with rows the sweeper had already given up
     * on, and every later stranded review became invisible forever — starvation
     * behind a limit that reads as a throttle.
     */
    private const int PER_BUSINESS_LIMIT = 100;

    public function handle(): int
    {
        // Asked before anything is enumerated. Each job would refuse
        // individually and correctly, but only after opening a `skipped` run
        // row — so a killed automation would still write one row per candidate
        // every time the schedule fired.
        if (AnalyzeReviewJob::killSwitchThrownFor('review.analyze')) {
            $this->info('Analysis is switched off; nothing re-dispatched.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$dispatched): void {
                foreach ($users as $user) {
                    $dispatched += $this->sweepOwner((int) $user->getKey());
                }
            });

        // Never leave a security context established after a console command.
        // The PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant the loop happened to touch last.
        Tenancy::forgetAll();

        $this->info($dispatched === 0
            ? 'No reviews are waiting on a model.'
            : "Re-dispatched analysis for {$dispatched} ".str('review')->plural($dispatched).'.');

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     */
    private function sweepOwner(int $userId): int
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by
        // the user just set, so it cannot widen beyond one person's own.
        $businessIds = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->pluck('id');

        $dispatched = 0;

        foreach ($businessIds as $businessId) {
            $dispatched += $this->sweepBusiness((int) $businessId);
        }

        return $dispatched;
    }

    private function sweepBusiness(int $businessId): int
    {
        // Inside the tenant from here down. Every query below is an ordinary
        // scoped Eloquent query with RLS beneath it — no withoutGlobalScope, no
        // raw cross-tenant read.
        Tenancy::set($businessId);

        // The kill-switch argument from handle(), one layer down and for the
        // other gate. With this tenant's monthly cap exhausted, every job
        // dispatched here hands off — no model call, no verdict — and opens a
        // `handed_off` run row for the privilege. A monthly cap does not clear
        // until the 1st, so sweeping through an exhausted one would write a row
        // per stranded review every tick for up to a month. Nothing is
        // abandoned: no attempt is counted, and the first sweep after the cap
        // resets finds these reviews exactly as it left them.
        if (! app(AiSpend::class)->allows()) {
            return 0;
        }

        $phi = app(PhiAnalysisConsent::class);

        $candidates = Review::query()
            ->where('source', ReviewSource::FirstParty)
            ->whereNull('moderation_flags')
            ->where('created_at', '<=', now()->subMinutes(self::STALE_AFTER_MINUTES))
            // ⚠️ THE SAME ARGUMENT AS THE CAP ABOVE, FOR A CONDITION THAT NEVER
            // CLEARS AT ALL — AND SINCE 2079-2081 A FILTER RATHER THAN AN EARLY
            // RETURN FOR THE WHOLE TENANT (2940).
            //
            // A covered entity's review is withheld from every model unless its
            // own author agreed to leave health information out, so
            // `moderation_flags` stays null for every unconsented one — exactly
            // the shape this sweep hunts for. Unfiltered, it would re-dispatch
            // every such review every fifteen minutes forever, each dispatch
            // handing off, opening a run row and changing nothing: decisions 356
            // and 364's stranding bug rebuilt on purpose. It would have looked
            // correct in review, because the job refuses the call and no PHI
            // leaves; the damage is noise and cost, not exposure.
            //
            // ⚠️ WHY THIS IS NO LONGER `return 0`. That was right while no review
            // of a covered entity could ever be analysed. Now one can, and a
            // *consented* review stranded by a vendor outage is precisely what
            // this command exists to recover — skipping the tenant wholesale
            // would have left the ruling half-applied, with a reader in the job
            // and no recovery behind it.
            ->when(
                $phi->handlesHealthInformation($businessId),
                fn (Builder $query): Builder => $query->whereIn('id', $phi->consentedReviewIds()),
            )
            // `reviews.id::text` rather than a cast on the other side:
            // `input->>'review_id'` is text out of jsonb, and casting *that* to
            // bigint would put the cast inside a subquery whose row filter the
            // planner is free to evaluate afterwards — one malformed `input`
            // anywhere in the table and the whole sweep raises instead of
            // sweeping.
            ->whereNotIn(DB::raw('reviews.id::text'), $this->exhausted())
            // Ascending on id: the oldest stuck reviews drain first, and an
            // ascending order raises none of the NULLS-FIRST problems the
            // ConventionsTest lint exists for.
            ->orderBy('id')
            ->limit(self::PER_BUSINESS_LIMIT)
            ->get(['id', 'location_id']);

        foreach ($candidates as $review) {
            AnalyzeReviewJob::dispatch($businessId, $review->location_id, (int) $review->id);
        }

        return $candidates->count();
    }

    /**
     * The review ids, as text, that have used up their attempts.
     *
     * ONE GROUPED ANTI-JOIN, APPLIED BEFORE PER_BUSINESS_LIMIT. This used to be
     * a second query and a PHP filter after the limit had already chosen a
     * hundred rows, which starved every review behind a hundred permanent
     * failures.
     *
     * THE TENANT PREDICATE SURVIVES BEING USED AS A SUBQUERY, and that is worth
     * saying out loud because it is not obvious. `automation_runs` is
     * tenant-owned; Laravel renders an Eloquent builder passed to `whereNotIn`
     * through `toSql()`/`getBindings()`, both of which are passthru methods that
     * run `applyScopes()` first, so `BelongsToTenant`'s global scope is in the
     * generated SQL. RLS sits under it either way, and no scope is removed
     * anywhere in this command.
     *
     * `IS NOT NULL` IS NOT DECORATION. `NOT IN (subquery)` where the subquery
     * yields a single NULL row is NULL for every candidate, so the sweeper would
     * silently dispatch nothing at all — and a `review.analyze` run row written
     * before `AnalyzeReviewJob::input()` carried the review id has exactly that
     * shape. Grouped, those rows collapse into one NULL group.
     *
     * @return Builder<AutomationRun>
     */
    private function exhausted(): Builder
    {
        return AutomationRun::query()
            ->where('automation_key', 'review.analyze')
            // A handoff spent nothing and a skip tried nothing. See MAX_ATTEMPTS.
            ->whereNotIn('status', [AutomationRunStatus::HandedOff, AutomationRunStatus::Skipped])
            ->whereRaw("input->>'review_id' is not null")
            ->groupByRaw("input->>'review_id'")
            ->havingRaw('count(*) >= ?', [self::MAX_ATTEMPTS])
            ->selectRaw("input->>'review_id'");
    }
}
