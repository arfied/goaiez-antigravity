<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AutomationRunStatus;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Jobs\RunCampaignJob;
use App\Models\AutomationRun;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Give the campaign runner a trigger (T137 `SL-2`, lane `L3`, decision 2578).
 *
 * ⛔ **`RunCampaignJob` HAD NO DISPATCHER AT ALL, AND THE SUITE WAS GREEN.**
 * The only `dispatch()` anywhere was the job's own re-queue in
 * `closeIfFinished()` — which can never fire, because nothing starts the chain.
 * `Campaigns::confirm()`, `enrol()` and `resume()` all returned without
 * dispatching, nothing sat on the scheduler, and the three constructions in the
 * repository were tests calling `handle()` by hand. `CLAUDE.md`'s
 * writerless-control shape (272) with the writer missing rather than the
 * reader.
 *
 * ## ⛔ AND ITS NEXT SENTENCE WAS FALSE WHEN IT WAS WRITTEN, WHICH MAKES
 * ## THIS A SWEEP WITH NOTHING TO SWEEP — 2026-08-28 (11520–11524)
 *
 * ⛔ **THE SUPERSEDED SENTENCE, KEPT AND DATED ON 4368'S RULE**, because it
 * is the premise the whole of 2577/2578 was argued from and it is still in
 * `DECISIONS.md`: *"A tenant could draft, confirm and enrol a campaign and
 * **not one message would ever leave**, with no error, no failed job and no run
 * row."* ⚠️ **The diagnosis was right and the actor was wrong.** No tenant
 * could draft one, then or now: `Services\Campaigns\Campaigns::enrol()` is the
 * sole writer of `campaign_recipients` and **has no caller in `app/` at all**,
 * `Campaigns::confirm()` has none either, and the only method of that class
 * anything in `app/` calls is `draft()` — from `CampaignPacks::activate()`,
 * which has no caller of its own. The measurement, and why *no caller* is a
 * proof here rather than the usual floor, is written at `enrol()`.
 *
 * ⚠️ **SO THIS COMMAND HAS RUN NINETY-SIX TIMES A DAY OVER A TABLE NOTHING
 * IN `app/` CAN POPULATE**, and every instrument this platform has reports it
 * as healthy: it exits 0, it opens no run row of its own, it fails no job, and
 * it prints the same sentence an idle-but-working sweep prints. **Nothing here
 * is withdrawn** — 2578's four stranding conditions are real and this is still
 * the only trigger, and the subsystem is deliberately not deleted: nothing
 * anywhere says it is unwanted, only that nothing reaches it. ✅ **What
 * changes is that the sweep now reports what it FOUND and not only what it
 * dispatched** — {@see self::report()}, which carries the design and the two
 * things it deliberately refuses.
 *
 * ## Why a sweep, and not a dispatch from the start path
 *
 * ⚠️ **BECAUSE THE THING THAT STARTS A CAMPAIGN IS NOT THE ONLY THING THAT HAS
 * TO COME BACK TO IT.** `reviews:reinvite` argues this at length two entries up
 * in `routes/console.php` and decisions 356/357 are the record: *a recovery path
 * with exactly one trigger and no retry is the bug they describe*. Four
 * conditions here end a campaign's chain with work still outstanding, and only
 * one of them has an event anybody could hook:
 *
 *   quiet hours       2570 makes a campaign started at 21:30 defer its audience
 *                     rather than destroy it — but deferring is only half a fix
 *                     if nothing returns at 08:00. **This is the other half.**
 *   a tenant pause    `AutopilotJob::handle()` checks the pause *before*
 *                     `execute()`, so a paused tenant's pass returns at
 *                     `recordSkip()` and `closeIfFinished()` never runs. The
 *                     chain is not delayed, it is **gone** — 822's stranding
 *                     bug, in the campaign lane.
 *   a lost dispatch   a worker killed between the transaction and the pickup
 *                     takes the whole campaign with it.
 *   a hand-off        `canExecute()` false runs `handoff()`, which deliberately
 *                     marks nobody and re-queues nothing.
 *
 * A dispatch from `confirm()` addresses none of those, so it would have to exist
 * *beside* this rather than instead of it — a second trigger buying back at most
 * one sweep interval of latency on a bulk marketing campaign, at the cost of the
 * doubling hazard below having two sources instead of one. It is not built.
 *
 * ## The doubling hazard, which is why the staleness window exists
 *
 * ⛔ **TWO CHAINS ON ONE CAMPAIGN DO NOT RACE, THEY MULTIPLY.** Each pass
 * re-queues itself, so a sweep that dispatched beside a live chain would leave
 * two, and each of those would leave two more: the queue fills with one campaign
 * re-marking the same rows, at exactly the interval an operator is least likely
 * to be watching. `RunCampaignJob` cannot take an idempotency key to stop it —
 * its own docblock explains that a key would make the second pass collide with
 * the first and the campaign would stop after one batch, silently.
 *
 * So a campaign is a candidate only if no pass has *started* within
 * {@see self::IN_FLIGHT_MINUTES}, read from `automation_runs` — the exhaustive
 * record of attempts that `AutopilotJob` already writes, keyed by the
 * `campaign_id` that job now puts in its `input()`. The window is **twice**
 * `RunCampaignJob::BARREN_PASS_MINUTES` and derived from it rather than typed
 * beside it, because the two must never cross: at or below the barren interval
 * every waiting campaign is dispatched a second time on the sweep that lands
 * between its passes.
 */
