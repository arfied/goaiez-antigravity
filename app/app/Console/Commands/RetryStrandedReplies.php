<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Reviews\PostReplyJob;
use App\Models\Business;
use App\Models\Reply;
use App\Models\User;
use App\Services\Billing\Subscriptions;
use App\Services\Gbp\GbpConnections;
use App\Services\Reviews\ReplyPublicationStatus;
use App\Services\Reviews\ReviewReplies;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ask Google again for the replies an owner approved while a blocker was up
 * (11040–11043).
 *
 * ⛔ **THE DEFECT, IN ONE SENTENCE.** An owner presses Approve on a reply to a
 * real customer's Google review; `ReviewReplies::approve()` dispatches
 * `PostReplyJob` **once**; the attempt hands off because `gbp.zernio_enabled`
 * was false or the location had no usable connection; and the row sat in
 * `Approved` for ever. Nothing re-dispatched it, nothing watched for the switch
 * being turned on or for the location being reconnected, and the only retry the
 * product had was the owner noticing the card and pressing *Approve again*
 * (1755, 6524). **This command is the queue 6523 refused to name.**
 *
 * ## ⛔ 6523 refused the WORDS because the MECHANISM was missing
 *
 * *"`approve()` dispatches `PostReplyJob` **once**. Nothing re-dispatches it, no
 * scheduler sweeps for approved-and-unpublished rows, and nothing watches for
 * `gbp.zernio_enabled` being switched on or for a location being reconnected.
 * ‘Waiting to go up on Google’ would name a queue that does not exist."* That
 * refusal is **satisfied rather than overturned**: it refused a future-tense
 * promise on a screen, and the thing it was waiting for is this file.
 * ⚠️ **The heading is untouched.** 7048 records *"Approved, not yet on
 * Google"* as the owner's to rule on and deliberately unchanged, and a queue
 * existing on a branch is not a queue anybody has watched run — see 11044.
 *
 * ## ⛔ Why this is not the `jobs` horizon `CLAUDE.md` forbids
 *
 * That warning is about a sweep over the **queue table**: *"the only reason a
 * `jobs` row gets old is that nothing is popping its queue, so a sweep would
 * keep the table small and every report calm precisely while the defect it
 * looks like a fix for was running."* The subject there is a symptom of a
 * broken worker and the sweep would erase the evidence of it.
 *
 * ⚠️ **This sweep reads `replies` and erases nothing.** Its subject is a
 * **completed** attempt that correctly refused — the handoff path `29` §2 rule
 * 44 requires — and the condition it waits on is not ours at all: an operator's
 * switch, or an owner reconnecting their own Google account. Every attempt it
 * causes writes an `automation_runs` row exactly as the owner's own would, the
 * reply keeps `publish_retry_dispatched_at` for ever, and a reply it fails to
 * publish is **more** visible afterwards, not less: it still sits on *Approved,
 * not yet on Google* with the same sentence on its card. ⛔ **Nothing here makes
 * a report calmer.**
 *
 * ## ⛔ The claim is spent by a DISPATCH, never by an EVALUATION
 *
 * A bound is unavoidable — 7126 refused to re-dispatch from
 * `reconcileUnconfirmedPublication()` precisely because an unbounded retry
 * republishes on a public listing on a loop, notifying a member of the public
 * each time. `replies.publish_retry_dispatched_at` is that bound at **one per
 * owner decision**.
 *
 * ⛔ **A claim keyed on the subject would be spent by the first refusal**: a
 * tenant whose integration is switched on while their location is still
 * disconnected would have burned their one attempt on a sweep that dispatched
 * nothing, and reconnecting later would do nothing at all. So every gate below
 * — the kill switch, the registry switch, the suspension, the pause, the
 * entitlement, the registry switch again per business, the connection, the
 * abandoned run — **skips without touching the column**, and
 * {@see ReviewReplies::markPublishRetryDispatched()} is called only after
 * `PostReplyJob::dispatch()` has returned.
 *
 * ## ⛔ And the evaluation that matters happens ONE HOP LATER — 11205, 11206
 *
 * ⛔ **THE HEADING ABOVE IS ACCURATE ABOUT WHERE THE WRITE HAPPENS AND OBSCURES
 * WHERE THE DECISION HAPPENS, AND IT IS KEPT BECAUSE THE PROPERTY IT NAMES IS
 * REAL.** {@see PostReplyJob::canExecute()} **re-makes every gate on the
 * worker** and can refuse *after* the stamp is written. So a claim can be spent
 * by a dispatch whose attempt never happened, and nothing hands it back: the
 * only clearer of `publish_retry_dispatched_at` is `ReviewReplies::approve()`,
 * which is a person deciding again — precisely the state this command exists to
 * stop needing.
 *
 * ⚠️ **THE POPULATION IS SMALLER THAN THAT SENTENCE SOUNDS, AND ENUMERATING IT
 * IS WHAT DECIDED THE FIX.** Of the arms `canExecute()` can refuse on after this
 * command has dispatched:
 *
 *   · `not_approved` is unreachable — `strandedAwaitingPublication()` filters
 *     on `Approved`;
 *   · `auto_approval_lapsed` is a reviewer who edited their stars below the
 *     floor, and burning a claim on a row that must **never** publish costs
 *     nothing;
 *   · `reply_missing` and `location_missing` are rows that no longer exist;
 *   · **`integration_disabled` and `not_connected` are the two that are
 *     transient**, and they are exactly the two blockers this command was
 *     written to wait for.
 *
 * ⛔ **SO THE FIX IS TO MAKE THIS COMMAND'S GATE SET A SUPERSET OF THE JOB'S
 * FOR EVERYTHING IT CAN READ, NOT TO RELEASE THE CLAIM ON A HANDOFF** (11205).
 * A release would re-arm this sweep every fifteen minutes for as long as the
 * blocker was up: an `automation_runs` row and an `OwnerActionNeeded` activity
 * item per stranded reply per sweep, for ever. ⚠️ **That is a different loop
 * from the one 7126 refused** — nothing public repeats, because a handoff never
 * reaches the vendor — and it is refused for its own reason rather than by
 * citing that one. The residue is a genuine race between this line and the
 * worker, which the queue makes irreducible; the entitlement gate is in this
 * file so that it never becomes the third member of the transient set.
 *
 * ## ⚠️ What this command does NOT do
 *
 * ⛔ **It never decides that a reply is on Google and it never writes one word
 * about a listing.** It dispatches the job that already exists, and every guard
 * that job makes it makes again on the worker.
 *
 * ⛔ **THE SENTENCE THAT STOOD HERE WAS WRONG ABOUT *WHERE*, AND WAVE 43 MADE
 * THE CONTRADICTION LOCATABLE RATHER THAN CREATING IT — 11451.** It read
 * *"`canExecute()` re-reads the registry, the location and the connection, and
 * `execute()` re-reads all of them"*. `execute()` re-reads the location and the
 * connection and **not** the registry, and not the plan either. ✅ **The honest
 * statement is that the registry read is re-made CLOSER to the act than
 * `execute()` could put it**: `ZernioGbpClient::assertEnabled()` reads
 * `gbp.zernio_enabled` as the last statement before the HTTP request, which is
 * why {@see PostReplyJob} owes no duplicate of that arm (11360, 11361).
 * A blocker that comes back between this dispatch and that worker produces an
 * ordinary handoff.
 *
 * ⛔ **It files nothing in the activity feed.** The feed already speaks for both
 * outcomes — `ReplyPosted` when the attempt lands (7134) and `OwnerActionNeeded`
 * when it does not — and an item here could only promise a future post, which
 * is the sentence 1747 removed and 6523 refused (11042).
 *
 * ⛔ **It sends no message to the owner, on any channel.** Texting an account
 * holder needs the owner-channel consent argument and a new
 * `OwnerNotificationKind` (10820–10824); an email would be the twenty-seventh
 * notification class in a product whose first tiebreaker is less support
 * surface. **The screen and the feed are the surfaces, and they are already
 * built.**
 *
 * ## ⚠️ Nothing here catches a throwable, and that is the point
 *
 * On every deployment that exists `QUEUE_CONNECTION` is `database`, so
 * `PostReplyJob::dispatch()` is an insert: the only way it throws is a database
 * fault, and a sweep that carried on through one would report a clean run
 * having dispatched nothing. A per-reply `try`/`catch` here would swallow
 * exactly the class of failure this wave exists to make visible.
 *
 * ⚠️ **On the `sync` driver — the test harness, and nothing else — the job
 * runs inline and a retryable vendor failure propagates out of `dispatch()` and
 * ends the sweep.** That is loud rather than silent, which is the direction this
 * codebase chooses, and it is stated here so nobody reads the absence of a catch
 * as an oversight.
 *
 * ## ⚠️ `replies.hold_until` is not asked, and nothing else asks it either
 *
 * The column exists and **nothing in `app/` ever sets it to a future time** —
 * `ReviewReplies::recordSuggestion()` writes `null` and is its only writer
 * (measured 2026-08-28; `php artisan db:column-readers` scores it alive off a
 * bare-token collision with `growth_pages.hold_until`). `isPublishable()` does
 * not ask it either, so the owner's own `approve()` dispatch ignores it too.
 * ⛔ **A `whereNull('hold_until')` here would be a guard nothing can drive**
 * (256) — **and the day somebody gives that column a writer it is owed in two
 * places, here and in `isPublishable()`, and neither has it today.**
 *
 * ## The enumeration
 *
 * ⚠️ **`ReinviteDeferredReviews`' walk, for its reasons** — this runs outside
 * any tenant, `businesses` is FORCE row-level secured on a policy keyed to the
 * session tenant, so reaching each business through its owner grants this sweep
 * no privilege a logged-in owner does not already have. Read that command's
 * docblock, and `RefreshOauthTokens`' before it, before changing this one.
 */
