<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Console\Commands\RecoverRestedNumbers;
use App\Enums\AutopilotActionType;
use App\Enums\NumberState;
use App\Services\Sms\NumberRecovery;

/**
 * Walk one tenant's rested number its next lifecycle hop — doc `51` §5.3, §11's
 * `RecoveryRelease`, row 4 slice 6 phase 4.
 *
 * ⚠️ **TENANT-OWNED NUMBERS ONLY, AND THE ASYMMETRY IS `NumberHealthRollup`'s
 * EXACTLY.** `AutopilotJob::handle()`'s whole contract — `Tenancy::set()`, the
 * kill switch, the suspension and pause checks, the run row, the activity feed
 * — presumes a resolvable tenant, and the shared Lane A pool number has none
 * (I40: *"shared pool = platform tenant"*, which names no row in `businesses`).
 * The shared number's own recovery is performed **directly** by
 * {@see RecoverRestedNumbers}, not wrapped in this job.
 *
 * ⚠️ **THE JOB EXISTS FOR THE FEED AND THE GATES, NOT FOR THE TRANSITION.**
 * `29` §2 rule 42 wants every automated action in the owner's activity feed, and
 * bringing a tenant's only sending number back into service is the other end of
 * the event `NumberHealthRollup` already files when it stops one. The durable
 * record is `number_state_changes` and the audit entry either way —
 * `NumberLifecycle` writes both — so what this adds is the sentence the owner
 * can see and the four gates in front of it.
 *
 * ⚠️ **A SUSPENDED OR PAUSED TENANT'S NUMBER SIMPLY WAITS**, which is the right
 * direction and costs nothing: neither account is sending, so a number resting
 * one more night changes no outcome, and the next nightly sweep picks it up
 * unchanged the moment the hold lifts.
 *
 * IDEMPOTENT BY CONSTRUCTION rather than by key. {@see NumberRecovery::advance()}
 * re-asks whether the number is still due, inside a transaction that locks the
 * row — so a redelivery, an overlapping sweep or a run that lands hours late
 * finds the timestamps already moved on and does nothing. An `idempotencyKey()`
 * would be wrong here for `AutopilotJob`'s own stated reason: a nightly sweep is
 * meant to run every night.
 */
final class RecoverRestedNumber extends AutopilotJob
{
    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $numberId,
        public readonly int $cooldownDays,
        public readonly int $reentryStep,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'numbers.recovery_release';
    }

    /**
     * The state this run moved the number to, or null when it turned out not to
     * be due after all.
     *
     * Set in {@see self::execute()} and read by {@see self::activityAction()},
     * which `AutopilotJob` calls only on the success path — so there is no run
     * in which this could be stale. `NumberHealthRollup` holds its quarantine
     * reason the same way, at the same point, for the same reason.
     */
    private ?NumberState $moved = null;

    /**
     * ⚠️ **ONLY ON A RUN THAT ACTUALLY MOVED SOMETHING.** The sweep dispatches
     * this job for a number it believes is due, and the job re-checks — so a
     * race, a redelivery or a late run can legitimately find nothing to do, and
     * writing a feed line for it would be 256's vacuity at the scale of a
     * sentence the owner reads (`FirstWeekUpdateSent`'s own rule). The
     * exhaustive record is `automation_runs`.
     *
     * ⚠️ **`AutomationCompleted` RATHER THAN A CASE OF ITS OWN, AND THAT IS A
     * DELIBERATE REFUSAL TO WRITE COPY** (6431). The owner-facing sentence for
     * *"your number is working normally again"* is doc 51 §8's Text Health card,
     * which is unbuilt and specifies its wording exactly; minting an enum case
     * here would put a different sentence in front of the owner first and make
     * §8's the correction rather than the copy. `AutopilotJob`'s default is
     * generic and true.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->moved === null ? null : parent::activityAction();
    }

    /**
     * ⚠️ **THE NUMBER'S ID AND THE STATE IT REACHED, AND NOTHING ELSE.** Both
     * are facts about one of our own numbers — no recipient, no message body,
     * no vendor payload — which is what makes them safe under
     * `AutopilotJob::activityMetadata()`'s rule. The E.164 is deliberately not
     * here: it is not PII, but the feed item is the wrong place to publish an
     * inventory detail the owner has no use for.
     *
     * @return array<string, mixed>
     */
    protected function activityMetadata(): array
    {
        return parent::activityMetadata() + [
            'number_id' => $this->numberId,
            'state' => $this->moved?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return parent::input() + ['number_id' => $this->numberId];
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        $this->moved = app(NumberRecovery::class)->advance(
            $this->numberId,
            $this->businessId,
            $this->cooldownDays,
            $this->reentryStep,
        );

        return [
            'number_id' => $this->numberId,
            'moved_to' => $this->moved?->value,
        ];
    }

    /**
     * ⚠️ **THE SAME WORK, BECAUSE THERE IS NO PROVIDER ON THIS PATH.** Rule 44
     * wants both paths built in the same ticket, and the honest implementation
     * here is identity: a lifecycle transition is a row in our own database and
     * a line in our own history. Nothing is asked of Infobip, of Google, or of
     * anybody else — a degraded apology would be inventing a dependency this
     * automation does not have.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->execute();
    }
}
