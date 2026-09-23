<?php

declare(strict_types=1);

namespace App\Jobs\Actuation;

use App\Services\Actuation\SpeedDecider;
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
 * Run `28` §4.3's four triggers over one applied speed fix, and take it back off
 * the site if any of them fired.
 *
 * ⚠️ **NOT AN `AutopilotJob`, ON `MeasureSiteChangeJob`'s ARGUMENT (5814).**
 * That base class is the contract for the 142 catalog automations — an
 * `automationKey()` naming a row in doc `16`, rule 44's `execute()`/`handoff()`
 * pair, an `automation_runs` row an owner is shown. There is no provider-free
 * variant to hand off to: this reads our own marts, and a `handoff()` could only
 * be an empty method, which is the stub rule 44 forbids.
 *
 * ⛔ **PAUSED AND SUSPENDED ARE KEPT; ENTITLEMENT IS DELIBERATELY NOT** (5813,
 * verbatim and for the same reason). Reverting is this platform taking its own
 * automated edit **off** somebody's website, and refusing to do that because
 * they stopped paying leaves our change on a former customer's site with no code
 * path to remove it.
 *
 * ⚠️ **SKIPPING COSTS NOTHING.** Both windows are anchored on the speed row's
 * `applied_at`, so the sweep re-derives the identical verdict on the day the
 * pause lifts.
 */
final class JudgeSpeedFixJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Three attempts with backoff: a customer's website that did not answer a
     * minute ago is the ordinary reason the revert half fails, and the ordinary
     * reason to try again. The judging half is idempotent — a second run finds
     * `result` written and answers `NotDue`.
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
     * nothing and the job fails naming neither the tenant nor the row.
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $locationId,
        public readonly int $speedChangeSetId,
    ) {}

    public function handle(SpeedDecider $decider, TenantPause $pause, TenantSuspension $suspension): void
    {
        Tenancy::set($this->businessId);

        if ($suspension->isCurrentTenantSuspended() || $pause->isCurrentTenantPaused()) {
            return;
        }

        $decider->judge($this->speedChangeSetId, CarbonImmutable::now());
    }

    /**
     * `29` §2.4 permits the tenant in queue context and nothing about a person.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            'business:'.$this->businessId,
            'location:'.$this->locationId,
            'speed-change-set:'.$this->speedChangeSetId,
        ];
    }
}
