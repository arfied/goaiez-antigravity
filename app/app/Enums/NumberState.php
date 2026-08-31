<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Sms\NumberLifecycle;
use App\Services\Sms\NumberSelector;

/**
 * The one lifecycle a `phone_numbers` row moves through (doc 51 §2.4).
 *
 * One enum, one place — every transition goes through
 * {@see NumberLifecycle::transitionTo()}, which is the only
 * writer of `phone_numbers.state` and appends the matching
 * `number_state_changes` row.
 *
 * ⚠️ **THE HISTORY MODEL IS NAMED IN PROSE AND NOT IMPORTED, DELIBERATELY.** Pint
 * promotes a `{@see}` into a real `use` statement, which is indistinguishable
 * from an import that intends to query something — and the `MessagingTest`
 * chokepoint that confines `NumberStateChange` to its one writer reads exactly
 * that. `SentText`'s footer records the same trap from the other side.
 *
 *   provisioning → registering → warming → active
 *                                     ↘        ↕ (score-driven)
 *                                      degraded
 *                                           ↓ (auto-trigger, §5)
 *                                      quarantined → recovering → active
 *                                           ↓ (2× in 30 d → recommend, not built here)
 *                                        retired → released
 *
 * ⚠️ **`retired` HAS A CALLER AS OF 2026-08-12 AND `released` STILL DOES NOT.**
 * The park half of doc 51 §5.5 is built — `TenantNumbers::releaseFromTenant()`
 * retires a departed tenant's number and `numbers:return-parked` walks it back to
 * `provisioning` ninety days later (2686). What is still unbuilt is the
 * owner-click retire/replace flow of §5.4 and the release-to-carrier hop:
 * **nothing in `app/` ever transitions a number to `released`**, and that is
 * recorded rather than hidden.
 *
 * ⚠️ **`degraded` IS THE SECOND OF THAT SHAPE AND IT IS STRONGER THAN THE
 * FIRST — CHECKED 2026-08-23 (8690).** It is a legal destination from `active`
 * and from `quarantined` (see the transition table below), it is in the
 * `maySend()` set, it is amber on the Ops board — and **nothing walks a row
 * there. Not a caller, not a seeder, not a factory.** Every mention of this
 * case in `app/`, `tests/`, `database/`, `routes/` and `resources/` is a read:
 * the membership tables here, one read-side list in `NumberHealthService`, and
 * one permitted-states array in `NumberInventoryTest`. That is deliberate and
 * on the record (3774) — §5.1's transition is phase 3/4 of row 4 slice 6,
 * because the only thing that would treat a degraded number differently is
 * §6.2's reduced rotation weight, which is phase 4. **A `Degraded` writer
 * shipped before that weight would be a state a number can enter and nothing
 * behaves differently about**, which is `CLAUDE.md`'s writerless-control shape
 * with the sign flipped.
 *
 * ⚠️ **SO A LINT DRIVEN OFF THIS ENUM CAN PASS PERFECTLY AGAINST A CASE
 * NOTHING WRITES**, which is exactly what happened one file over: the
 * `MessagingTest` chokepoint arm that proves `numbers.degraded_below_score` is
 * really read was satisfied by a docblock until 2026-08-23. **Do not read this
 * case's presence in a transition table as evidence that the transition
 * happens.**
 */
enum NumberState: string
{
    case Provisioning = 'provisioning';
    case Registering = 'registering';
    case Warming = 'warming';
    case Active = 'active';
    case Degraded = 'degraded';
    case Quarantined = 'quarantined';
    case Recovering = 'recovering';
    case Retired = 'retired';
    case Released = 'released';

    /**
     * What this state means, in words an operator can send to a carrier —
     * wave 40 lane C, decision 10880.
     *
     * ⚠️ **IT LIVES HERE RATHER THAN ON THE SCREEN THAT RENDERS IT, AND THE
     * REASON IS A LINT.** `MessagingTest`'s *"one quarantine path"* forbids any
     * file outside three named ones from writing `NumberState::Quarantined` —
     * and that lint's own docblock records that this file is deliberately NOT
     * on its allowlist, because the enum spells its own case `self::`. So a
     * `match` over every case cannot be written in a Livewire component at all;
     * the meaning of a state belongs to the state, which is `SignalState::
     * label()`'s precedent one enum over.
     *
     * ⚠️ **A `match` WITH NO DEFAULT.** A tenth case must be given a sentence
     * rather than inheriting a plausible wrong one.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::Provisioning => 'Being set up. Nothing has been sent from it.',
            self::Registering => 'Waiting on carrier registration. Nothing has been sent from it.',
            self::Warming => 'Warming up. Sending, at a deliberately low volume.',
            self::Active => 'In service. Messages are sent from it.',
            self::Degraded => 'In service, and its delivery health has fallen.',
            self::Quarantined => 'Held out of service. Nothing is being sent from it.',
            self::Recovering => 'Coming back into service after being held.',
            self::Retired => 'Retired. Nothing is sent from it any more.',
            self::Released => 'Given back to the carrier. It is no longer ours.',
        };
    }

    /**
     * Whether a number in this state may originate a send at all — I38: the
     * type-level refusal, enforced by {@see NumberSelector}
     * never returning a row outside this set.
     */
    public function maySend(): bool
    {
        return match ($this) {
            self::Warming, self::Active, self::Degraded, self::Recovering => true,
            self::Provisioning, self::Registering, self::Quarantined,
            self::Retired, self::Released => false,
        };
    }

