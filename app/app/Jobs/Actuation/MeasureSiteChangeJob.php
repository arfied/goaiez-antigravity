<?php

declare(strict_types=1);

namespace App\Jobs\Actuation;

use App\Enums\SiteMeasurementOutcome;
use App\Services\Actuation\ChangeMeasurer;
use App\Services\Tenant\TenantPause;
use App\Services\Tenant\TenantSuspension;
use App\Support\OutboundSiteBudget;
use App\Support\QueueBackoff;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Judge one change we made to somebody else's website, and take it back off if
 * it made things worse — `29` §2 rule 32, `BUILD-PLAN` §2.11.3 slice H.
 *
 * ⚠️ **NOT AN `AutopilotJob`, ON `ProbeLocationSiteJob`'s ARGUMENT (5555).**
 * That base class is the contract for the 142 catalog automations: an
 * `automationKey()` naming a row in doc `16`, rule 44's `execute()`/`handoff()`
 * pair, and an `automation_runs` row an owner is shown. **There is no
 * provider-free variant to hand off to here.** The measurement reads our own
 * marts and, where a grant exists, Search Console — a tenant without one is
 * measured on the pixel alone by `execute()`'s own path, not by a different one
 * — and every change set it can be handed is T1 or T3, both of which have an
 * actuator. A `handoff()` could therefore only be an empty method, which is the
 * stub rule 44 exists to forbid.
 *
 * ⛔ **IT KEEPS TWO OF THAT CLASS'S GATES BY HAND**: a suspended or paused
 * tenant is skipped whole. Row 5's pause is *"all sending + actuation off"* and
 * this is actuation at both ends — a Search Console request made on their
 * behalf, and a write to their website. ⚠️ **Skipping costs nothing, and that
 * is a property of the design rather than luck**: every window is anchored on
 * `applied_at`, so the sweep re-derives the identical verdict from the identical
 * rows on the day the pause lifts.
 *
 * ⛔ **THERE IS DELIBERATELY NO ENTITLEMENT GATE, AND IT IS THE ONE GATE THAT
 * WOULD BE WRONG HERE.** `Publishing` refuses to publish for a tenant who is not
 * entitled, correctly: publishing is something they are buying. Measuring is
 * not, and reverting is the opposite — it is this platform taking its own
 * automated edit **off** a site. Refusing to do that because somebody stopped
 * paying leaves our content on a former customer's website with no code path to
 * remove it, which is 4880's Zernio grant wearing a page.
 *
 * ## Idempotent because the row is the state
 *
 * A second run of the same change finds `measured_at` already set and answers
 * {@see SiteMeasurementOutcome::NotDue} — except on the one arm that must
 * repeat, a regression whose revert did not land, where running again is the
 * whole point. So there is no claim to take and nothing a duplicate can
 * corrupt.
 */
final class MeasureSiteChangeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Three attempts with backoff: a customer's website that did not answer a
     * minute ago is the ordinary reason the revert half of this fails, and it is
     * the ordinary reason to try again. The measurement half is idempotent, so a
     * retry re-derives rather than double-counts.
     */
    public int $tries = 3;

    /**
     * Five minutes, then half an hour — each spread by ±25%.
     *
     * ⛔ **A FIXED LADDER WAS A METHOD, NOT A PROPERTY, WAITING TO HAPPEN**
     * (6267). `public array $backoff = [300, 1800]` retries every queued
     * measurement of every tenant against the same customer's website at the
     * same instant, which is exactly the burst
     * {@see OutboundSiteBudget} then has to refuse. Laravel
     * prefers a `backoff()` method over the property, and `29` §2 rule 40 and
     * `CLAUDE.md` §Engineering both say *"retried with backoff"*. **The jitter
     * is this codebase's own** — neither document names it, and it is what stops
     * the ladder from becoming the burst.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return QueueBackoff::ladder(QueueBackoff::fromSetting('queue.backoff.actuation_seconds'));
    }

    /**
     * ⚠️ **IDS RATHER THAN MODELS.** `SerializesModels` re-queries on
     * unserialize with no tenant in context, so a model property resolves to
     * nothing and the job fails for a reason that names neither the tenant nor
     * the row.
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $locationId,
        public readonly int $siteChangeId,
    ) {}

    public function handle(ChangeMeasurer $measurer, TenantPause $pause, TenantSuspension $suspension): void
    {
        Tenancy::set($this->businessId);

        if ($suspension->isCurrentTenantSuspended() || $pause->isCurrentTenantPaused()) {
            return;
        }

        $measurer->measure($this->siteChangeId, CarbonImmutable::now());
    }

    /**
     * `29` §2.4 permits the tenant in queue context and nothing about a person.
     * A location id is neither.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            'business:'.$this->businessId,
            'location:'.$this->locationId,
            'site-change:'.$this->siteChangeId,
        ];
    }
}
