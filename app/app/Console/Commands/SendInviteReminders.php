<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReviewSource;
use App\Jobs\SendInviteReminderJob;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Review;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Services\Messaging\MessageLog;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Nudge the contacts whose review invite went nowhere — T176 §3's P14.
 *
 * *"Unanswered invite (no click, no review) → one reminder at tenant-set delay
 * (seed 3d) → stop."*
 *
 * ⛔ **THIS IS NOT THE `FollowUps` SCREEN AND MUST NEVER BE CONFLATED WITH IT.**
 * `crm.tasks_enabled`, `App\Livewire\Account\FollowUps` and `crm_tasks` are
 * **human task triage** — an owner's own to-do list about a contact, which they
 * create, snooze and close. Nothing there sends anything to anybody. This is an
 * automated outbound message to a member of the public, and the two share a word
 * and no machinery.
 *
 * ## Why a sweep rather than a delayed dispatch
 *
 * ⚠️ **`SendReviewInviteJob::dispatch()->delay(3 days)` IS THE OBVIOUS SHAPE AND
 * IT IS WRONG HERE**, for decisions 356 and 357's reason — a recovery path with
 * exactly one trigger and no retry. A payload sitting on the queue for three
 * days cannot be re-decided: it survives no worker restart it was not persisted
 * through, it carries a decision made before the owner switched solicitation
 * off, and **every refusal on this path is temporary** — a closed daytime
 * window, an arbiter hold, a global halt, a tenant pause, an exhausted balance —
 * so a one-shot delayed job would answer null once and be gone. A sweep asks
 * again on the next pass, which is what makes "held rather than sent" true.
 *
 * ## The enumeration
 *
 * ⚠️ **FOLLOWS `reviews:reinvite`, AND FOR ITS REASONS, WHICH ARE
 * `oauth:refresh-tokens`' REASONS.** This runs outside any tenant; `businesses`
 * is FORCE ROW LEVEL SECURITY on a policy keyed to the session tenant, so
 * `Business::all()` returns nothing and dropping a global scope does not help
 * because the policy is in the database. Reaching each business through its
 * owner grants this sweep no privilege a logged-in owner does not already have.
 * Read `ReinviteDeferredReviews`' docblock before changing this one.
 *
 * ⚠️ **THE CANDIDATE WINDOW IS BOUNDED AT BOTH ENDS, AND THE FAR END IS WHAT
 * STOPS THIS STARVING** (365's trap). Nothing marks a review as "considered and
 * declined" — deliberately, because almost every decline is temporary — so a
 * candidate that keeps being refused would sit at the front of an ascending
 * queue forever. `MAX_REMINDER_AGE_DAYS` is what makes that impossible: the
 * window rolls, and a review ages out of it whether or not anything was ever
 * sent. The one durable marker is the reminder itself, which
 * `ReviewInviteSender::remind()` refuses a second time.
 *
 * ⛔ **NO COLUMN WAS ADDED TO `reviews`, AND THE OBVIOUS DESIGN WOULD HAVE ONE.**
 * An `invite_reminder_sent_at` beside `invite_deferred_at` reads naturally and
 * would be a **second source of truth for a fact `outreach_messages` already
 * holds** — the row *is* the record that a reminder went, and a marker beside it
 * is one more thing to disagree with. The rolling window is what a marker was
 * going to buy (a bounded candidate set), and it buys it without the second
 * store. ⚠️ It also means the ceiling is honest about what it measures: how long
 * ago the customer was served, not how long ago somebody wrote a flag.
 */
#[Signature('reviews:remind')]
#[Description('Send the single follow-up for review invites nobody acted on')]
final class SendInviteReminders extends Command
{
    /**
     * Reviews considered per business per sweep.
     *
     * `ReanalyseReviews`' constant and its reasoning: a cap rather than a
     * chunked walk, so a backlog drains across sweeps.
     */
    private const int PER_BUSINESS_LIMIT = 100;

    /**
     * How old an invite may be and still earn a follow-up.
     *
     * ⛔ **A CONSTANT RATHER THAN A REGISTRY KEY, ON
     * `ReviewRouter::MAX_DEFERRAL_DAYS`' PRECEDENT** (4031). It is not a policy
     * figure an operator should be tuning — it bounds the sweep's candidate
     * window — and a second editable number here would let somebody set
     * `reviews.invite_reminder_delay_days` beyond it, which stops every reminder
     * while both screens read correctly.
     *
     * ⚠️ **FOURTEEN, BECAUSE A NUDGE ABOUT A FORTNIGHT-OLD VISIT IS A COLD
     * MESSAGE.** The person wrote their feedback the day they were served; a
     * reminder long after that reads as a stranger's marketing rather than a
     * follow-up, and it is the reading a complaint comes from. It is comfortably
     * wider than the seeded three-day delay, so an ordinary tenant has eleven
     * days of sweeps in which a closed window, a pause or a missing region code
     * can clear.
     */
    private const int MAX_REMINDER_AGE_DAYS = 14;

    public function handle(): int
    {
        // Asked before anything is enumerated, on `ReinviteDeferredReviews`'
        // reasoning: each job would refuse individually and correctly, but only
        // after opening a `skipped` run row, so a killed automation would write
        // one row per candidate every time the schedule fired.
        if (SendInviteReminderJob::killSwitchThrownFor('review.invite.reminder')) {
            $this->info('Invite follow-ups are switched off; nothing sent.');

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
            ? 'No invite follow-ups are due.'
            : "Queued {$dispatched} invite ".str('follow-up')->plural($dispatched).'.');

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

        // ⚠️ A PAUSED OR SUSPENDED TENANT IS SKIPPED WHOLE. Each job would refuse
        // individually — `AutopilotJob` records `skipped` with a reason (821–823)
        // — so what this buys is not correctness but silence: a fortnight-long
        // pause would otherwise write a run row per candidate every fifteen
        // minutes. Nothing is lost by waiting, because a candidate stays a
        // candidate until it ages out.
        if (app(TenantPause::class)->isCurrentTenantPaused()) {
            return 0;
        }

        if (app(TenantSuspension::class)->isCurrentTenantSuspended()) {
            return 0;
        }

        $delayDays = app(DefaultsRegistry::class)->int('reviews.invite_reminder_delay_days');

        $now = CarbonImmutable::now();
        $messages = app(MessageLog::class);

        // ⛔ **THE WINDOW IS ON `reviews.created_at` AND NOT ON `routed_at`, AND
        // THE FIRST VERSION OF THIS QUERY USED THE LATTER.** `ReviewsTest`'s
        // *"nothing outside the review router routes a review"* lint refuses the
        // three routing column names anywhere but `ReviewRouter`, and it is
        // right to: a sweep filtering on routing state is one step from a sweep
        // writing it. **The lint pointed at a better column.** This ceiling is
        // about how long ago the customer was *served* — a nudge about a
        // fortnight-old visit is a cold message — and that is the submission, not
        // the moment a snapshot happened to be computed.
        //
        // ⚠️ **NOTHING IS LOST BY DROPPING THE "WAS IT ROUTED" PREDICATE**,
        // because the check that replaces it is strictly stronger: an invite row
        // has to exist, which cannot happen unless the review was routed *and*
        // something was actually sent. That is asked per candidate below,
        // because `outreach_messages` is keyed on the contact rather than on the
        // review.
        //
        // ⚠️ **THE LOWER BOUND IS A PREFILTER ONLY, AND DELETING IT CHANGES
        // NOTHING THE SUITE CAN SEE** — said out loud, because an unfalsifiable
        // line is one somebody later removes as dead. The delay is re-applied to
        // the invite's own timestamp below (routing and sending are the same
        // second on the ordinary path and days apart when `reviews:reinvite`
        // recovers a paused tenant's invites), and **that** check is what
        // decides, and is driven red by mutation. What this bound buys is that
        // the hundred-row page is not filled with reviews that are not due yet
        // while ones that are wait behind them — a starvation property that
        // needs more than `PER_BUSINESS_LIMIT` rows to observe and is therefore
        // not pinned by a test.
        $candidates = Review::query()
            ->where('source', ReviewSource::FirstParty)
            ->whereNotNull('customer_id')
            ->where('created_at', '>=', $now->subDays(self::MAX_REMINDER_AGE_DAYS))
            ->where('created_at', '<=', $now->subDays($delayDays))
            // Ascending on id: the oldest drain first, and an ascending order
            // raises none of the NULLS-FIRST problems the ConventionsTest lint
            // exists for.
            ->orderBy('id')
            ->limit(self::PER_BUSINESS_LIMIT)
            ->get();

        // ⚠️ One query for the whole page rather than a `find()` inside the loop
        // — an N+1 on a sweep that already asks `MessageLog` twice per candidate.
        // `Customer` is tenant-scoped, so a contact belonging to somebody else
        // simply is not in this map and its review is skipped.
        $customers = Customer::query()
            ->whereIn('id', $candidates->pluck('customer_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $dispatched = 0;

        foreach ($candidates as $review) {
            $customer = $customers->get($review->customer_id);

            if (! $customer instanceof Customer) {
                continue;
            }

            // ⚠️ **BOTH ASKED HERE AND BOTH ASKED AGAIN IN `remind()`.** That is
            // not redundancy for its own sake: this is what keeps the sweep from
            // opening an `automation_runs` row per contact per fifteen minutes
            // for the rest of the fortnight, and the service's copies are what
            // make the guarantee true for any other caller — 398's rule, that an
            // outer guard refusing first must never be the only thing making an
            // inner one hold.
            if ($messages->reviewInviteReminderSent($customer)) {
                continue;
            }

            $invitedAt = $messages->reviewInviteFor($customer)?->created_at;

            if ($invitedAt === null || $invitedAt->greaterThan($now->subDays($delayDays))) {
                continue;
            }

            // `location_id` is NOT NULL with a foreign key — `ReviewRouter`'s own
            // docblock says so. ⚠️ This sentence used to add "and an unrouted
            // review never reaches here", which stopped being true when the
            // `routed_at` predicate came out; what guarantees the review was
            // routed now is that an invite row exists for its contact, which is
            // the line above.
            SendInviteReminderJob::dispatch(
                $businessId,
                (int) $review->location_id,
                (int) $review->id,
            );

            $dispatched++;
        }

        return $dispatched;
    }
}
