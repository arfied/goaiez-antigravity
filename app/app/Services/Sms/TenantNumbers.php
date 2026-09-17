<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\MessagingLane;
use App\Enums\NumberRole;
use App\Enums\NumberState;
use App\Enums\OutreachChannel;
use App\Exceptions\NumberPoolExhausted;
use App\Models\PhoneNumber;
use App\Services\TenantProvisioner;
use App\Support\Identifier;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * One Infobip number per tenant, and the answer to "whose number is this" —
 * dedicated number allocation.
 *
 * *"One Infobip number per tenant (voice + SMS + MMS on the same number — voice call forwarding
 * requires it), provisioned from the platform pool under the GOAIEZ 10DLC
 * brand/campaign."*
 *
 * ## The reverse lookup is the load-bearing half
 *
 * Provisioning is the obvious part. {@see self::tenantFor()} is the part three
 * other features are blocked on, because **every inbound event arrives with no
 * tenant**: a delivery receipt, an inbound SMS, a STOP, a HELP, a voice webhook.
 * `outreach_messages` solves it for delivery receipts by sending the business id
 * out as `callbackData` and reading it back — but nothing hands a *caller* or a
 * *texter* an identifier of ours to quote. What they have is the number they
 * dialled or texted, and dedicated number allocation is what makes that number identify exactly one
 * tenant.
 *
 * ⛔ **THIS IS WHY THE HELP REPLY COULD NOT BE BUILT BEFORE NOW** (2125).
 * `InboundMessages` has parsed HELP since row 4 slice 2 and deliberately did not
 * answer it, and its own comment says why: *"the reply would have to name the
 * tenant — which is the thing this path cannot resolve."* It can now.
 *
 * ## Why the lookup is a query and never an inference
 *
 * ⚠️ **NOTHING HERE READS THE MESSAGE BODY, THE AREA CODE, OR THE SENDER.** A
 * tenant inferred from message content is a tenant an attacker chooses by
 * writing a sentence — and the thing being decided is which business's customer
 * list a STOP suppresses against, and whose name goes in a HELP reply. It is a
 * lookup on an indexed column against a row an operator provisioned, or it is
 * nothing.
 *
 * ## The number is not tenant-scoped and that is deliberate
 *
 * {@see PhoneNumber} sits on `TenancyTest`'s scope allowlist — it is
 * `impersonation_sessions`' shape (562): **it is what establishes the tenant for
 * a request whose reader has none**, so it cannot itself be filtered by one. The
 * application layer is the whole boundary here, which is precisely why this
 * class is small, has one query in it, and is the only place that query lives.
 *
 * ⚠️ **AND WHY `tenantFor()` RETURNS AN ID RATHER THAN A MODEL OR A SCOPE.**
 * Handing back a tenant-scoped object from a lookup that ran *outside* the scope
 * is how an unscoped read leaks past the boundary. The caller takes the id and
 * opens `Tenancy::actingAs()` with it, which is the one sanctioned way in.
 */
final class TenantNumbers
{
    /** Who the record names for an assignment nobody in this company clicked. */
    public const string ACTOR = 'system:number-assignment';

    public function __construct(
        private readonly NumberLifecycle $lifecycle,
    ) {}

    /**
     * Put a purchased number into the assignable pool.
     *
     * ⚠️ **AN INVENTORY NUMBER IS `shared_pool` WITH NO BUSINESS, BECAUSE THE
     * TABLE'S OWN CHECK LEAVES NO OTHER SHAPE.**
     * `phone_numbers_shared_pool_has_no_business` (I40) says a `shared_pool` row
     * belongs to nobody and every other row belongs to exactly one tenant — so a
     * number waiting to be given away cannot be `primary` yet. That is why
     * {@see self::assign()} *converts* a row rather than creating one.
     *
     * ⛔ **AND `provisioning` IS WHAT SEPARATES INVENTORY FROM THE SHARED LANE A
     * SENDER, WHICH OTHERWISE LOOKS IDENTICAL.** Both are `shared_pool` with a
     * null business. The sender `RegisterSendingNumber` records is `active`
     * *because its earlier lifecycle happened at the carrier* — that command's
     * docblock argues it at length — and this one is `provisioning` because
     * nothing has happened to it here yet. Two consequences fall straight out and
     * both are the direction this has to fail:
     *
     *   1. **The shared sender can never be claimed by a tenant.**
     *      {@see self::freeFromPool()} looks only at `provisioning`, so the one
     *      number every text currently leaves on cannot be given away by a
     *      signup.
     *   2. **An unclaimed inventory number can never send.**
     *      `NumberState::provisioning` is outside `maySend()`, so
     *      {@see NumberSelector} cannot pick it — which matters because the
     *      selector's shared-pool branch is `business_id IS NULL` and would
     *      otherwise pile every tenant with no number of their own onto whichever
     *      spare number happened to sort first.
     *
     * ⚠️ **IDEMPOTENT, BECAUSE IT IS AN OPERATIONS COMMAND AND THOSE GET RE-RUN.**
     * A number already on the table is returned untouched rather than re-recorded
     * — including its state, so a number an operator quarantined is not marched
     * back by a second load. Callers tell the two apart with
     * `wasRecentlyCreated`.
     *
     * ⛔ **NO `provider_number_id` HERE, AND ITS ABSENCE IS NAMED RATHER THAN
     * IMPLIED.** Infobip's own id for a number arrives from the purchase API,
     * which is doc 51 §2.3 and is not built — `assign()` still accepts one so the
     * day that flow exists it has somewhere to put it, and until then recording a
     * made-up id would be worse than recording none.
     *
     * @throws InvalidArgumentException when the number does not normalise
     */
    public function addToPool(string $e164, string $actor = self::ACTOR, ?string $reason = null): PhoneNumber
    {
        $normalised = Identifier::normalise($e164, OutreachChannel::Sms);

        if ($normalised === null) {
            throw new InvalidArgumentException(
                "'{$e164}' does not normalise to E.164, so it could never be matched against the `to` "
                .'field of an inbound webhook. A number nothing can resolve is not inventory.'
            );
        }

        $existing = $this->numberRow($normalised);

        if ($existing !== null) {
            return $existing;
        }

        return $this->lifecycle->record(
            e164: $normalised,
            role: NumberRole::SharedPool,
            state: NumberState::Provisioning,
            actor: $actor,
            reason: $reason ?? 'Loaded into the assignable pool, awaiting a tenant.',
        );
    }

