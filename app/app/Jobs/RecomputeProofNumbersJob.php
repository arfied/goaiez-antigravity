<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Business;
use App\Services\Proof\ProofNumbers;
use App\Support\QueueBackoff;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One tenant's proof numbers, worked out again (`28` §3.3).
 *
 * ⛔ **THE ENGINE HAD NO STARTER, AND THE SAFEGUARD THAT KEPT IT HONEST IS WHY
 * NOBODY NOTICED.** `ProofNumbers::recompute()` had exactly one caller in
 * `app/` — `FirstWeekPath::runDay7()`, once, at the end of a tenant's first
 * week, over `'all'` only — and nothing scheduled it. So *"This month"* was
 * three permanent zeros on every owner's Home screen from the day the screen
 * shipped: `ProofNumbers::for()` found no monthly row and returned an unsaved
 * zero row, exactly as it is designed to. Decision **907** refused to recompute
 * on render because that *"would hide a broken scheduler behind a screen that
 * always looks right"* — and it worked, in the least useful direction
 * available: **the screen told the truth and there was nobody to tell.** 1765
 * wrote the gap down twice and it stayed open.
 *
 * ⛔ **THIS DOES NOT REOPEN 907.** Nothing here touches the render path; the
 * screen still reads a stored row and still says when it was worked out, and if
 * this sweep stops it is the timestamp on Home that goes stale in public.
 *
 * ## Not an `AutopilotJob`, and the reason is 2591's
 *
 * `AdvanceDunningScheduleJob` opted out because inherited gates made a required
 * arm unreachable. The shape here is the same and the stakes are smaller, so
 * both halves are worth saying plainly.
 *
 * **The gates would be inherited rather than chosen** (2591).
 * `AutopilotJob::handle()` skips on `TenantPause` and `TenantSuspension`, and
 * neither is a statement about arithmetic. A paused tenant has stopped their
 * automations — they have not stopped receiving feedback-form submissions,
 * which arrive at a public page, and 822 leaves triage running throughout. Under
 * the base class their Home would freeze for the length of the pause and then
 * jump, which is the one screen in this product where a number appearing from
 * nowhere reads as an invented one.
 *
 * **And the run row would be a second, worse copy of a record that already
 * exists** (2133's rule, applied to a different table). `proof_numbers` carries
 * `computed_at` per tenant per period precisely so the screen can say when the
 * numbers were worked out — **this job's own output is the evidence that it
 * ran**. An `automation_runs` row beside it adds nothing and costs a great deal:
 * this sweep is hourly and unconditional, so at the base class it would write
 * twenty-four rows per tenant per day into a table nothing prunes and
 * `Livewire\Admin\AutomationRuns` renders, until the operator screen for what
 * automations did is mostly a cache refresh reporting that it refreshed a cache.
 * `AutopilotJob::activityAction()`'s own docblock names that shape — *"checked
 * something and found nothing"* — as the automation that makes a feed worse.
 *
 * ⚠️ **WHAT IS NOT INHERITED IS RE-STATED HERE RATHER THAN DROPPED**, which is
 * the half that makes the opt-out honest: tenancy is established below, the
 * platform kill switch is checked below through `AutopilotJob`'s own static
 * accessor, and the ladder below is `QueueBackoff`'s, the same jittered one
 * every job in this application retries on.
 *
 * ⚠️ **RULE 44's `execute()`/`handoff()` PAIR IS NOT BEING SKIPPED EITHER.**
 * That rule exists so the review engine runs with zero GBP API access. This job
 * reaches no provider at all — three `COUNT(*)`s over our own tables — so there
 * is no full-capability path to degrade and no reduced one to write. Its
 * behaviour with the GBP API switched off is its behaviour.
 *
 * ⚠️ **NO IDEMPOTENCY KEY, BECAUSE THE WORK IS IDEMPOTENT.**
 * `ProofNumbers::recompute()` is `firstOrNew` on (business_id, period) inside a
 * transaction, so a redelivery recomputes the same counts over the same row. A
 * key would buy nothing and would owe `claimIsSpent()` an answer for a claim
 * that protects nothing.
 */
final class RecomputeProofNumbersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The key this job answers to for the platform kill switch.
     *
     * ⚠️ **IT REUSES `AutopilotJob`'s CONFIG-ONLY SWITCH RATHER THAN ADDING A
     * SECOND ONE**, on 2598's reasoning: two kill switches is how a kill switch
     * ends up half honoured, and `killSwitchThrownFor()` is static and invites
     * exactly this use.
     */
    public const string AUTOMATION_KEY = 'proof.recompute';

    /**
     * Three attempts, the ladder every job in this application uses.
     */
    public int $tries = 3;

    /**
     * @param  list<string>  $periods  Decided by the sweep rather than by this
     *                                 job — `proof:recompute` reads the clock
     *                                 once for the whole fan-out, so a run that
     *                                 straddles midnight on the 1st does not
     *                                 give half the platform a different set.
     */
    public function __construct(
        public readonly int $businessId,
        public readonly array $periods,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return QueueBackoff::ladder(QueueBackoff::fromSetting('queue.backoff.standard_seconds'));
    }

    public function handle(ProofNumbers $proof): void
    {
        // A job dispatched from the scheduler inherits no tenant context at all
        // — `AutopilotJob`'s reasoning, restated because this does not extend
        // it.
        Tenancy::set($this->businessId);

        if (AutopilotJob::killSwitchThrownFor(self::AUTOMATION_KEY)) {
            // ⚠️ NOTHING IS LOST AND NOTHING IS HIDDEN. The stored rows keep
            // their old `computed_at`, so Home goes on saying how old its
            // numbers are and grows more emphatic about it every hour the
            // switch stays thrown. That is the failure 907 built the screen to
            // survive.
            return;
        }

        // Null when the business was deleted between the sweep and the worker,
        // which is ordinary rather than an error — `28` §9.5's deletion runs on
        // its own schedule and does not coordinate with this one.
        if (! Business::query()->find($this->businessId) instanceof Business) {
            return;
        }

        foreach ($this->periods as $period) {
            $proof->recompute($period);
        }
    }
}
