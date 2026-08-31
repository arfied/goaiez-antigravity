<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Console\Commands\RecoverRestedNumbers;
use App\Enums\NumberState;
use App\Models\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * THE COOLDOWN RECOVERY — doc `51` §5.3, row 4 slice 6 phase 4.
 *
 * ⛔ **THE CONTAINMENT WAS DISABLING ITSELF ONE RELEASE AT A TIME, AND THIS IS
 * THE PART THAT WAS MISSING** (3796, 3988). `NumberHealthService::evaluateQuarantine()`
 * can stop a number automatically. Until 2026-08-20 the only way back out was
 * `numbers:release-quarantine`, an Ops command — and it releases to
 * `recovering`, which the trigger deliberately never re-evaluates (3768). So
 * every number that ever left a quarantine left the automatic trigger's reach
 * **permanently**: an eager containment converting itself into a disabled one,
 * one release at a time, which is 3988 in one sentence.
 *
 * This class performs the two hops nothing performed:
 *
 *   quarantined --(`numbers.quarantine_cooldown_days` elapsed)--> recovering
 *   recovering  --(the re-entry climb elapsed)-->                 active
 *
 * ⚠️ **THE SECOND HOP IS THE ONE THAT RE-ARMS THE TRIGGER, AND IT IS THE
 * REASON THIS CLASS IS WORTH MORE THAN A TIMER.** `active` is inside
 * `NumberHealthService`'s evaluable set and `recovering` is not, so a number
 * only becomes automatically stoppable again when it reaches `active`. It
 * closes 3788's one-way door for the **Ops** release too, not only for the
 * automatic one: a number released by hand on Monday now walks back to `active`
 * by itself and can be stopped again on Friday without anybody typing anything.
 *
 * ## Doc 51 §5.3's pool-alarm hold is deliberately NOT implemented
 *
 * ⛔ **§5.3 releases a number *"only if no pool alarm (§7) is active for its
 * tenant"* — and §7 is unbuilt** (3774 lists it among the deliberate
 * omissions). Nothing in this application can raise a pool alarm, clear one, or
 * answer whether one is active. A `poolAlarmActive()` returning a hardcoded
 * `false` would read to the next reviewer as a safety condition and be a
 * guard that matches nothing — 256's vacuity **inside a containment**, which is
 * the most expensive place to put it (314–316: the paragraph explaining the
 * hazard is what stops the next reviewer looking for it).
 *
 * So the hold is absent and named rather than faked (decision 6424). **The
 * consequence is real and is not hidden**: a tenant whose whole pool sickened
 * from one bad list gets their numbers back on the cooldown, into the same
 * fire, exactly as they would have before §7 existed. Two things make that
 * survivable today and neither is §7 — dedicated number allocation gives a tenant exactly one number, so
 * "a tenant's pool" is one row and *"the numbers are not the problem"* has no
 * second number to prove itself with; and the platform-wide complaint trip
 * (2102, 2119) still halts sending regardless of what state a number is in.
 * ⚠️ **Neither is substituted for the hold here.** Gating this sweep on the
 * platform halt would be a different condition wearing §7's name: it is
 * platform-wide where §7 is per-tenant, it contains *sending* rather than
 * *resumption*, and during a halt nothing sends anyway — so it would change no
 * outcome while reading like the missing guard.
 *
 * ⚠️ **WHAT IT WOULD TAKE**, so the next lane does not have to re-derive it:
 * §7's two seeded figures (`numbers.pool_alarm_quarantine_pct`,
 * `numbers.pool_alarm_min_numbers`, neither in the manifest), a per-tenant
 * count of quarantines in seven days, somewhere durable to hold the alarm and
 * its explicit resume, and event 256. When that exists, the hold is one
 * predicate in {@see self::dueTarget()} and one clause in the reason string.
 *
 * ## Fail-safe direction
 *
 * ⚠️ **A RE-ARM LOOSENS A CONTAINMENT, SO AN UNSET FIGURE RELEASES NOTHING.**
 * A cooldown of zero or less is an unset or mis-edited row, never *"release
 * immediately"* — `TenantNumbers::returnParkedNumbersToPool()`'s rule one
 * service over, and `storage:prune`'s (4941). The refusal is here **and** in
 * {@see RecoverRestedNumbers}, and the duplication is deliberate: 398's lesson
 * is that a guard which only ever runs behind another guard is unfalsifiable,
 * so this one is driven directly by its own test.
 *
 * ⚠️ **THE SECOND HOP IS GATED BY ITS OWN FIGURE AND NOT BY THE COOLDOWN**, and
 * that asymmetry is the fail-safe direction rather than an oversight: holding a
 * number in `recovering` is *not* the safe side, because `recovering` is the
 * one sendable state the trigger cannot stop. An unset cooldown must strand a
 * number in `quarantined`, where it sends nothing; it must not strand one in
 * `recovering`, where it sends everything unwatched.
 */