#[Signature('reviews:retry-stranded-replies')]
#[Description('Re-dispatch approved replies that never reached Google, once the blocker has cleared')]
final class RetryStrandedReplies extends Command
{
    /**
     * Replies considered per business per sweep.
     *
     * `ReinviteDeferredReviews::PER_BUSINESS_LIMIT`'s figure and its reasoning:
     * a cap rather than a chunked walk, so a backlog drains across sweeps.
     * ⚠️ **The starvation trap decision 365 records cannot arise here**, because
     * a swept reply always leaves the population — `markPublishRetryDispatched()`
     * stamps it whether or not the attempt succeeds, so nothing can sit at the
     * front of this queue for ever.
     */
    private const int PER_BUSINESS_LIMIT = 100;

    public function handle(): int
    {
        // Asked before anything is enumerated, on `ReanalyseReviews`' reasoning:
        // each job would refuse individually and correctly, but only after
        // opening a `skipped` run row, so a killed automation would write one
        // row per candidate every time the schedule fired.
        //
        // ⚠️ THE CLAIM COLUMN IS LEFT ALONE, WHICH IS THE POINT. A thrown kill
        // switch means "not now", and a sweep that spent its one attempt per
        // reply while switched off would make "not now" mean "never".
        if (PostReplyJob::killSwitchThrownFor('reviews.post_reply')) {
            $this->info('Posting replies to Google is switched off; nothing re-dispatched.');

            return self::SUCCESS;
        }

        // ⚠️ **ASKED HERE FOR THE WHOLE RUN AND AGAIN PER BUSINESS — THIS
        // COMMENT SAID "ONCE FOR THE WHOLE RUN" AND THAT IS CORRECTED**
        // (11206). One read for a walk over every user meant an operator
        // flipping the switch off mid-run burned a claim on every reply left in
        // the run; {@see self::sweepBusiness()} asks again. **This read stays**
        // because it saves the entire walk when the answer is already no, and
        // because it is the one that produces the operator's sentence below.
        // ⚠️ It is still `ReplyPublicationStatus::publishingIsSwitchedOn()` at
        // both sites rather than two `->value()` calls, so there is one spelling
        // of the predicate and not a re-typed twin.
        //
        // ⛔ **AND IT IS EXACTLY THE BLOCKER THIS COMMAND EXISTS FOR**, so the
        // early return is not a nicety: with the integration off, every reply
        // in the population would hand off again and this sweep would spend
        // every claim it has on the state it is waiting to see cleared.
        if (! app(ReplyPublicationStatus::class)->publishingIsSwitchedOn()) {
            $this->info('Publishing replies to Google is switched off; nothing re-dispatched.');

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
            ? 'No approved replies are waiting on a blocker that has cleared.'
            : "Re-dispatched {$dispatched} approved ".str('reply')->plural($dispatched).'.');

        return self::SUCCESS;
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     */
    private function sweepOwner(int $userId): int
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity `ResolveTenant`
        // documents and `ReinviteDeferredReviews` answers the same way. The
        // database still restricts this to businesses owned by the user just
        // set, so it cannot widen beyond one person's own.
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

        // Both asked here rather than left to the job, on `ReanalyseReviews`'
        // reasoning again: `AutopilotJob::handle()` would refuse each dispatch
        // correctly and write a `skipped` run row per candidate first. ⚠️ And
        // both leave the claim column untouched, so a fortnight's pause defers
        // these attempts rather than destroying them.
        if (app(TenantSuspension::class)->isCurrentTenantSuspended()) {
            return 0;
        }

        if (app(TenantPause::class)->isCurrentTenantPaused()) {
            return 0;
        }

        // ⛔ **THE PLATFORM SWITCH, ASKED AGAIN AND PER BUSINESS** (11206). The
        // read at the top of `handle()` is one row for a walk over every user,
        // so an operator flipping the integration off mid-run made every
        // business after that moment dispatch a job that hands off — spending
        // one claim per reply on the exact condition this command exists to wait
        // for. ⚠️ **Deliberate cheapness with a permanent cost is still a
        // permanent cost**: a burned claim is not recoverable until a person
        // presses Approve again, which is the state this command exists to end.
        //
        // ⚠️ **IT NARROWS THE WINDOW AND CANNOT CLOSE IT.** The worker runs
        // later than this line by construction, so `canExecute()` may still find
        // the switch down; what changes is that the exposure is one business's
        // page of replies rather than every business left in the run. **Per
        // reply would be a hundred more reads per business** and would buy only
        // the difference between one page and one row.
        //
        // ⚠️ **REGISTRY READS ARE NOT CACHED, DELIBERATELY** — one indexed
        // lookup on a tiny table, beside the three tenant reads this method
        // already makes.
        //
        // ⛔ **AND THE AXIS THIS VARIES ON IS TIME, NEVER THE BUSINESS — SAYING
        // SO IS THE POINT.** `gbp.zernio_enabled` is one platform row, so its
        // answer is identical for every business in the run at any one instant:
        // asked of the businesses, this predicate **cannot** vary, which is the
        // shape `canDeliver()` was caught in. What it watches is the run's own
        // duration, and that is the only thing it is claimed to watch. A reader
        // who takes it for a per-tenant gate has read it wrong, and the reason
        // it earns its place is written above rather than assumed.
        //
        // ⚠️ **IT IS ASKED BEFORE THE ENTITLEMENT READ BELOW, MATCHING THE
        // CARD'S LADDER AND `PostReplyJob::canExecute()`'s ORDER.** Both arms
        // return 0 and nothing is printed either way, so the ordering buys
        // nothing today — it is here so that the three places this question is
        // asked cannot drift into naming different blockers first.
        if (! app(ReplyPublicationStatus::class)->publishingIsSwitchedOn()) {
            return 0;
        }

        // ⛔ **THE THIRD THING THAT IS TRUE OF THE ACCOUNT RATHER THAN THE
        // REPLY, AND UNTIL 2026-08-28 NOTHING ON THIS PATH ASKED IT**
        // (11200, 11204). This command gated the kill switch, the platform
        // switch, suspension, pause, the connection and an abandoned run — and
        // never whether the tenant still had a plan. A tenant whose annual term
        // expired sits at `status = active` with a past `ends_at`, a state
        // `Subscriptions::applyAuthorizeNetSubscription()` deliberately creates
        // so they keep the year they bought (2748), and nothing ever revisits
        // it (8960–8979): so they are neither suspended nor paused, they clear
        // every gate above, and their approved-but-stranded replies go up under
        // their business name on their public Google listing within fifteen
        // minutes of the blocker clearing.
        //
        // ⚠️ **IT IS ASKED HERE *AS WELL AS* IN `PostReplyJob::canExecute()`
        // AND THE SECOND ASK IS NOT BELT AND BRACES.** Every gate the job makes
        // that this command does not make is a window in which a dispatch spends
        // a claim on an attempt that never happens — the property this file's
        // suspension, pause and connection gates already exist for, restated
        // for a third condition rather than a new idea. Without it, a lapsed
        // tenant's replies would burn their one attempt each and would still be
        // unpublished on the day somebody resubscribed.
        //
        // ⚠️ **A MISSING BUSINESS ROW IS NOT REACHABLE HERE** — `sweepOwner()`
        // plucked this id out of `businesses` moments ago — and the read is
        // scoped to the tenant just established.
        $business = Business::query()->find($businessId);

        if ($business instanceof Business && ! app(Subscriptions::class)->isEntitled($business)) {
            return 0;
        }

        $connections = app(GbpConnections::class);

        // ⛔ **THE SECOND BLOCKER, AND IT IS PER LOCATION RATHER THAN PER
        // TENANT.** A business with two locations may have reconnected one of
        // them; the replies at the other are still stranded and must not spend
        // their claim. `usableLocationIds()` is the same predicate
        // `SyncGoogleReviews` enumerates and `Account\Home` reports on, asked
        // through the one method that owns it (9917) rather than restated here.
        $locationIds = $connections->usableLocationIds()->map(fn ($id): int => (int) $id)->all();

        if ($locationIds === []) {
            return 0;
        }

        $replies = app(ReviewReplies::class);

        $candidates = $replies->strandedAwaitingPublication($locationIds, self::PER_BUSINESS_LIMIT);

        if ($candidates->isEmpty()) {
            return 0;
        }

        // ⛔ **THE FOURTH REFUSAL, AND THE ONLY ONE THAT IS NOT A COLUMN ON THE
        // ROW** (10264). A `reviews.post_reply` run this platform's own worker
        // killed mid-flight may have been killed **during** the vendor call,
        // after Google accepted the reply and before this application recorded
        // it — which is epistemically identical to `publish_unconfirmed_at`, and
        // `ReplyPublicationStatus` already says so on the card. Re-dispatching
        // such a row would publish the same text a second time under somebody
        // else's name to settle a question this application cannot answer.
        //
        // ⚠️ **ONE BATCH QUERY FOR THE WHOLE BUSINESS, NEVER ONE PER REPLY** —
        // the discipline `Account\ReplyQueue::publicationStates()` states for
        // the same reader.
        $abandoned = app(ReplyPublicationStatus::class)->abandonedReplyIds(
            $candidates->map(fn (Reply $reply): int => (int) $reply->getKey())->all(),
        );

        $dispatched = 0;

        foreach ($candidates as $reply) {
            if (isset($abandoned[(int) $reply->getKey()])) {
                continue;
            }

            $locationId = $reply->review?->location_id;

            // `strandedAwaitingPublication()` filters on the review's location,
            // so a candidate without one cannot exist. This narrows the type
            // rather than adding a refusal — 1933's distinction, kept.
            if ($locationId === null) {
                continue;
            }

            PostReplyJob::dispatch($businessId, (int) $locationId, (int) $reply->getKey());

            // ⛔ **AFTER THE DISPATCH AND NEVER BEFORE IT.** The claim records
            // an occasion that happened; a stamp written first would be spent
            // by a `dispatch()` that threw.
            $replies->markPublishRetryDispatched($reply);

            $dispatched++;
        }

        return $dispatched;
    }
}