#[Signature('campaigns:run-due')]
#[Description('Dispatch a sending pass for every reactivation campaign that is due one')]
final class RunDueCampaigns extends Command
{
    /**
     * How long since a pass started before this campaign counts as stalled.
     *
     * ⚠️ **DERIVED, NEVER TYPED.** See the class docblock: below the barren
     * backoff this command doubles every campaign it is meant to rescue.
     */
    private const int IN_FLIGHT_MINUTES = RunCampaignJob::BARREN_PASS_MINUTES * 2;

    /**
     * Campaigns considered per business per sweep.
     *
     * `ReinviteDeferredReviews`' constant and its reasoning — a cap rather than a
     * chunked walk, so a backlog drains across sweeps. Nothing starves at the
     * front of this queue: a dispatched campaign gets a run row and therefore
     * stops being a candidate, so the next sweep sees the ones behind it.
     */
    private const int PER_BUSINESS_LIMIT = 25;

    /**
     * What this run found, reset at the top of {@see self::handle()}.
     *
     * ⛔ **THE RESET IS LOAD-BEARING AND IS NOT DEFENSIVE TIDINESS.** The
     * console kernel caches a resolved command and `Artisan::call()` reuses
     * that instance, so a second invocation inside one process — which is what
     * a test running this command twice is, and what any long-lived console
     * caller would be — would otherwise report the first run's figures added
     * to the second's. `CampaignSweepTest`'s *"running the sweep twice does not
     * double its own tally"* drives exactly that, and removing any one of these
     * four lines reddens it.
     *
     * ⚠️ **INSTANCE STATE RATHER THAN A RETURNED SHAPE**, because
     * {@see self::sweepOwner()} and {@see self::sweepBusiness()} already return
     * the dispatch count and threading four more through both signatures would
     * put the arithmetic in three places instead of one.
     */
    private int $businessesSwept = 0;

    private int $businessesSkipped = 0;

    private int $campaignsHeld = 0;

    private int $businessesWithARecipient = 0;

    public function handle(): int
    {
        $this->businessesSwept = 0;
        $this->businessesSkipped = 0;
        $this->campaignsHeld = 0;
        $this->businessesWithARecipient = 0;

        // Asked before anything is enumerated, on `ReinviteDeferredReviews`'
        // reasoning: each job would refuse individually and correctly, but only
        // after opening a `skipped` run row, so a killed automation would write
        // one row per campaign every time the schedule fired.
        if (RunCampaignJob::killSwitchThrownFor('react.campaign_pass')) {
            $this->info('Reactivation campaigns are switched off; nothing dispatched.');

            return self::SUCCESS;
        }

        // ⚠️ **THE CHANNEL SWITCH IS READ HERE AS WELL AS IN THE JOB, AND IT IS
        // NOT A SECOND COPY OF THE RULE.** `RunCampaignJob::canExecute()` reads
        // the same key and hands off when it is false; the hand-off marks nobody
        // and re-queues nothing, so without this line every sweep would file one
        // more "your campaign is waiting" activity item per campaign, forever,
        // for as long as the switch is down. The registry is platform-scoped, so
        // this is answerable before any tenant is established.
        if (! app(DefaultsRegistry::class)->value('sms.enabled')) {
            $this->info('Text messaging is switched off; nothing dispatched.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        // The enumeration follows `reviews:reinvite`, and for its reasons, which
        // are `oauth:refresh-tokens`' reasons. This runs outside any tenant,
        // `businesses` is FORCE ROW LEVEL SECURITY on a policy keyed to the
        // session tenant, so `Business::all()` returns nothing and dropping a
        // global scope does not help because the policy is in the database.
        // Reaching each business through its owner grants this sweep no
        // privilege a logged-in owner does not already have.
        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$dispatched): void {
                foreach ($users as $user) {
                    $dispatched += $this->sweepOwner((int) $user->getKey());
                }
            });

        // Never leave a security context established after a console command. The
        // PostgreSQL session variable outlives this process's connection under any
        // pooler, and a worker inheriting it would start as whichever tenant the
        // loop happened to touch last.
        Tenancy::forgetAll();

        $this->report($dispatched);

        return self::SUCCESS;
    }