final class NumberRecovery
{
    /**
     * `carrier:infobip`'s precedent — the `system:` prefix for something no
     * person in this company did. `NumberLifecycle`'s docblock argues the point.
     */
    public const string ACTOR = 'system:number-recovery';

    /**
     * How many *ramp* steps doc 51 §1's warmup curve has: `20, 50, 100, 150,
     * cap` — four climbing steps, then the steady-state cap, which is arriving
     * rather than a fifth step.
     *
     * ⛔ **NOT A REGISTRY KEY, AND THE REASON IS `STOP_NOISE_FLOOR_K`'s** (3986):
     * *"a key exists to be set, not to be admired."* `numbers.warmup_curve` is
     * in doc 51 §1 and is deliberately **not** seeded by this slice, because
     * nothing anywhere in `app/` clamps a daily send volume — seeding the four
     * cap figures would put four numbers on an Ops screen that an operator
     * could lower believing they had tightened something, and only the *length*
     * of the list would have any effect at all. That is the writerless-control
     * shape (272) dressed as a safety setting. The length lives here, where
     * moving it costs a code review, until there is a clamp to attach the caps
     * to.
     */
    private const int WARMUP_RAMP_STEPS = 4;

    public function __construct(
        private readonly NumberLifecycle $lifecycle,
    ) {}

