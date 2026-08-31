<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\NumberRole;
use App\Enums\NumberState;
use App\Models\NumberStateChange;
use App\Models\PhoneNumber;
use App\Services\AuditService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * The only thing that writes a sending number's state — row 4 slice 6 phase 1,
 * doc 51 §2.4.
 *
 * ⚠️ **ONE WRITER, BECAUSE THE ALTERNATIVE IS A STATE WITH NO HISTORY.** A
 * `$number->state = NumberState::Active` anywhere else is two defects at once:
 * it skips {@see NumberState::canTransitionTo()}, so a quarantined number can be
 * marched straight back to `active` without the cooldown the state exists to
 * impose; and it leaves `number_state_changes` with no row, so the table that is
 * supposed to answer *"why is this number quarantined"* answers nothing. A
 * chokepoint lint in `MessagingTest` confines {@see PhoneNumber} to four files
 * and {@see NumberStateChange} to this one.
 *
 * ⚠️ **AN ILLEGAL TRANSITION THROWS AND IS NOT A REFUSAL.** Refusals in this
 * domain are silent and return null — `PlatformTexter` does it, and
 * `ReviewInviteSender` does it — because they are operational states somebody
 * chose. An illegal transition is neither: nothing in the product can ask for
 * `released → active`, so a request for one is a programming error and has to be
 * loud, in the way an empty SMS body already is.
 *
 * ## The audit entry is written when it can be, and said when it cannot
 *
 * `29` §2 rule 42 wants every sensitive action in the append-only audit log, and
 * `AuditService::record()` opens with `Tenancy::idOrFail()` because `audit_log`
 * is tenant-owned and RLS-`FORCE`d. **The shared Lane A number belongs to no
 * tenant**, so a transition on it has no tenant to file an entry against — the
 * same fact that shaped {@see InboundMessages}, which records its unhonourable
 * sender in the log for the identical reason and says so rather than claiming to
 * be audited.
 *
 * So: `number_state_changes` **always** gets the row, and it is the durable
 * record. The audit entry is written **in addition**, and only when the number
 * names a business *and* that business is the resolved tenant. Filing it
 * whenever any tenant happens to be resolved would attribute one tenant's audit
 * log an entry about another tenant's number — or about a platform number nobody
 * owns — which is worse than not writing one, because an audit entry is read as
 * a statement about the tenant it sits under.
 *
 * ⚠️ **THE ACTOR IS A STRING AND FOLLOWS `carrier:infobip`'s PRECEDENT** — the
 * `system:` prefix for something no person in this company did. `AuditService`'s
 * own docblock takes the same position: automation is a first-class actor here,
 * and a nullable user id would model *"nobody did this"*, which is never true.
 */