    /**
     * What this sweep found, in figures, whether or not it dispatched anything.
     *
     * ⛔ **THE SENTENCE THIS REPLACES COLLAPSED THREE STATES INTO ONE.** *"No
     * campaigns are waiting for a pass"* was printed when no campaign existed
     * at all, when campaigns existed with nobody enrolled behind them, and when
     * every candidate already had a live chain — so an operator reading it had
     * no way to tell an idle platform from a subsystem nothing can reach.
     * ⚠️ **It has printed the first of those three on every deployment that has
     * ever existed**, because `Services\Campaigns\Campaigns::enrol()` is the
     * sole writer of `campaign_recipients` and has no caller in `app/`
     * (11520–11524). A sweep running ninety-six times a day over a table
     * nothing can populate was, to every instrument this platform has,
     * indistinguishable from a sweep that is working.
     *
     * ⛔ **AND NO CAUSAL CLAIM GOES INTO THE OUTPUT, WHICH IS THE WHOLE OF THE
     * DESIGN.** A printed line saying *"nothing enrols a campaign audience"*
     * would be a comment in output form: correct today, silently wrong the
     * morning somebody builds the entry point, and read then as a defect report
     * about a defect that no longer exists — 511's shape with a scheduler
     * behind it. **Every figure below is a count of live rows**, so the report
     * moves on its own and there is nothing in it to go stale. The argument
     * lives at `Campaigns::enrol()` and in 11520–11524, where it is dated.
     *
     * ⚠️ **THE READER IS A PERSON RUNNING THIS BY HAND, AND IT IS THE SAME
     * READER `storage:prune` AND `db:footprint` HAVE.** No scheduled command in
     * this application sends its output anywhere — every entry in
     * `routes/console.php` is `runInBackground()` and the tree contains no
     * `sendOutputTo` or `emailOutputTo` at all — so this rings nothing and
     * pages nobody. ⛔ **A bell was refused rather than forgotten**: an empty
     * sweep is the correct state for a tenant with no campaigns, which is most
     * tenants most of the time, so a threshold over it fires constantly and is
     * muted by the person carrying it — `db:footprint`'s own 8010 refusal, one
     * command over.
     *
     * ⚠️ **THE SECOND LINE IS ABOUT THE SWEPT SET AND NOT ABOUT THE PLATFORM.**
     * A paused or suspended tenant is counted on the first line and contributes
     * nothing to the second, because this command returns before establishing
     * anything about its campaigns — writing it as a platform total would be a
     * figure that quietly drops a tenant every time somebody pauses one.
     */
    private function report(int $dispatched): void
    {
        $this->info(sprintf(
            'Swept %d %s; %d skipped for a pause or a suspension.',
            $this->businessesSwept,
            str('business')->plural($this->businessesSwept),
            $this->businessesSkipped,
        ));

        $this->info(sprintf(
            'Those businesses hold %d %s, and %d of them have anybody enrolled.',
            $this->campaignsHeld,
            str('campaign')->plural($this->campaignsHeld),
            $this->businessesWithARecipient,
        ));

        $this->info($dispatched === 0
            ? 'No campaigns are waiting for a pass, so nothing was dispatched.'
            : sprintf('Dispatched %d campaign %s.', $dispatched, str('pass')->plural($dispatched)));
    }