    /**
     * Give this tenant a number out of the pool, at the moment a tenant
     * comes into existence.
     *
     * ⚠️ **THIS IS THE CALLER `assign()` DID NOT HAVE.** `assign()` shipped with
     * its rules, its refusals and a full test file, and **nothing in `app/` ever
     * called it** — `CLAUDE.md`'s first recurring failure shape, in the one place
     * where being writerless is a compliance exposure rather than an inert
     * feature: with no tenant number, `tenantFor()` answers null for every inbound
     * message, so `ComplianceReplies::answerHelp()` could never name a business
     * and the carrier-mandated HELP response was dead on a green suite.
     * {@see TenantProvisioner} is the caller now.
     *
     * ## Three outcomes, and the middle one is the whole design
     *
     * **A free number** is claimed, assigned and walked into service.
     *
     * **No free number, on a platform that has assigned some** — the pool ran
     * out. {@see NumberPoolExhausted}, which aborts the registration. Signing a
     * tenant up without a number when every other tenant has one produces an
     * account whose inbound events resolve to nobody, silently, forever.
     *
     * **No free number, on a platform that has never assigned one** — nobody has
     * loaded a pool, so dedicated number allocation is not switched on here. Returns null, loudly in the
     * log, and provisioning continues: refusing every signup because an
     * unconfigured feature is unconfigured is an outage, not a safeguard. This is
     * exactly {@see NumberSelector}'s two-nulls distinction, one layer up, and it
     * is drawn from the same evidence: *"no inventory yet"* is a different fact
     * from *"the inventory is exhausted"*, and collapsing them is what makes one
     * of them invisible.
     *
     * ⚠️ **`FOR UPDATE SKIP LOCKED`, BECAUSE TWO PEOPLE CAN REGISTER AT ONCE.**
     * Without it both transactions read the same lowest-id free row and the
     * second one fails on `assign()`'s "already belongs to business" refusal —
     * a correct refusal for the wrong reason, presented to somebody who was
     * simply the second person to sign up that second. Skipping locked rows makes
     * the two claims take two different numbers instead of racing for one.
     *
     * ⚠️ **IDEMPOTENT.** A tenant who already has a number gets it back rather
     * than a second one — `assign()` would refuse anyway, and a provisioner
     * re-run must not turn into a failed signup.
     *
     * @throws NumberPoolExhausted when the pool is in use and has nothing free
     */
    public function claimForTenant(int $businessId, string $actor = self::ACTOR): ?PhoneNumber
    {
        $existing = $this->forBusiness($businessId);

        if ($existing !== null) {
            return $existing;
        }

        $free = $this->freeFromPool();

        if ($free === null) {
            return $this->refuseOrExplain($businessId);
        }

        $number = $this->assign($businessId, $free->e164, $free->provider_number_id);

        // ⚠️ **WALKED INTO SERVICE HERE, AND STOPPING AT `Provisioning` WOULD
        // LEAVE THE FEATURE INERT.** `assign()` deliberately leaves a *new* row
        // `Provisioning`, and `NumberState::maySend()` refuses that state — so a
        // tenant whose number never left it cannot answer HELP, cannot send the
        // missed-call text-back from the number the caller dialled, and would
        // have every message fall through to the shared Lane A sender. That is
        // the state this method exists to end.
        //
        // ⛔ **AND IT STOPS AT `Warming` RATHER THAN `Active`, WHICH IS NOT
        // TIMIDITY.** `Warming` is the first state that may send at all, and it
        // is the truthful one: this number has never sent anything. Walking to
        // `Active` would assert a warm-up that has not happened, and doc 51 §3's
        // warm-up caps — phase 4, unbuilt — are the thing that would then read a
        // state saying they do not apply.
        //
        // The `Registering` hop is passed through rather than waited on, and shared brand registration
        // is why: the pool number is bought *"under the GOAIEZ 10DLC
        // brand/campaign"*, so the registration this state models happened at the
        // brand before the number was ours to give away.
        return $this->walkTo($number, NumberState::Warming, $actor);
    }