final class NumberLifecycle
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Bring a number into the inventory, in a stated initial state.
     *
     * ⚠️ **THE INITIAL STATE IS A REQUIRED ARGUMENT AND HAS NO DEFAULT, ON
     * PURPOSE.** Doc 51 §2.4 has a number born `provisioning` and walking the
     * curve, which is right for a number this application buys — and the one
     * number this platform has today was registered, warmed and put into service
     * *outside* this application, months before the table existed. Defaulting to
     * `provisioning` would make the bootstrap row unsendable and stop every text
     * silently; defaulting to `active` would let a future purchase flow skip
     * warmup by omission. A caller has to say which it means.
     *
     * The first history row carries `from_state = null`, which is what that
     * column is nullable for: a creation is not a transition, and inventing a
     * prior state for it would be inventing a fact.
     *
     * ⛔ **AND IT REFUSES A SECOND LIVE NUMBER FOR A TENANT, WHICH IT DID NOT
     * UNTIL DECISION 4821.** Dedicated number allocation is one number per tenant and
     * {@see TenantNumbers::assign()} has enforced it since the day it was
     * written — but `assign()` is not the only door into this table, and this
     * one is the *other* door: public, taking a `businessId`, and asking only
     * I40's role-versus-tenant question. A caller reaching straight past
     * `assign()` could give a tenant a second number and every guard downstream
     * would go on being correct about a schema that had stopped being true. The
     * only caller that passes a business today, {@see TenantNumbers::adoptOwnNumber()},
     * is safe because it calls `releaseFromTenant()` first — its own comment says
     * *"Done before the insert so that the one-number-per-tenant rule is never
     * briefly false"* — and that is an **ordering inside one method** rather than
     * an invariant. This is what makes it an invariant.
     *
     * ⚠️ **AND IT IS A BILLING CONTAINMENT AS MUCH AS A ROUTING ONE** (4820).
     * `plan.base.additional_phone_number.monthly_cents` is seeded at 1,000¢ —
     * $10 a month, a subscription line and never a credit (3305) — and **nothing
     * in `app/` reads it**. So a second number is not merely ambiguous for the
     * reverse lookup, it is *free*: there is no allowance to check, no count to
     * bill and nothing anywhere that would notice. The two missing halves cancel
     * out only while neither exists, which is 4689(c)'s warning, and this guard
     * is what keeps them cancelling.
     *
     * ⚠️ **THE PREDICATE IS {@see TenantNumbers::forBusiness()}'s, WRITTEN OUT
     * RATHER THAN CALLED.** That class is constructed *with* this one, so calling
     * back into it would be a cycle; the query is four lines and the two must
     * agree, so the test beside it drives both doors with the same fixture rather
     * than trusting that they still do.
     *
     * @throws LogicException when the role and the tenant disagree — the same
     *                        rule the table's CHECK enforces, raised here so the
     *                        message names the rule rather than the constraint —
     *                        or when the tenant already holds a live number
     */
    public function record(
        string $e164,
        NumberRole $role,
        NumberState $state,
        string $actor,
        ?string $reason = null,
        ?int $businessId = null,
        ?int $locationId = null,
    ): PhoneNumber {
        // I40, in the language before the database: a shared-pool number belongs
        // to nobody and everything else belongs to exactly one tenant. The CHECK
        // says the same thing and stays, because this method is not the only way
        // a row could ever be inserted; what this adds is a message that names
        // the invariant instead of quoting a constraint name.
        if (($role === NumberRole::SharedPool) !== ($businessId === null)) {
            throw new LogicException(
                'A shared-pool number belongs to no tenant and every other number belongs to exactly '
                .'one. Role '.$role->value.' with '.($businessId === null ? 'no business' : 'a business')
                .' is neither.'
            );
        }

        if ($businessId !== null && $this->liveNumberFor($businessId) !== null) {
            throw new LogicException(
                "Business {$businessId} already holds a live number, and dedicated number allocation is one number per tenant. "
                .'A second one is ambiguous for every read that has to pick one — forBusiness(), '
                .'displayNumberFor() and the missed-call text-back — and it is also free: '
                .'the extra-number SKU is seeded in the registry and nothing in app/ reads it (4820). '
                .'Release the one they hold first, or build the charge before the number.'
            );
        }

        return DB::transaction(function () use (
            $e164,
            $role,
            $state,
            $actor,
            $reason,
            $businessId,
            $locationId,
        ): PhoneNumber {
            $number = PhoneNumber::query()->create([
                'business_id' => $businessId,
                'location_id' => $locationId,
                'e164' => $e164,
                'role' => $role,
                'state' => $state,
                'state_reason' => $reason,
                // The timestamps a state owns, spelled out here rather than
                // shared with `transitionTo()`: a creation has no prior value to
                // preserve, which is the one thing a transition does have to
                // think about. Two short rules that agree are safer than one
                // rule with a flag.
                'quarantined_at' => $state === NumberState::Quarantined ? now() : null,
                'recovering_at' => $state === NumberState::Recovering ? now() : null,
                'retired_at' => $state === NumberState::Retired ? now() : null,
                'released_at' => $state === NumberState::Released ? now() : null,
                // ⚠️ `lane` IS DELIBERATELY UNSET AND NOT A PARAMETER. Which
                // lane the registered 10DLC brand serves is unresolved
                // (`BUILD-PLAN` §2.10.5, 1563), and a lane written here would
                // answer that open question by accident. See the creating
                // migration.
            ]);

            $this->file($number, null, $state, $actor, $reason);

            return $number;
        });
    }

    /**
     * Move a number from where it is to where it is going, or refuse loudly.
     *
     * @throws LogicException when the lifecycle has no edge for this move
     */
    public function transitionTo(
        PhoneNumber $number,
        NumberState $to,
        string $actor,
        ?string $reason = null,
    ): PhoneNumber {
        $from = $number->state;

        if (! $from->canTransitionTo($to)) {
            throw new LogicException(
                "A number cannot move from {$from->value} to {$to->value}. The lifecycle is one table "
                .'in App\Enums\NumberState; if this move is genuinely legal, the edge belongs there '
                .'rather than at this call site.'
            );
        }

        return DB::transaction(function () use ($number, $from, $to, $actor, $reason): PhoneNumber {
            $number->state = $to;
            $number->state_reason = $reason;

            // ⚠️ **`quarantined_at` IS CLEARED ON THE WAY OUT AND THE OTHER TWO
            // ARE NOT.** A number that has recovered is not quarantined, and a
            // stale timestamp beside an `active` state is read by the next
            // person as *"this is quarantined right now"* — they check the
            // column precisely because it is the one that sounds like an answer.
            // `retired_at` and `released_at` are terminal, so there is no way
            // out to clear them on, and a released number was genuinely retired
            // first.
            $number->quarantined_at = $to === NumberState::Quarantined ? now() : null;

            // ⚠️ **THE SAME RULE, FOR THE STATE THAT NOW HAS A CLOCK ON IT**
            // (doc 51 §5.3, 6423). `recovering_at` is what
            // `NumberRecovery::advance()` measures the climb from, so it is set
            // on the way in and cleared on every way out — a number that has
            // reached `active` is not recovering, and a stale timestamp beside
            // it would make the next sweep read a warmed number as still
            // climbing.
            $number->recovering_at = $to === NumberState::Recovering ? now() : null;

            // ⚠️ NEITHER ARM HAS A CALLER IN THIS SLICE — the owner-click retire
            // flow and the park-then-release sweep are doc 51 §5.4–§5.5 and
            // phase 4. That is a writer waiting for a caller, which is the right
            // way round; a column waiting for a writer is the shape `CLAUDE.md`
            // warns about, and it is what these two would be if they were left
            // out.
            if ($to === NumberState::Retired) {
                $number->retired_at = now();
            }

            if ($to === NumberState::Released) {
                $number->released_at = now();
            }

            $number->save();

            $this->file($number, $from, $to, $actor, $reason);

            return $number;
        });
    }

    /**
     * Append the history row, and the audit entry when there is a tenant to file
     * it under.
     */
    private function file(
        PhoneNumber $number,
        ?NumberState $from,
        NumberState $to,
        string $actor,
        ?string $reason,
    ): void {
        NumberStateChange::query()->create([
            'number_id' => $number->id,
            'from_state' => $from,
            'to_state' => $to,
            'actor' => $actor,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        $tenant = Tenancy::id();

        if ($number->business_id === null || $tenant === null || $tenant !== $number->business_id) {
            // ⚠️ THE LOG RATHER THAN `audit_log`, AND NOT BY PREFERENCE — see
            // the class docblock. The number itself is safe to name here: it is
            // one of ours, not a customer's, which is the whole argument the
            // creating migration makes for storing it in plain text.
            Log::info('A sending number changed state outside any tenant.', [
                'number_id' => $number->id,
                'e164' => $number->e164,
                'from' => $from?->value,
                'to' => $to->value,
                'actor' => $actor,
                'reason' => $reason,
            ]);

            return;
        }

        $this->audit->record('sms.number_state_changed', $actor, $number, [
            'from' => $from?->value,
            'to' => $to->value,
            'reason' => $reason,
        ]);
    }

    /**
     * A number this business still holds, in the sense every other read means.
     *
     * ⚠️ **`released` AND `retired` ARE OUT, WHICH IS `TenantNumbers::forBusiness()`
     * EXACTLY.** A released number has gone back to the carrier and a retired one
     * is parked with its `business_id` already nulled, so counting either would
     * refuse a tenant a replacement for a number they no longer have — the one
     * direction this guard must not fail in. A **quarantined** number is in,
     * because a tenant whose number is sitting out a cooldown still holds it and
     * gets it back.
     *
     * ⚠️ **AND IT IS NOT `whereNull('business_id')`'s MIRROR.** The row that
     * matters is the one an operator would have to release; the CHECK that makes
     * `role` and `business_id` equivalent (I40) says nothing about how many rows
     * one business may have.
     */
    private function liveNumberFor(int $businessId): ?PhoneNumber
    {
        return PhoneNumber::query()
            ->where('business_id', $businessId)
            ->whereNotIn('state', [NumberState::Released->value, NumberState::Retired->value])
            ->orderBy('id')
            ->first();
    }
}