    /**
     * Every number whose next lifecycle hop has come due.
     *
     * ⚠️ **THIS PREDICATE EXISTS TWICE — HERE IN SQL AND IN {@see self::dueTarget()}
     * IN PHP — AND BOTH ARE KEPT** (2884's rule). This one bounds what the sweep
     * enumerates; that one is re-asked inside the transaction that acts, because
     * a queued job runs minutes after the row was selected and may run twice.
     * The test drives both, with the same fixture, so they cannot drift.
     *
     * @return Collection<int, PhoneNumber>
     */
    public function due(int $cooldownDays, int $reentryStep): Collection
    {
        $climbDays = $this->climbDays($reentryStep);

        return PhoneNumber::query()
            ->where(function (Builder $query) use ($cooldownDays): void {
                if ($cooldownDays <= 0) {
                    // ⛔ A cooldown of zero is not "release immediately" — see
                    // the class docblock. `whereRaw('false')` rather than an
                    // early return of an empty collection, so the *other* arm
                    // below still runs: a recovering number must not be
                    // stranded outside the trigger by a mis-edited row that has
                    // nothing to do with it.
                    $query->whereRaw('false');

                    return;
                }

                $query->where('state', NumberState::Quarantined->value)
                    ->whereNotNull('quarantined_at')
                    ->where('quarantined_at', '<=', now()->subDays($cooldownDays));
            })
            ->orWhere(function (Builder $query) use ($climbDays): void {
                $query->where('state', NumberState::Recovering->value)
                    // ⚠️ A number that entered `recovering` before this column
                    // existed has none, and holds rather than being handed a
                    // manufactured entry time — the creating migration's
                    // no-backfill argument.
                    ->whereNotNull('recovering_at')
                    ->where('recovering_at', '<=', now()->subDays($climbDays));
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Walk one number its next hop, if it is still due when this runs.
     *
     * @param  ?int  $expectedBusinessId  the tenant the caller believes owns this
     *                                    number — null for the shared Lane A pool
     * @return ?NumberState the state it moved to, or null if it was not due
     *
     * @throws LogicException when the number belongs to a different tenant than
     *                        the caller was dispatched for
     */
    public function advance(
        int $numberId,
        ?int $expectedBusinessId,
        int $cooldownDays,
        int $reentryStep,
    ): ?NumberState {
        return DB::transaction(function () use (
            $numberId,
            $expectedBusinessId,
            $cooldownDays,
            $reentryStep,
        ): ?NumberState {
            // ⚠️ **LOCKED, BECAUSE TWO SWEEPS CAN OVERLAP AND THE SECOND HOP
            // WOULD THROW RATHER THAN NO-OP.** `recovering → recovering` has no
            // edge in the legality table, so an unlocked double run would raise
            // a LogicException out of a scheduled task on an ordinary night —
            // which is `numbers:return-parked`'s own recorded reason for
            // `withoutOverlapping`, held here in the database as well.
            $number = PhoneNumber::query()->lockForUpdate()->find($numberId);

            if ($number === null) {
                return null;
            }

            // ⚠️ **THE CROSS-TENANT REFUSAL, AND IT IS `NumberHealthRollup`'s
            // EXACTLY.** `phone_numbers` carries no global scope (the shared
            // pool row belongs to nobody, so one would hide it), which means a
            // dispatch bug could hand this job another tenant's number and every
            // guard downstream would go on being correct. A number is released
            // into the service of the tenant that owns it or of nobody; there is
            // no third answer, so a mismatch is a programming error and is loud.
            if ($number->business_id !== $expectedBusinessId) {
                throw new LogicException(
                    "Number {$numberId} belongs to business ".($number->business_id ?? 'nobody')
                    .', not '.($expectedBusinessId ?? 'nobody')
                    ."— refusing to bring another tenant's number back into service."
                );
            }

            $target = $this->dueTarget($number, $cooldownDays, $reentryStep);

            if ($target === null) {
                return null;
            }

            $this->lifecycle->transitionTo(
                $number,
                $target,
                self::ACTOR,
                $this->reasonFor($number->state, $target, $cooldownDays, $reentryStep),
            );

            return $target;
        });
    }

    /**
     * Where this number goes next, or null while it is not due.
     *
     * The whole clock, in one method, so the SQL above and the transition below
     * are reading one rule.
     */
    private function dueTarget(PhoneNumber $number, int $cooldownDays, int $reentryStep): ?NumberState
    {
        $quarantinedAt = $number->quarantined_at;
        $recoveringAt = $number->recovering_at;

        if ($number->state === NumberState::Quarantined) {
            if ($cooldownDays <= 0 || $quarantinedAt === null) {
                return null;
            }

            return $quarantinedAt->lessThanOrEqualTo(now()->subDays($cooldownDays))
                ? NumberState::Recovering
                : null;
        }

        if ($number->state === NumberState::Recovering) {
            if ($recoveringAt === null) {
                return null;
            }

            return $recoveringAt->lessThanOrEqualTo(now()->subDays($this->climbDays($reentryStep)))
                ? NumberState::Active
                : null;
        }

        return null;
    }

    /**
     * How many days a number spends in `recovering` before it is `active`.
     *
     * Doc 51 §5.3: a recovering number *"re-enters at warmup step
     * `recovery_reentry_step` (50/day) and climbs the curve"* — one step a day,
     * so what is left to climb is what is left of the ramp.
     *
     * ⛔ **THE CAPS ARE NOT ENFORCED AND THIS METHOD DOES NOT PRETEND THEY ARE.**
     * There is no warmup machinery in this application: no `warmup_day` column,
     * no daily clamp, no curve. `numbers:release-quarantine` has said so in a
     * warning to the operator since AG6 and still does. What survives of §5.3
     * without the caps is its *duration*, which is what this returns — the
     * number sends at full capacity throughout, and the honest word for the
     * window is "unwatched" rather than "warming".
     *
     * ⚠️ **CLAMPED AT BOTH ENDS, ON PURPOSE.** A step past the top of the ramp
     * would compute zero days, and a zero-day recovery is one sweep run
     * performing both hops at once — which erases the state doc 51 asks for and
     * would make the re-entry indistinguishable from `quarantined → active`, an
     * edge the lifecycle deliberately does not have. A step below 1 gets the
     * full climb. Neither clamp can strand a number: the window is always
     * between one day and the length of the ramp.
     */
    private function climbDays(int $reentryStep): int
    {
        return max(1, min(
            self::WARMUP_RAMP_STEPS,
            self::WARMUP_RAMP_STEPS - $reentryStep + 1,
        ));
    }

    /**
     * What `number_state_changes` and the audit entry will say about this hop.
     */
    private function reasonFor(NumberState $from, NumberState $to, int $cooldownDays, int $reentryStep): string
    {
        if ($to === NumberState::Recovering) {
            return "Rested {$cooldownDays} days since it was quarantined, so doc 51 §5.3's cooldown has "
                ."elapsed; re-entering at warmup step {$reentryStep}. Doc 51 also holds this release "
                .'while a pool alarm is active for the tenant; §7 is not built, so no alarm was consulted.';
        }

        return "Completed the {$this->climbDays($reentryStep)}-day re-entry climb from warmup step "
            ."{$reentryStep} without being quarantined again (doc 51 §5.3), so it is back under the "
            .'automatic health trigger, which does not evaluate a recovering number. Moved from '
            ."{$from->value}.";
    }
}