    /**
     * Record a number the tenant brought themselves, on their own 10DLC brand —
     * decision 3310, and the **writer** for `phone_numbers.lane = 'tenant'`.
     *
     * ⛔ **NOTHING IN `app/` HAD EVER WRITTEN `MessagingLane::Tenant` TO THIS
     * TABLE.** {@see self::assign()} writes `Platform` and says why — the number
     * belongs to a tenant operationally while the *brand* stays ours (2101) — so
     * the column that tells "our number" from "their number" only ever held one
     * of its two values. A broadcast guard reading it would have been
     * `CLAUDE.md`'s writerless-control shape exactly: a permanently-false
     * predicate, a green suite, and a feature nobody could ever use.
     *
     * ⛔ **AND A GO AI EZ NUMBER CAN NEVER BECOME THEIR OWN BY RE-LABELLING.**
     * 3310 is verbatim: *"they can not use a go ai ez number for this they must
     * have there own."* A pool number is bought under the **GOAIEZ** brand and
     * assigned; flipping its lane would make the one column 3310 turns on say
     * something untrue, and every guard downstream would then be correct about a
     * lie. So an `e164` already in this inventory is refused rather than
     * converted — whether it belongs to nobody (the pool), to somebody else, or
     * to this tenant as the number we gave them.
     *
     * ## The swap, and why the one-number rule survives it
     *
     * ⚠️ **THE TENANT'S POOL NUMBER IS RELEASED, NOT KEPT ALONGSIDE.** Dedicated number allocation is one
     * number per tenant and {@see self::assign()} enforces it, because the whole
     * reverse lookup depends on the mapping being a function — two numbers for
     * one tenant means `forBusiness()`, `displayNumberFor()` and the
     * text-back each pick one arbitrarily. Adopting an own number therefore goes
     * through {@see self::releaseFromTenant()}, which parks the old one for
     * `numbers.release_park_days` with STOP and HELP still answered at platform
     * level throughout — the Reassigned Numbers window that method exists for.
     *
     * ⚠️ **`Warming`, NOT `Active`, ON THE SAME ARGUMENT `claimForTenant()`
     * MAKES.** The registration genuinely happened — at *their* brand rather
     * than ours — so the `Registering` hop is passed through. What has not
     * happened is this number sending anything through us, and `Warming` is the
     * truthful state for that. It is also the first state that may send at all,
     * so the adoption is not inert.
     *
     * @throws InvalidArgumentException when the number does not normalise, or is
     *                                  already in this platform's inventory in
     *                                  any form
     */
    public function adoptOwnNumber(
        int $businessId,
        string $e164,
        ?string $providerNumberId = null,
        string $actor = self::ACTOR,
    ): PhoneNumber {
        $normalised = Identifier::normalise($e164, OutreachChannel::Sms);

        if ($normalised === null) {
            throw new InvalidArgumentException(
                "'{$e164}' does not normalise to E.164. A broadcast sender that cannot be matched "
                .'against the `to` field of an inbound webhook is one whose STOP replies resolve to '
                .'nobody, and 3310 puts this number in front of a marketing blast.'
            );
        }

        // ⚠️ **NOT `numberRow()`, WHICH HIDES `retired` AND `released`.** A
        // parked number still occupies `phone_numbers_e164_active_unique`, so
        // going through the usual helper would let this method reach an insert
        // that the database refuses with SQLSTATE 23505 — a constraint name
        // where an operator needs a sentence.
        $existing = PhoneNumber::query()
            ->where('e164', $normalised)
            ->where('state', '!=', NumberState::Released->value)
            ->orderBy('id')
            ->first();

        if ($existing !== null) {
            return $this->refuseOrReturnAdopted($existing, $businessId, $normalised);
        }

        // The pool number they are holding goes back, parked. Done before the
        // insert so that the one-number-per-tenant rule is never briefly false.
        $this->releaseFromTenant(
            $businessId,
            $actor,
            'Replaced by the number this tenant brought on their own 10DLC brand (3310). Parked for '
            .'numbers.release_park_days before it may be given to anybody else.',
        );

        $number = $this->lifecycle->record(
            e164: $normalised,
            role: NumberRole::Primary,
            state: NumberState::Provisioning,
            actor: $actor,
            reason: 'Brought by the tenant on their own 10DLC brand (3310). The carrier registration '
                .'happened at their brand rather than at ours.',
            businessId: $businessId,
        );

        // ⚠️ **`lane` IS SET HERE BECAUSE `NumberLifecycle::record()` REFUSES TO
        // TAKE IT**, and that refusal is right: its own comment says a lane
        // written there would answer `BUILD-PLAN` §2.10.5's open question by
        // accident. This is not that question. §2.10.5 asks which lane the
        // *platform's* registered brand serves; this row is a *tenant's* brand,
        // which is Lane B by definition and is the only fact 3310 turns on.
        $number->fill(['lane' => MessagingLane::Tenant, 'provider_number_id' => $providerNumberId]);
        $number->save();

        return $this->walkTo(
            $number,
            NumberState::Warming,
            $actor,
            'Brought by the tenant on their own 10DLC brand (3310). Registration happened at their '
            .'brand, so the registering hop is passed through; it has never sent through us, so it '
            .'stops at warming.',
        );
    }

