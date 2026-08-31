<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Console\Commands\RollUpNumberHealth;
use App\Enums\AutopilotActionType;
use App\Models\PhoneNumber;
use App\Services\Sms\NumberHealthService;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Recompute today's row for one tenant-owned sending number — row 4 slice 6
 * phase 2, doc `51` §4.4, §11.
 *
 * ⚠️ **TENANT-OWNED NUMBERS ONLY.** `AutopilotJob::handle()`'s whole contract —
 * `Tenancy::set()`, the kill switch, the tenant pause/suspension checks, a
 * business-scoped audit trail — presumes a resolvable tenant, and the shared
 * Lane A pool number has none (I40: "shared pool = platform tenant", which
 * names no row in `businesses`). That is the identical wall decision 1630 hit
 * for `NumberLifecycle`'s audit entry on that same number, and the answer is
 * the same shape: the shared pool number's rollup is computed **directly** by
 * {@see RollUpNumberHealth}, not wrapped in this job.
 *
 * IDEMPOTENT BY CONSTRUCTION, `RefreshOauthTokensJob`'s posture rather than a
 * claimed key: {@see NumberHealthService::recompute()} always recomputes
 * today's row from the raw counts gathered by this run rather than
 * incrementing a stored one, so running this twice for the same hour writes
 * the same numbers twice rather than double-counting anything.
 */
final class NumberHealthRollup extends AutopilotJob
{
    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $numberId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'numbers.health_rollup';
    }

    /**
     * The reason a quarantine fired on this run, or null when none did.
     *
     * Set inside {@see self::execute()} and read by {@see self::activityAction()}
     * afterwards — `AutopilotJob` calls the second only on the success path, so
     * there is no run in which this could be stale.
     */
    private ?string $quarantineReason = null;

    /**
     * ⛔ **THIS RETURNED NULL UNCONDITIONALLY AND SAID "nothing owner-visible
     * happens when a score recomputes" — TRUE IN PHASE 2, FALSE FROM PHASE 3**
     * (3791). A recompute can now stop a tenant's texting entirely, and
     * `29` §2 requires every automated action to reach the activity feed. The
     * audit half was already covered — `NumberLifecycle` files the transition —
     * and the half the owner can actually see was not.
     *
     * ⚠️ **ONLY ON A RUN THAT ACTUALLY QUARANTINED SOMETHING**, which is
     * `FirstWeekUpdateSent`'s rule at the identical point: most recomputes
     * change nothing, and writing a feed item every hour would be 256's vacuity
     * at the scale of a sentence the owner reads. The exhaustive record is still
     * `automation_runs`.
     *
     * ⚠️ **A QUARANTINE *WITHHELD* FILES NOTHING HERE EITHER, AND THAT IS
     * DELIBERATE** (10080). {@see NumberHealthService::stopWindow()} declines
     * the STOP trigger outright when yesterday's rollup row was never written,
     * and announces the refusal on the operator's channel rather than the
     * owner's: the owner's texting is **unaffected**, there is no action they
     * could take, and every reason the row is missing — a dead queue, a thrown
     * kill switch, their own pause, a failed job — is somebody else's to fix.
     * *"Something needs your attention"* would be false.
     *
     * ⚠️ **`OwnerActionNeeded` RATHER THAN A NEW CASE.** Its own docblock covers
     * exactly this shape — *"a review nobody could moderate wants nothing at all
     * from the owner except that they know"* — and *"Something needs your
     * attention"* is true here in a way no existing sentence about automation
     * completing is. Releasing the number is an Ops command, so there is no
     * action to name that the owner could take, and naming one would be worse
     * jargon than naming none (`29` §2 rule 47).
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->quarantineReason === null ? null : AutopilotActionType::OwnerActionNeeded;
    }

    /**
     * ⚠️ **THE REASON STRING AND THE NUMBER'S ID, AND NOTHING ELSE.** Both are
     * rates and counts about our own number — no recipient, no message body, no
     * vendor payload — which is what makes them safe under
     * `AutopilotJob::activityMetadata()`'s rule.
     *
     * ⚠️ **UNCONDITIONAL, BECAUSE ITS ONLY CALLER IS ALREADY GATED.**
     * `AutopilotJob::recordActivity()` reads {@see self::activityAction()}
     * first and returns when that is null, so this is never reached on a run
     * with no quarantine behind it. A `$this->quarantineReason === null` branch
     * here would be a guard nothing could drive — 256's vacuity one method over
     * from the gate that makes it vacuous.
     *
     * @return array<string, mixed>
     */
    protected function activityMetadata(): array
    {
        return parent::activityMetadata() + [
            'number_id' => $this->numberId,
            'reason' => $this->quarantineReason,
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
     *
     * @throws LogicException when the number does not belong to this job's
     *                        tenant — a dispatch bug this job refuses to score
     *                        through rather than silently mixing tenants'
     *                        traffic into one number's row
     */
    protected function execute(): array
    {
        $number = PhoneNumber::query()->findOrFail($this->numberId);

        if ($number->business_id !== $this->businessId) {
            throw new LogicException(
                "Number {$this->numberId} belongs to business {$number->business_id}, not "
                ."{$this->businessId} — refusing to score another tenant's number."
            );
        }

        $health = app(NumberHealthService::class);

        $now = CarbonImmutable::now();
        $todayStart = $now->startOfDay();
        $rollingStart = $now->subDay();

        $today = $health->outreachCounts($this->numberId, $todayStart, $now)
            ->add($health->inboundCounts($number->e164, $todayStart, $now));

        $rolling = $health->outreachCounts($this->numberId, $rollingStart, $now)
            ->add($health->inboundCounts($number->e164, $rollingStart, $now));

        $this->quarantineReason = $health->recompute($number, $today, $rolling);

        return array_filter([
            'sends_today' => $today->sends,
            'health_score' => $number->fresh()?->health_score,
            'quarantined' => $this->quarantineReason,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * There is no handoff for this one, and saying so is the honest answer —
     * `RefreshOauthTokensJob`'s reasoning, reused: this is not part of the
     * review engine `29` §2 rule 44 requires to run with zero GBP access, it is
     * plumbing that scores our own SMS traffic and never calls Google at all.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return ['skipped' => 'not a GBP-dependent automation'];
    }
}