    /**
     * Whether a number in this state may carry a **carrier-mandated compliance
     * reply** — a STOP confirmation or a HELP answer, and nothing else.
     *
     * ⛔ **THIS IS DELIBERATELY WIDER THAN {@see self::maySend()} AND THE GAP IS
     * THE WHOLE POINT** (AG6 fix wave). 2099 and 2125 make STOP and HELP
     * *unconditional*: a carrier tests HELP on the registered campaign, and a
     * person who has just texted STOP has to be told it worked. Before this
     * method existed both replies went through `maySend()`, so the first
     * auto-quarantine of the shared Lane A number would have taken the STOP
     * confirmation and the HELP answer down with it — **platform-wide, silently,
     * and precisely when the carrier is already looking at us**. An
     * auto-quarantine is not an operator's decision to make knowingly, which is
     * the test `ComplianceReplies` applies to everything it defers to.
     *
     * ⚠️ **`quarantined` IS ADMITTED; `retired` AND `released` ARE NOT.** A
     * retired number is being parked or handed back and a released one is
     * somebody else's — answering from either would send a text from a number we
     * no longer speak for. A quarantined number is still ours and still
     * registered; resting it stops *outreach*, which is what a quarantine is
     * for.
     *
     * ⚠️ **AND NEITHER IS `provisioning` OR `registering`.** A number that has
     * never been in service has no carrier registration behind it, so a reply
     * from it would fail at the vendor at best and be unregistered 10DLC traffic
     * at worst. Silence from a number that cannot deliver is what we would get
     * anyway; the difference is that this way we do not originate a violation to
     * find out.
     */
    public function mayAnswerCompliance(): bool
    {
        return match ($this) {
            self::Warming, self::Active, self::Degraded,
            self::Recovering, self::Quarantined => true,
            self::Provisioning, self::Registering,
            self::Retired, self::Released => false,
        };
    }

    /**
     * The states {@see self::canTransitionTo()} may be reached from, keyed by
     * destination. The single legality table for every transition this
     * application performs or will perform — doc 51's diagram, expressed as
     * data so `NumberLifecycle` has one place to check rather than a scattered
     * set of `if` statements.
     *
     * @return array<string, list<self>>
     */
    private static function edges(): array
    {
        return [
            self::Registering->value => [self::Provisioning],
            self::Warming->value => [self::Registering],
            self::Active->value => [self::Warming, self::Degraded, self::Recovering],
            self::Degraded->value => [self::Active],
            self::Quarantined->value => [self::Warming, self::Active, self::Degraded, self::Recovering],
            self::Recovering->value => [self::Quarantined],
            // ⛔ **THE THREE PRE-SERVICE STATES WERE MISSING AND THE PARK COULD
            // NOT BE ENTERED FROM THE ONE STATE TENANT NUMBERS ACTUALLY SIT IN**
            // (2880). `TenantNumbers::claimForTenant()` deliberately stops at
            // `Warming` — *"Walking to `Active` would assert a warm-up that has
            // not happened"* — and nothing in `app/` walks a tenant number on to
            // `Active`, so **every assigned number in this system is `warming`**.
            // Retiring one on tenant deletion threw `LogicException` from the
            // legality table, which is the table doing its job and pointing at
            // itself: *"if this move is genuinely legal, the edge belongs
            // here"*. `provisioning` and `registering` ride along for the same
            // reason one hop earlier — a tenant deleted the same afternoon they
            // signed up is the ordinary way to reach them.
            self::Retired->value => [
                self::Provisioning, self::Registering, self::Warming,
                self::Active, self::Degraded, self::Recovering, self::Quarantined,
            ],
            self::Released->value => [self::Retired],

            // ⚠️ **THE PARK ENDS BY COMING BACK, WHICH IS THE OWNER'S MODEL AND
            // NOT DOC 51'S** (2686, 2881). §5.5 parks a retired number for
            // `release_park_days` and then *"release[s] back to Infobip"* — the
            // number leaves. 2686 rules that it **returns to our own assignable
            // pool** instead, and 90 days is chosen so it is cold before
            // anybody else holds it. `provisioning` is the shape
            // `TenantNumbers::freeFromPool()` looks for, so this edge is
            // literally what "returns to the pool" means here. ⛔ Doc 51's
            // release-to-carrier path is NOT removed — `retired → released`
            // stands above, unbuilt, for the day a number really does go back.
            self::Provisioning->value => [self::Retired],
        ];
    }

    /**
     * Whether the lifecycle permits moving from this state to `$to`.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($this, self::edges()[$to->value] ?? [], true);
    }
}