    /**
     * This tenant's own Lane B number, if they have one that may send.
     *
     * ⛔ **THE READ DECISION 3310'S SECOND PRECONDITION IS MADE OF, AND IT ASKS
     * ABOUT THE LANE RATHER THAN ABOUT THE TENANT.** `business_id = $businessId`
     * is true of the pool number this platform *gave* them —
     * {@see self::assign()} sets exactly that, with `lane = Platform` — so a
     * check on ownership alone would answer "yes, they have their own number"
     * for every tenant on the platform, on their first day, having brought
     * nothing. ⚠️ `NumberSelector`'s docblock calls a tenant-owned row *"its own
     * Lane B number"*, which is the same conflation one file over and is why
     * this predicate is written out rather than borrowed.
     *
     * ⚠️ **A STRING, NOT THE MODEL**, for {@see self::displayNumberFor()}'s
     * reason: handing a tenant-scoped-looking object back from a query that ran
     * outside every scope is how an unscoped read gets passed around. The caller
     * needs to know one exists and, for a log line, which one.
     */
    public function ownBrandNumberFor(int $businessId): ?string
    {
        $sendable = array_values(array_map(
            static fn (NumberState $state): string => $state->value,
            array_filter(NumberState::cases(), static fn (NumberState $state): bool => $state->maySend()),
        ));

        return PhoneNumber::query()
            ->where('business_id', $businessId)
            ->where('lane', MessagingLane::Tenant->value)
            ->whereIn('state', $sendable)
            ->orderBy('id')
            ->first()?->e164;
    }

    /**
     * Take this tenant's number back and park it — decision 2686.
     *
     * ⛔ **NOTHING RELEASED A NUMBER ON TENANT DELETION OR SUSPENSION, AND THE
     * CONSEQUENCE WAS WORSE THAN A LEAKED ROW.** Wiring 2610–2629's assignment
     * created the debt: a departed tenant's number stayed `warming` with their
     * `business_id`, so it was **out of the pool for ever** — `freeFromPool()`
     * looks for `provisioning` with no business — and `tenantFor()` went on
     * resolving it to a business that no longer exists, which is what
     * `ComplianceReplies::answerHelp()` reads to decide whose name goes in a
     * carrier-mandated reply.
     *
     * ⛔ **AND `business_id` IS `nullOnDelete`, SO THE DELETION ITSELF WOULD
     * HAVE FAILED** (2882). The FK nulls the column while `role` stays
     * `primary`, and `phone_numbers_shared_pool_has_no_business` refuses exactly
     * that shape — a CHECK violation inside `TenantDeletion::execute()`'s
     * transaction, rolling back a statutory erasure. **This method is what makes
     * the delete possible at all**, not merely tidy.
     *
     * ## What the park is for
     *
     * ⚠️ **NOT TIDINESS — REASSIGNMENT.** Consent records are per tenant and do
     * not follow a number. A customer who texts the old number after a business
     * closes must never reach a *different* business, because their STOP would
     * suppress against the wrong customer list and their HELP would name a
     * business they have never heard of. That is the Reassigned Numbers problem
     * in miniature, and `numbers.release_park_days` is the cold period.
     *
     * ⚠️ **STOP AND HELP STILL ANSWER THROUGHOUT**, at platform level: the row
     * survives with no business, so {@see self::isPlatformNumber()} says yes and
     * the carrier's HELP test is met by the platform's own name. Deleting the
     * row would have been simpler and would leave a number that answers nothing.
     *
     * ⚠️ **`shared_pool` AND `Retired` TOGETHER, BECAUSE NEITHER IS ENOUGH.**
     * The role is what lets `business_id` be null under I40's CHECK; the state
     * is what keeps it out of `freeFromPool()` — which looks for `provisioning`
     * — and out of `NumberSelector`, since `Retired` is outside `maySend()`.
     *
     * @return PhoneNumber|null the parked number, or null when the tenant had
     *                          none
     */
    public function releaseFromTenant(int $businessId, string $actor = self::ACTOR, ?string $reason = null): ?PhoneNumber
    {
        $number = $this->forBusiness($businessId);

        if ($number === null) {
            return null;
        }

        $parked = $this->lifecycle->transitionTo(
            $number,
            NumberState::Retired,
            $actor,
            $reason ?? 'The tenant this number belonged to was deleted. Parked for '
                .'numbers.release_park_days before it may be given to anybody else (2686).',
        );

        // ⚠️ **AFTER THE TRANSITION, NOT BEFORE.** `NumberLifecycle::file()`
        // writes the audit entry under the number's tenant, and a row whose
        // business is already null has nobody to file it under — the one record
        // of why this number left would be the one thing the change did not
        // keep.
        $parked->forceFill([
            'business_id' => null,
            'location_id' => null,
            'role' => NumberRole::SharedPool,
        ])->save();

        return $parked->refresh();
    }

