<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutopilotActionType;
use App\Models\Business;
use App\Services\Trust\FirstWeekPath;

/**
 * One tick of one tenant's First 7-Day Results Path (`28` §3.2, row 5).
 *
 * ⚠️ **BUSINESS-SCOPED, NOT LOCATION-SCOPED.** `first_week_runs.business_id` is
 * `UNIQUE` — one row per tenant, not per location — so this job is dispatched
 * with `locationId` null, matching how `AutomationRun.location_id` was made
 * nullable for exactly "business-level automations [with] no single location"
 * (that migration's own docblock).
 *
 * NO SEPARATE `handoff()` WORK, AND — AS `SendReviewInviteJob`'s OWN DOCBLOCK
 * SAYS OF ITSELF — THE REASON IS NOT "IT DOES NOT NEED ONE". This automation
 * never calls the Google Business Profile API at all: the audit data it reads
 * was cached at signup by a class that ran once, long before this job exists;
 * the Google-review win check reads rows a *different* automation
 * (`SyncGoogleReviewsJob`) already wrote; and every send goes through
 * `PlatformMailer`. There is no reduced-capability version to write, so both
 * paths call the same method — a demonstration of "the whole review engine
 * runs with zero GBP API access" rather than an exception to it.
 */
final class AdvanceFirstWeekPathJob extends AutopilotJob
{
    /**
     * Set by `run()`, and the only thing `activityAction()` reads — see that
     * method for why a fixed, unconditional action would be wrong here.
     */
    private bool $notified = false;

    public function automationKey(): string
    {
        return 'trust.first_week_path';
    }

    /**
     * One claim per business per calendar day. `FirstWeekPath::advance()` is
     * itself safe to call more than once — not because each step re-checks
     * itself (three of the four do not, 1950) but because each one commits
     * `step`, `win_type` or `completed_at` *before* its email leaves. The key
     * still matters on top of that: without it, a scheduler that fires twice in
     * one day (a restart, an overlapping run despite `withoutOverlapping()`)
     * would open two run rows for one day's work, which reads as two attempts
     * at something that only happened once.
     */
    protected function idempotencyKey(): string
    {
        return 'first-week-path:'.$this->businessId.':'.now()->toDateString();
    }

    /**
     * Always `true` — declared explicitly because the `ArchitectureTest` lint
     * that requires it exists precisely so this is never inherited by
     * silence, the way it was on `SendReviewInviteJob`'s first version.
     *
     * ⚠️ **UNCONDITIONALLY TRUE IS CORRECT HERE, NOT MERELY THE SAFE DEFAULT**
     * — `SendReviewInviteJob` tracks a real flag because most of its calls are
     * gate refusals that cost nothing to re-decide. This job's whole subject
     * is different: every step in `FirstWeekPath::advance()` persists `step`,
     * `win_type` or `completed_at` **before** it enqueues its email (1950), so
     * a throw mid-catch-up leaves the row at or past the work that actually
     * went out, and a later retry re-reads that state and continues rather than
     * starting the day over.
     *
     * ⚠️ **WHAT THE CLAIM BUYS IS NARROWER THAN THIS DOCBLOCK USED TO SAY, AND
     * THE NARROWING IS THE POINT** (1950). The key is `first-week-path:{id}:
     * {Y-m-d}` — per calendar day — so it can only ever stop the *same day's*
     * retry ladder turning one message into three. It says nothing whatsoever
     * about tomorrow's tick, which arrives under a different key. Only the
     * commit-before-send ordering stops a later day re-sending what already
     * went out, and 1884 asserted otherwise on the strength of a per-step check
     * that three of the four steps do not perform.
     */
    protected function claimIsSpent(): bool
    {
        return true;
    }

    /**
     * Only when this tick actually emailed the owner — see the property's own
     * docblock and `FirstWeekPath::advance()`'s `notified` key.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->notified ? AutopilotActionType::FirstWeekUpdateSent : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        return $this->tick();
    }

    /**
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->tick();
    }

    /**
     * @return array<string, mixed>
     */
    private function tick(): array
    {
        $business = Business::query()->find($this->businessId);

        if (! $business instanceof Business) {
            return ['skipped' => 'business_missing'];
        }

        $path = app(FirstWeekPath::class);
        $run = $path->for($business);

        // No row means the wizard has never completed for this tenant —
        // dispatched anyway, on `ReinviteDeferredReviews`' precedent of
        // enumerating every business through its owner rather than a second
        // tenant-scoped query to filter the candidate list first.
        if ($run === null) {
            return ['skipped' => 'not_started'];
        }

        $output = $path->advance($run, $business);

        $this->notified = ($output['notified'] ?? false) === true;

        return $output;
    }
}