    /**
     * Every business this user owns, through the `owner_lookup` policy.
     */
    private function sweepOwner(int $userId): int
    {
        Tenancy::setUser($userId);

        // withoutGlobalScopes because the scope calls Tenancy::idOrFail() and no
        // tenant is established yet — the same circularity ResolveTenant
        // documents. The database still restricts this to businesses owned by the
        // user just set, so it cannot widen beyond one person's own.
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

        // ⚠️ **A PAUSED OR SUSPENDED TENANT IS SKIPPED WHOLE, AND THE JOB WOULD
        // REFUSE ANYWAY.** `AutopilotJob::handle()` checks both before
        // `execute()` — but it refuses by writing a `skipped` run row, and
        // `recordSkip()` writes no `input`, so that row carries no campaign id
        // and this command's staleness query cannot see it. Left to the job, a
        // fortnight-long pause would mean a dispatch and a run row per campaign
        // per sweep for a fortnight. Refusing here is what keeps the campaign
        // genuinely dormant until somebody releases it — and the sweep is then
        // what picks it back up, which is the whole reason this command exists.
        if (app(TenantPause::class)->isCurrentTenantPaused()) {
            $this->businessesSkipped++;

            return 0;
        }

        if (app(TenantSuspension::class)->isCurrentTenantSuspended()) {
            $this->businessesSkipped++;

            return 0;
        }

        $this->businessesSwept++;
        $this->tally();

        $busy = $this->campaignsWithAPassInFlight();

        $candidates = Campaign::query()
            ->whereIn('status', [CampaignStatus::Scheduled, CampaignStatus::Running])
            // ⚠️ **CONFIRMED, ASKED HERE TOO.** The CHECK makes an unconfirmed
            // sendable status impossible and `RunCampaignJob::campaign()` asks
            // again — 398's shape is a guard whose only proof is another guard,
            // and dispatching at a campaign nobody approved is the one thing
            // CONFIRM exists to stop.
            ->whereNotNull('confirmed_at')
            // Only campaigns with something left to do. Without this, a campaign
            // whose rows are all `Refused` — every contact permanently
            // unreachable — would be dispatched every sweep forever, because
            // `closeIfFinished()` is the only thing that completes it and it
            // needs a pass to run to say so.
            ->whereIn('id', $this->campaignIdsWithOutstandingRecipients())
            // Ascending on id: the oldest campaigns drain first, and an ascending
            // order raises none of the NULLS-FIRST problems the ConventionsTest
            // lint exists for.
            ->orderBy('id')
            ->limit(self::PER_BUSINESS_LIMIT)
            ->get();

        $dispatched = 0;

        foreach ($candidates as $campaign) {
            $campaignId = (int) $campaign->getKey();

            if (in_array($campaignId, $busy, true)) {
                continue;
            }

            RunCampaignJob::dispatch($businessId, $campaign->location_id, $campaignId);

            $dispatched++;
        }

        return $dispatched;
    }

    /**
     * The two figures {@see self::report()} is derived from, taken inside the
     * tenant this sweep has just established.
     *
     * ⛔ **INSIDE `Tenancy::set()`, AND THE ATTRACTIVE OPTIMISATION IS THE ONE
     * THING THAT WOULD MAKE THIS A PERMANENTLY-ZERO REPORT.** As written these
     * are tenant-scoped Eloquent reads, so outside a tenant `TenantScope` calls
     * `Tenancy::idOrFail()` and the command **throws** — loud, and safe. ⚠️ **A
     * later lane folding the per-business loop into one statement would reach
     * for `DB::table('campaigns')->count()` to do it, and that is the shape
     * that fails silently**: both tables are FORCE row level security, so a raw
     * count from a console process with no tenant established matches **zero
     * rows and reports success** (7626) — a report answering *"0 campaigns"*
     * on every install for ever, including one where the entry point had
     * shipped and tenants were using it. **That is this slice's own subject
     * rebuilt inside the instrument meant to disclose it**, which is why these
     * two lines are here rather than beside the summary.
     *
     * ⚠️ **`exists()` RATHER THAN `count()` ON THE RECIPIENTS**, because the
     * question the report asks is *has anybody been enrolled at all* and
     * `EXISTS` stops at the first row — so this costs an empty index scan today
     * and a single row lookup on a platform with a million of them. The
     * campaign count is a real count because `campaigns` is one row per
     * campaign per tenant and the figure that separates *no campaigns* from
     * *campaigns nobody is enrolled on* is the one worth having: that second
     * state is exactly what `CampaignPacks::activate()` produces, since a pack
     * drafts campaigns and enrols nobody.
     *
     * ⚠️ **UNFILTERED BY STATUS, DELIBERATELY.** A cancelled or completed
     * campaign is still evidence that something in this platform creates one,
     * which is the question this figure is being asked; filtering to the
     * sendable statuses would make it a second, worse copy of the candidate
     * query twenty lines below.
     */
    private function tally(): void
    {
        $this->campaignsHeld += Campaign::query()->count();

        if (CampaignRecipient::query()->exists()) {
            $this->businessesWithARecipient++;
        }
    }