    /**
     * Walk every number whose park has run out back into the assignable pool.
     *
     * ⚠️ **THE READER FOR `retired_at`, WHICH HAD A WRITER AND NO READER.**
     * `NumberLifecycle` has stamped it since the column was created, under a
     * comment saying the park-then-release sweep was *"doc 51 §5.4–§5.5 and
     * phase 4"*. This is that sweep, in the shape 2686 rules rather than the
     * doc's: the number comes back to **our** pool rather than going back to
     * Infobip.
     *
     * ⚠️ **A NUMBER STILL NAMING A BUSINESS IS NEVER RETURNED.** Only
     * {@see self::releaseFromTenant()} produces the `shared_pool` + null-business
     * shape, so a number retired by some future owner-click flow while still
     * assigned stays parked until somebody decides what it is for. The predicate
     * is the guard rather than a comment, because this method's whole job is
     * handing a number to a stranger.
     *
     * @return int how many came back
     */
    public function returnParkedNumbersToPool(int $parkDays, string $actor = self::ACTOR): int
    {
        if ($parkDays <= 0) {
            // ⛔ A park of zero is not "return immediately" — it is an unset or
            // mis-edited figure, and the direction this has to fail is *not
            // handing a stranger's number to another tenant today*. 2409's rule,
            // one service over.
            //
            // ⚠️ **`ReturnParkedNumbers` REFUSES ON THE SAME CONDITION AND THAT
            // IS NOT A REASON TO DELETE THIS ONE** (2885). The command's guard
            // exists to *say something to an operator*; this one exists so the
            // refusal survives the next caller, and 398 is the record of what
            // happens when only the outer one is real. The test drives this
            // method directly, with the command's own case beside it.
            return 0;
        }

        $due = PhoneNumber::query()
            ->where('state', NumberState::Retired->value)
            // ⚠️ **THESE TWO PREDICATES ARE ONE PREDICATE, AND SAYING SO IS
            // HONEST RATHER THAN TIDY** (2884). `phone_numbers_shared_pool_has_
            // no_business` makes `role = 'shared_pool'` and `business_id IS
            // NULL` equivalent by schema, so **deleting either one alone leaves
            // the suite green** — 398's shape, found by mutating this method's
            // own guard. Neither is removed: the CHECK is what makes them
            // equivalent, this is the only place in `app/` that hands a number
            // to a stranger, and a query that states the safety property it
            // depends on is worth more here than one line fewer.
            ->whereNull('business_id')
            ->where('role', NumberRole::SharedPool->value)
            ->whereNotNull('retired_at')
            ->where('retired_at', '<=', now()->subDays($parkDays))
            ->orderBy('id')
            ->get();

        $returned = 0;

        foreach ($due as $number) {
            $this->lifecycle->transitionTo(
                $number,
                NumberState::Provisioning,
                $actor,
                "Parked for {$parkDays} days after its tenant left; cold enough to be assigned again (2686).",
            );

            $returned++;
        }

        return $returned;
    }

    /**
     * Is this one of ours, belonging to no tenant — the shared Lane A pool?
     *
     * ⚠️ **THIS IS THE DISTINCTION `tenantFor()` DELIBERATELY CANNOT DRAW**, and
     * the HELP reply needs it. `tenantFor()` answers null for two very different
     * numbers: one of ours that nobody owns, and one that was never ours at all.
     * Answering a carrier's HELP test on the first is required; answering on the
     * second would mean replying on behalf of a number this platform does not
     * hold, from whichever number the selector happened to pick.
     *
     * ⛔ **A PARKED NUMBER COUNTS AS OURS, WHICH IS WHY THIS DOES NOT GO THROUGH
     * `numberRow()`** (2686, 2883). That helper excludes `retired` and
     * `released` so that a recycled number stops answering for the tenant who
     * used to hold it — right for `tenantFor()`, and exactly wrong here: it
     * would make a number we are still holding, for ninety days, answer a STOP
     * or a HELP with silence. **The park is the window in which yesterday's
     * customer is most likely to text**, and the carrier mandates a reply to
     * HELP whoever is texting.
     *
     * ⚠️ `released` stays excluded. That number has gone back to the carrier and
     * answering on it would be replying on somebody else's line.
     */
    public function isPlatformNumber(string $e164): bool
    {
        $normalised = Identifier::normalise($e164, OutreachChannel::Sms);

        if ($normalised === null) {
            return false;
        }

        $number = PhoneNumber::query()
            ->where('e164', $normalised)
            ->where('state', '!=', NumberState::Released->value)
            ->orderBy('id')
            ->first();

        return $number !== null && $number->business_id === null;
    }

    /**
     * Give this tenant their number.
     *
     * ⚠️ **DEDICATED NUMBER ALLOCATION SAYS ONE NUMBER PER TENANT AND THIS ENFORCES IT RATHER THAN
     * ASSUMING IT.** A second assignment is refused, because the whole reverse
     * lookup depends on the mapping being a function: two numbers for one tenant
     * is survivable, but the code that follows would have to pick one, and the
     * inverse — one number serving two tenants — would send a HELP reply naming
     * the wrong business and route a STOP into the wrong customer list.
     *
     * ⛔ **AND THE SECOND NUMBER IS FREE, WHICH IS THE HALF THIS REFUSAL WAS NOT
     * WRITTEN FOR AND IS NOW ALSO HOLDING** (4820, closing half of 4689(c)).
     * `plan.base.additional_phone_number.monthly_cents` is seeded — $10 a month,
     * a **subscription line and never a credit** (3305) — and **nothing in `app/`
     * reads it**. There is no allowance, no count on `subscriptions`, and no
     * `NumberAllowance` on `LocationAllowance`'s
     * pattern — named in prose rather than with a `{@see}`, because a docblock
     * reference is what Pint turns into an import and 4681 records what an import
     * only a comment uses does to the next lint that reads this file.
     * So the day this refusal is relaxed, a tenant gets a second number
     * and is charged nothing, and every screen renders a coherent, wrong number —
     * `LocationAllowance`'s own words about the add-on that had a price, an
     * instalment split, a column and no buyer.
     *
     * ⚠️ **THE ORDERING IS THE RULING AND IT IS NOT A PREFERENCE**: the charge
     * arrives first, or the two arrive together. `Subscriptions::recordAdditionalLocations()`
     * is the shape the charge takes — an operator records what was arranged, the
     * agreed rate is stored on the row and never re-read (3443) — and it is a
     * slice, not a paragraph, because relaxing the one-number rule also has to answer which number
     * `forBusiness()`, `displayNumberFor()`, {@see NumberSelector} and the
     * text-back pick. **That is a reversal of a foundational rule and it is the owner's,
     * not a lane's** (4823).
     *
     * ⚠️ **VOICE, SMS AND MMS RIDE THE SAME NUMBER AND THERE IS NO COLUMN FOR
     * THAT.** Voice architecture requires it: the missed-call text-back goes back to the caller
     * **from the number they just dialled**, which is only possible if the voice
     * number and the SMS number are one number. There is deliberately no
     * `supports_voice` flag — a flag implies the alternative is configurable,
     * and a tenant whose voice number differs from their SMS number breaks the missed call workflow
     * silently rather than loudly.
     *
     * @param  string  $e164  The number, from the platform pool.
     * @param  string|null  $providerNumberId  Infobip's own id for it, so a
     *                                         later API call about this number
     *                                         does not have to search by digits.
     *
     * @throws InvalidArgumentException when the tenant already has one, when the
     *                                  number is already somebody else's, or
     *                                  when the number does not normalise
     */
    public function assign(int $businessId, string $e164, ?string $providerNumberId = null): PhoneNumber
    {
        $normalised = Identifier::normalise($e164, OutreachChannel::Sms);

        if ($normalised === null) {
            throw new InvalidArgumentException(
                'A tenant number must normalise to E.164. An unnormalised number cannot be matched '
                .'against the `to` field of an inbound webhook, so nothing would ever resolve to this '
                .'tenant again.'
            );
        }

        $existing = $this->forBusiness($businessId);

        if ($existing !== null) {
            throw new InvalidArgumentException(
                "Business {$businessId} already has a number ({$existing->e164}). Dedicated number allocation is one number per "
                .'tenant, and a second would make the number-to-tenant lookup ambiguous in the '
                .'direction that matters — an inbound STOP would have two candidate customer lists. '
                .'It would also be free: the extra-number SKU is seeded and nothing reads it (4820). '
                .'Whoever relaxes this brings the charge with it, in the same slice.'
            );
        }

        $claimed = $this->numberRow($normalised);

        if ($claimed !== null && $claimed->business_id !== null) {
            throw new InvalidArgumentException(
                "The number {$normalised} already belongs to business {$claimed->business_id}. "
                .'Reassigning it would route that tenant\'s inbound replies to this one.'
            );
        }

        // ⚠️ **`Provisioning`, NEVER `Active`.** `NumberLifecycle` owns every
        // state transition and the legality table with it; writing `Active` here
        // would skip `Registering` and `Warming` — the states that exist because
        // a brand-new number sending at volume on day one is what gets a 10DLC
        // campaign filtered. A number arrives unable to send and is promoted.
        $number = $claimed ?? new PhoneNumber(['e164' => $normalised]);

        $number->fill([
            'business_id' => $businessId,
            'provider_number_id' => $providerNumberId,
            // ⚠️ **A *NUMBER'S* ROLE, NOT A USER'S** — `primary` | `extension` |
            // `shared_pool`, doc 51 §2.1. `StaffTest`'s "one service writes
            // users.role" lint matches both spellings of this column name
            // anywhere in `app/`, deliberately, because neither can be told
            // from a read without parsing. This file is on that allowlist as
            // the third instance of the same collision, with the same
            // compensating control the other two carry: it can never reach a
            // `User`, and the lint asserts that rather than trusting it.
            'role' => NumberRole::Primary,
            // Lane A: the GOAIEZ brand and our own pool. ⚠️ **Not Lane B, even
            // though the number is the tenant's.** 2101 is exactly this — the
            // number belongs to a tenant operationally while the *brand* stays
            // ours, so the complaint rate accrues to the platform. A `Tenant`
            // lane here would assert the tenant has their own TCR brand, which
            // is the one thing tenant number isolation says they do not.
            'lane' => MessagingLane::Platform,
            'purchased_at' => $number->purchased_at ?? now(),
        ]);

        if ($number->exists) {
            $number->save();
        } else {
            $number->state = NumberState::Provisioning;
            $number->save();
        }

        return $number->refresh();
    }

    /**
     * This tenant's number, if they have one.
     */
    public function forBusiness(int $businessId): ?PhoneNumber
    {
        return PhoneNumber::query()
            ->where('business_id', $businessId)
            ->whereNotIn('state', [NumberState::Released->value, NumberState::Retired->value])
            ->orderBy('id')
            ->first();
    }