    /**
     * Campaigns with at least one recipient still worth attempting.
     *
     * ⚠️ **THE SAME THREE STATUSES `RunCampaignJob::batch()` SELECTS**, asked
     * through `CampaignRecipientStatus::isOutstanding()` so the two cannot
     * drift — a sweep that dispatched on a wider predicate than the runner acts
     * on would wake a worker to do nothing, once per campaign, forever.
     *
     * @return list<int>
     */
    private function campaignIdsWithOutstandingRecipients(): array
    {
        $outstanding = array_values(array_filter(
            CampaignRecipientStatus::cases(),
            static fn (CampaignRecipientStatus $status): bool => $status->isOutstanding(),
        ));

        // array_values because `Collection::all()` preserves keys and a
        // `distinct()` pluck is not guaranteed to hand back a list.
        return array_values(
            CampaignRecipient::query()
                ->whereIn('status', $outstanding)
                ->distinct()
                ->pluck('campaign_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all()
        );
    }

    /**
     * Campaigns whose chain is alive, and which must therefore be left alone.
     *
     * ⛔ **THIS IS THE ONLY THING STANDING BETWEEN THIS SWEEP AND A CHAIN THAT
     * DOUBLES EVERY PASS.** See the class docblock. `automation_runs` is read
     * rather than a new column added: it is already the exhaustive record of
     * attempts, `AutopilotJob::input()` is protected so a job can name the row it
     * acted on, and `RunCampaignJob::input()` now does.
     *
     * ⛔ **A SKIPPED PASS IS NOT A PASS IN FLIGHT, AND THAT EXCLUSION BECAME
     * LOAD-BEARING ON 2026-08-21** (6744). `AutopilotJob::recordSkip()` wrote no
     * `input` at all until 6743, so a pass stopped by the kill switch, a
     * suspension, a tenant pause or the toggle was invisible to this query by
     * accident. It now carries `campaign_id` like every other row, and without
     * this predicate the sweep would read a campaign that **never reached
     * `execute()`** as one whose chain is alive, and leave it alone for
     * {@see self::IN_FLIGHT_MINUTES}. The tenant pause is the case that makes
     * it concrete: this command's own docblock names a paused tenant's stranded
     * chain as the reason it exists, and the fix for the record would have put
     * up to half an hour between the pause lifting and the chain restarting.
     *
     * ⚠️ **`Skipped` ONLY, AND `HandedOff` IS DELIBERATELY LEFT IN.**
     * `ReanalyseReviews::exhausted()` excludes both, for a different question —
     * it is counting attempts against a ceiling, and a hand-off spent nothing.
     * The question here is whether a chain exists, `handoff()` runs real code,
     * and narrowing that arm as well is a separate judgment this slice did not
     * verify. A skip is the one status that provably returned before
     * `execute()`.
     *
     * @return list<int>
     */
    private function campaignsWithAPassInFlight(): array
    {
        $runs = AutomationRun::query()
            ->where('automation_key', 'react.campaign_pass')
            ->whereNot('status', AutomationRunStatus::Skipped)
            ->where('started_at', '>=', now()->subMinutes(self::IN_FLIGHT_MINUTES))
            ->pluck('input');

        $ids = [];

        foreach ($runs as $input) {
            if (is_array($input) && isset($input['campaign_id'])) {
                $ids[] = (int) $input['campaign_id'];
            }
        }

        return array_values(array_unique($ids));
    }
}