    /**
     * This tenant's number, in the form a person reads it off a screen.
     *
     * ⚠️ **THE READ THAT MADE THE ASSIGNED NUMBER VISIBLE TO THE PERSON IT BELONGS TO** (2911).
     * `claimForTenant()` has had a caller since `TenantProvisioner` gained one,
     * so every tenant has held a number — and **no component or controller
     * anywhere referenced `PhoneNumber` or this class**, so the owner could not
     * be told what it was. Call forwarding setup asks them to forward their line *to that number*,
     * which is impossible to do and impossible to support if nobody can say what
     * it is.
     *
     * ⚠️ **IT LIVES HERE RATHER THAN IN THE SCREEN**, beside `claimForTenant()`,
     * `tenantFor()` and `releaseFromTenant()`. This class's own docblock is
     * explicit that `phone_numbers` is deliberately un-scoped and that the
     * application layer is the whole boundary — so the one query that finds a
     * tenant's number stays in the one file that owns it, rather than a screen
     * growing a second `where('business_id', …)` that no global scope checks.
     *
     * ⚠️ **AND IT RETURNS A STRING, NOT THE MODEL**, for the reason `tenantFor()`
     * gives about returning an id: handing a tenant-scoped-looking object back
     * from a query that ran outside every scope is how an unscoped read gets
     * passed around.
     *
     * NANP only, and everything else is returned exactly as stored. There is no
     * phone-number library in this application and inventing a grouping for an
     * international number would print a number that does not dial — the honest
     * `+441632960123` is better than a wrong `+44 (163) 296-0123`.
     */
    public function displayNumberFor(int $businessId): ?string
    {
        $e164 = $this->forBusiness($businessId)?->e164;

        if ($e164 === null) {
            return null;
        }

        if (preg_match('/^\+1(\d{3})(\d{3})(\d{4})$/', $e164, $parts) !== 1) {
            return $e164;
        }

        return "({$parts[1]}) {$parts[2]}-{$parts[3]}";
    }

    /**
     * Whose number is this — the resolution every inbound path needs.
     *
     * Returns null for a number nobody owns, which is the shared Lane A pool
     * number and is **not** an error: a STOP arriving there is still honoured,
     * platform-wide, by `ConsentService::suppressFromCarrier()`, which takes no
     * scope at all precisely so that path does not need a tenant.
     *
     * ⚠️ **A RELEASED OR RETIRED NUMBER RESOLVES TO NOBODY, DELIBERATELY.** A
     * number recycled to another customer of the carrier would otherwise keep
     * answering for the tenant who used to hold it — replying to a stranger's
     * HELP with a business they have never heard of, and worse, attributing
     * their STOP to that business's list.
     */
    public function tenantFor(string $e164): ?int
    {
        $normalised = Identifier::normalise($e164, OutreachChannel::Sms);

        if ($normalised === null) {
            return null;
        }

        return $this->numberRow($normalised)?->business_id;
    }

    /**
     * Walk a provisioned number into service, one legal hop at a time.
     *
     * ⚠️ **THE FIRST VERSION OF THIS METHOD CALLED `transitionTo($number,
     * Active)` DIRECTLY AND COULD NEVER HAVE WORKED.** `Provisioning → Active`
     * is not an edge in `NumberState::edges()` — the real path is
     * `Provisioning → Registering → Warming → Active`, and those intermediate
     * states are not bureaucracy: **`Warming` is the first state that may send
     * at all**, and it exists because a brand-new number sending at volume on
     * day one is what gets a 10DLC campaign filtered. The lifecycle refused it
     * loudly, which is the legality table doing exactly its job.
     *
     * Every hop goes through {@see NumberLifecycle}, so each one is checked
     * against that table and each writes its own `number_state_changes` row — a
     * `state` assigned directly would leave the audit trail with no row for the
     * state the number is actually in.
     *
     * ⚠️ **THIS IS THE TEST AND BOOTSTRAP PATH, NOT THE PRODUCTION CLOCK.** In
     * production the hops are separated by real waits — `Registering` while TCR
     * processes the number, `Warming` while volume ramps. A caller that wants
     * one hop calls `NumberLifecycle` itself; this exists so that provisioning a
     * number and having it able to send is one call in the places where the wait
     * is not being simulated.
     *
     * @return PhoneNumber the number in its new state
     */
    public function bringIntoService(PhoneNumber $number, string $actor, ?string $reason = null): PhoneNumber
    {
        foreach ([NumberState::Registering, NumberState::Warming, NumberState::Active] as $step) {
            if ($number->state === $step) {
                continue;
            }

            if (! $number->state->canTransitionTo($step)) {
                // Already past this step, or somewhere the chain does not pass
                // through — a quarantined number, say. Skipped rather than
                // forced: the legality table is the authority and this method
                // does not get to argue with it.
                continue;
            }

            $number = $this->lifecycle->transitionTo($number, $step, $actor, $reason);
        }

        return $number;
    }

    private function numberRow(string $normalised): ?PhoneNumber
    {
        return PhoneNumber::query()
            ->where('e164', $normalised)
            ->whereNotIn('state', [NumberState::Released->value, NumberState::Retired->value])
            ->orderBy('id')
            ->first();
    }

    /**
     * One unclaimed pool number, locked so nobody else takes it.
     *
     * See {@see self::addToPool()} for why `provisioning` is the mark of an
     * assignable number and why the shared Lane A sender is therefore out of
     * reach here.
     */
    private function freeFromPool(): ?PhoneNumber
    {
        return PhoneNumber::query()
            ->whereNull('business_id')
            ->where('state', NumberState::Provisioning->value)
            ->orderBy('id')

            ->first();
    }

    /**
     * Nothing was free — decide whether that is an exhausted pool or no pool.
     *
     * @throws NumberPoolExhausted
     */
    private function refuseOrExplain(int $businessId): null
    {
        $assigned = PhoneNumber::query()->whereNotNull('business_id')->count();

        if ($assigned > 0) {
            // ⚠️ **LOGGED BEFORE THE THROW, BECAUSE THE THROW ROLLS THE
            // TRANSACTION BACK.** Everything this method could otherwise have
            // written — an audit entry, an activity item — disappears with the
            // registration, so the log is the only record that will survive to
            // tell an operator why a signup failed. `Log` rather than
            // `AuditService`: `audit_log` is tenant-owned and the tenant is about
            // to stop existing.
            Log::critical('A tenant could not be provisioned: the number pool is exhausted.', [
                'business_id' => $businessId,
                'assigned_numbers' => $assigned,
                'actor' => self::ACTOR,
            ]);

            throw NumberPoolExhausted::noFreeNumber($assigned);
        }

        // The bootstrap case: nobody has ever loaded a pool, so dedicated number allocation is not
        // running here. Said out loud rather than passed over — a tenant with no
        // number is a real degradation (their HELP is answered by the platform
        // rather than by their own name, and the missed-call text-back has no number to come
        // from), and the thing that makes it survivable is that it is uniform
        // across every tenant rather than a surprise for one of them.
        Log::warning('A tenant was provisioned with no number: the platform has no assignable pool.', [
            'business_id' => $businessId,
            'actor' => self::ACTOR,
        ]);

        return null;
    }

    /**
     * An `e164` already in this inventory: hand it back only when it is already
     * exactly what was asked for, and otherwise say which of the three refusals
     * this is.
     *
     * ⛔ **THE THREE ARE NAMED APART DELIBERATELY.** They are three very
     * different conversations — *"that is one of ours"*, *"that is somebody
     * else's"*, *"that is the number we gave you"* — and an operator sent to
     * look for the wrong one of them is `SendRefusalReason::Deleted`'s argument:
     * a label that is nearly true is the kind a reader trusts.
     *
     * @throws InvalidArgumentException
     */
    private function refuseOrReturnAdopted(PhoneNumber $existing, int $businessId, string $normalised): PhoneNumber
    {
        if ($existing->business_id === null) {
            throw new InvalidArgumentException(
                "{$normalised} is one of this platform's own numbers, bought under the GOAIEZ 10DLC "
                .'brand. Decision 3310 is explicit that a broadcast may not ride a GO AI EZ number, '
                .'so it cannot be adopted as the tenant\'s own — re-labelling it would make the lane '
                .'column say something untrue and every guard downstream would be correct about a lie.'
            );
        }

        if ($existing->business_id !== $businessId) {
            throw new InvalidArgumentException(
                "{$normalised} already belongs to business {$existing->business_id}. Adopting it here "
                .'would route that tenant\'s inbound replies — including their STOP — to this one.'
            );
        }

        if ($existing->lane === MessagingLane::Tenant) {
            // Idempotent: an Ops command re-run must not fail, and this is
            // already precisely the state it was asked to produce.
            return $existing;
        }

        throw new InvalidArgumentException(
            "{$normalised} is the pool number this platform assigned to business {$businessId}, under "
            .'the GOAIEZ 10DLC brand. 3310 requires the tenant\'s own number and their own brand; this '
            .'is ours on both counts.'
        );
    }

    /**
     * Walk a number up to a state, one legal hop at a time.
     *
     * The same discipline as {@see self::bringIntoService()} and for the same
     * reason: every hop goes through {@see NumberLifecycle}, so each is checked
     * against the legality table and each writes its own `number_state_changes`
     * row. A `state` assigned directly would leave the history with no row for
     * the state the number is actually in.
     *
     * @param  ?string  $reason  Why this number is being walked. ⚠️ **Defaulted
     *                           rather than required, and the default is the
     *                           pool claim** — `adoptOwnNumber()` passes its own
     *                           because a number the tenant brought was never
     *                           claimed from any pool, and a history row saying
     *                           otherwise is the one record of where the number
     *                           came from.
     */
    private function walkTo(PhoneNumber $number, NumberState $target, string $actor, ?string $reason = null): PhoneNumber
    {
        $reason ??= 'Claimed from the platform pool at signup (dedicated number allocation). The number is registered under '
            .'the GOAIEZ 10DLC brand before it is assigned, so the registration wait happened at the '
            .'brand rather than at this number.';

        foreach ([NumberState::Registering, NumberState::Warming, NumberState::Active] as $step) {
            if ($number->state === $target) {
                break;
            }

            // Skipped rather than forced when the chain does not pass through
            // here — the legality table is the authority and this method does
            // not get to argue with it. `bringIntoService()` says the same.
            if ($number->state->canTransitionTo($step)) {
                $number = $this->lifecycle->transitionTo($number, $step, $actor, $reason);
            }
        }

        return $number;
    }
}
