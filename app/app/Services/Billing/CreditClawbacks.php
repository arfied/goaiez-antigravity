<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\AutopilotActionType;
use App\Enums\CreditClawbackOutcome;
use App\Enums\CreditKind;
use App\Enums\CreditPool;
use App\Enums\CreditPurchaseStatus;
use App\Enums\CreditReversalCause;
use App\Models\Business;
use App\Models\CreditPurchase;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The unfunder: the one place in this application that takes credit back when the
 * money that bought it goes back (decision 6380).
 *
 * ⛔ **IT IS THE ONLY FILE IN `app/` PERMITTED TO CONSTRUCT `CreditKind::Refund`,
 * AND A `BillingTest` LINT SAYS SO** — {@see CreditPurchases}' shape exactly, one
 * direction over. Before this class the case existed on the enum with a draw
 * order, a CHECK that deliberately left its sign open, and **zero writers
 * anywhere in `app/`**: a tenant could buy the $250 / 10,000-SMS top-up, receive
 * the credit, charge the payment back and keep every message. That is 272's
 * writerless control with a bill attached, and it is the failure `CLAUDE.md`
 * names as the one this codebase keeps repeating.
 *
 * ## The two events are two events, and 2056 decides which one counts
 *
 * ⚠️ **THE CLAWBACK IS WRITTEN FROM THE WEBHOOK, NEVER FROM A SYNCHRONOUS
 * RESPONSE**, for exactly the reason the credit is: *"webhooks stay the source of
 * truth on both"*. Nothing in this application issues a refund, so there is no
 * synchronous response to be tempted by — every reversal this class hears about
 * arrives as a verified notification from a gateway.
 *
 * ## Idempotency, in two layers, and neither is a new key
 *
 * ⚠️ **A REPLAY MUST NEVER DEBIT TWICE.** Both gateways redeliver and both can
 * deliver out of order.
 *
 *   1. **The gateway event tables**, unchanged and not extended.
 *      `stripe_events.stripe_event_id` and `authorize_net_events.notification_id`
 *      are unique and the insert is the claim, so the *same* event arriving twice
 *      does no work at all. That is the mechanism the credit path already uses
 *      and this adds no second one.
 *   2. **Arithmetic against the ledger** —
 *      {@see CreditLedger::reversedUnitsFor()}. Two *different* events can
 *      describe one reversal, which is precisely how a double clawback would
 *      arrive, and the event tables cannot see that. So this computes what the
 *      reversal is worth **in total** and subtracts what it has already taken;
 *      a second description of the same money moves nothing.
 *
 * ⛔ **THE CREDIT PATH'S SECOND LAYER IS NOT AVAILABLE HERE AND THAT IS WHY THIS
 * ONE EXISTS.** `credit_ledger_one_credit_per_purchase` is a partial unique index
 * on `kind = 'purchase'`, and `CreditTopUpTest` already pins that it deliberately
 * *permits* a refund pointing at the same purchase — because a purchase may
 * legitimately be reversed in parts, twice. An index cannot tell the second part
 * from the second delivery; arithmetic can.
 *
 * ## What decides the size of the movement
 *
 * ⛔ **THE PROPORTION OF THE PRICE THAT CAME BACK, NEVER A FIGURE FROM THE
 * NOTIFICATION.** `CreditPurchases` refuses to credit units on the strength of an
 * amount a notification claims, and the same hole runs the other way: a handler
 * that debited "whatever units the event said" would let anybody who could forge
 * one empty a balance. The units and the price were both recorded when the
 * purchase was opened; the only question here is what fraction of that price has
 * been returned.
 *
 * ## Where it takes from
 *
 * ⛔ **THE TOP-UP POOL AND NOWHERE ELSE**, because that is
 * {@see CreditKind::Refund}'s draw order and it is not this class's to override.
 * **The monthly allotment is never eaten by a chargeback** — a grant is not what
 * was bought, and taking it would punish a tenant for a dispute by withdrawing
 * something included in their plan.
 *
 * ⚠️ **THE POOL IS FUNGIBLE AND NO LOT IS TRACKED.** A tenant holding two
 * top-ups who reverses one has the units taken from the single purchased
 * balance, not from "those" units, because there is no such thing as those
 * units. Building lot tracking to say otherwise would be a second definition of
 * the balance, which is the thing `CreditLedger`'s chokepoint exists to prevent.
 *
 * ## 3441 does not apply here, and it was checked rather than assumed
 *
 * ⚠️ **A `Refund` IS NOT GATED BY THE ACTIVE-PLAN RULE.**
 * {@see CreditLedger::planPermitsThisMovement()} returns true for every kind that is not
 * `Consume`, and its own docblock names this case: refusing a clawback on an
 * inactive plan *"would leave a tenant holding credit they have been repaid
 * for"*. A cancelled account is the **commonest** account for a reversal to
 * arrive against, so the gate firing here would be the gate helping somebody keep
 * what they did not pay for. `CreditClawbackTest` drives it.
 */
final class CreditClawbacks
{
    public function __construct(
        private readonly CreditLedger $ledger = new CreditLedger,
        private readonly AuditService $audit = new AuditService,
        private readonly ActivityService $activity = new ActivityService,
    ) {}

    /**
     * Apply one verified reversal to the credit it bought.
     *
     * ⚠️ **CALLED INSIDE `Tenancy::actingAs()`**, by a webhook handler that has
     * just resolved the tenant from {@see CreditPurchases::referenceFor()} —
     * {@see CreditPurchases::settle()}'s contract exactly, so the two halves of
     * one purchase's life are reached the same way.
     *
     * ⛔ **NOTHING THROWS OUT OF HERE**, for `settle()`'s reason: every refusal is
     * deterministic and will be just as true on the redelivery, so an exception
     * would ask the gateway to retry a decision that was made correctly and would
     * read as an infrastructure fault (3239). The refusals become an outcome and
     * a row.
     *
     * @param  string  $reference  Our own handle for the purchase, resolved from
     *                             the un-tenanted index (3594).
     * @param  ?Money  $reversed  How much of this purchase's price has been
     *                            returned **in total**, or null when the vendor's
     *                            payload did not yield an amount this application
     *                            can read exactly. ⚠️ **Cumulative, not
     *                            incremental** — `charge.refunded` carries the
     *                            charge's `amount_refunded`, which is what makes
     *                            layer 2 above work across partial refunds.
     */
    public function reverse(
        string $reference,
        ?Money $reversed,
        CreditReversalCause $cause,
        string $actor,
    ): CreditClawbackOutcome {
        $businessId = Tenancy::idOrFail();

        $purchase = CreditPurchase::query()->where('reference', $reference)->first();

        if (! $purchase instanceof CreditPurchase) {
            // The index resolved a tenant and the purchase is not there — a real
            // inconsistency rather than an ordinary miss, and one a retry cannot
            // mend. `settle()` answers `Unknown` to the same shape.
            return CreditClawbackOutcome::Unknown;
        }

        if ($purchase->status !== CreditPurchaseStatus::Credited) {
            /*
             * ⛔ MONEY CAME BACK AND NO CREDIT WAS EVER WRITTEN, WHICH IS THE
             * CASE `PurchaseReconciliation` LIVES IN AND THE ONE THIS CLASS DOES
             * NOT. Nothing to remove, and — deliberately — nothing written to the
             * status either: `settle()` claims `WHERE status IN
             * ('pending','authorized')`, so marking this row terminal would put it
             * beyond a notification that may still arrive. Decision 6396 records
             * what that leaves open, which is a purchase that could still credit
             * against money already returned.
             */
            Log::warning('a reversed payment matched a purchase that had never credited', [
                'credit_purchase_id' => $purchase->getKey(),
                'status' => $purchase->status->value,
                'cause' => $cause->value,
            ]);

            return CreditClawbackOutcome::NotCredited;
        }

        if (! $reversed instanceof Money
            || $reversed->currency !== $purchase->currency
            || $reversed->minorUnits <= 0
            || $purchase->price_cents <= 0) {
            /*
             * ⛔ NEVER GUESS AN AMOUNT FOR A DEBIT. A missing figure, one in a
             * currency this purchase was not sold in, or a reversal of nothing,
             * all mean the comparison this movement turns on cannot be made —
             * and a debit made anyway is `Mismatched`'s free-credit hole with its
             * sign flipped, emptying a balance instead of filling one.
             */
            Log::warning('a reversal carried no amount this purchase can be compared against', [
                'credit_purchase_id' => $purchase->getKey(),
                'cause' => $cause->value,
                'purchase_currency' => $purchase->currency,
                'reversed_currency' => $reversed?->currency,
            ]);

            return CreditClawbackOutcome::Unreadable;
        }

        return DB::transaction(function () use ($purchase, $reversed, $cause, $actor, $businessId): CreditClawbackOutcome {
            /*
             * ⚠️ THE LOCK IS ON THE BUSINESS ROW, FOR THE REASON `CreditLedger`
             * DOCUMENTS AT LENGTH AND FOR ONE THIS CLASS ADDS. Reading how much
             * has already been reversed and then writing a reversal is a race:
             * two notifications describing one refund could both read zero and
             * both debit in full. `CreditLedger::move()` takes this same lock
             * below, and re-acquiring a row lock inside one transaction is free —
             * what would not be free is deciding *outside* it.
             *
             * `resetMonthly()` is the precedent: the caller that makes the
             * decision takes the lock, so that the check and the write are one.
             */
            Business::query()->whereKey($businessId)->lockForUpdate()->first();

            $owed = $this->unitsFor($purchase, $reversed);
            $already = $this->ledger->reversedUnitsFor(
                $purchase->product,
                CreditPurchase::class,
                (int) $purchase->getKey(),
            );

            $clawback = $owed - $already;

            if ($clawback <= 0) {
                // Layer 2 speaking. A redelivery under a new event id, or a
                // second event describing the same money, lands here.
                return CreditClawbackOutcome::Nothing;
            }

            /*
             * ⛔ THE CLAMP, AND IT IS DECISION 6385 — THE CRUX OF THIS SLICE.
             * The balance stops at zero and is never driven negative. Both
             * directions were argued; what settles it is that
             * `credit_ledger_balance_is_never_negative` is a CHECK constraint
             * whose own migration comment already made the case — *"a negative
             * balance is an overdraft nobody agreed to extend, and it would read
             * as one on the screen that eventually shows it"* — so going negative
             * is not an open lane decision at all, it is a schema change
             * overturning a written argument.
             *
             * ⚠️ AND THE CLAMP IS COMPUTED HERE RATHER THAN LEFT TO THE LEDGER'S
             * REFUSAL. `legsFor()` would throw `CreditMovementRefused` on an
             * over-large debit, which is the correct behaviour for a *spend* and
             * the wrong one for this: a clawback that throws leaves the whole
             * reversal unrecorded and the shortfall invisible. Taking what is
             * there deliberately, and reporting the rest, is the difference
             * between a fact and an exception.
             */
            $available = $this->ledger->balance($purchase->product, CreditPool::TopUp);
            $taken = max(0, min($clawback, $available));
            $shortfall = $clawback - $taken;

            $balanceAfter = null;

            if ($taken > 0) {
                $entry = $this->ledger->record(
                    $purchase->product,
                    // ⛔ THE ONE CONSTRUCTION OF THIS CASE IN `app/`. See the
                    // class docblock and `BillingTest`'s clawback lint, which
                    // names this file.
                    CreditKind::Refund,
                    -$taken,
                    $actor,
                    $cause->ledgerReason(),
                    // The reference the reversal is computed against on every
                    // later delivery. Without both halves layer 2 cannot see its
                    // own earlier rows at all, and `credit_ledger`'s CHECK
                    // refuses half a reference anyway.
                    $purchase::class,
                    (int) $purchase->getKey(),
                );

                $balanceAfter = $entry->balance_after;
            }

            $this->audit->record('billing.credit_clawed_back', $actor, $purchase, [
                'cause' => $cause->value,
                'product' => $purchase->product->value,
                'gateway' => $purchase->gateway->value,
                'gateway_transaction_id' => $purchase->gateway_transaction_id,
                'reversed_cents' => $reversed->minorUnits,
                'currency' => $reversed->currency,
                'units_owed' => $clawback,
                'units_taken' => $taken,
                // ⚠️ THE FIGURE THE LEDGER CANNOT CARRY. A movement that did not
                // happen writes no row, so this audit entry is the only record
                // that money left the platform and credit did not come back.
                'units_shortfall' => $shortfall,
                'balance_after' => $balanceAfter,
            ]);

            $this->activity->record(
                AutopilotActionType::SystemMessage,
                title: $this->tenantTitle($purchase, $cause, $shortfall),
            );

            if ($shortfall > 0) {
                /*
                 * ⛔ THE OUTCOME THAT COSTS US MONEY. The units were already
                 * spent — texts sent, emails delivered, model calls made and paid
                 * for — and the payment has gone back. Nothing in this
                 * application can recover it, so what it can do is say so
                 * somewhere a person will read.
                 *
                 * ⚠️ A LOG AND AN AUDIT ENTRY RATHER THAN AN `OperatorAlerts`
                 * BELL, AND THAT IS DELIBERATE (6387): `raise()` sends an email
                 * and a text, and this code runs inside the webhook's own
                 * database transaction with a business row locked. A vendor send
                 * on a locked path is the wrong shape whatever the bell is worth.
                 */
                Log::warning('a reversed payment could not be fully clawed back', [
                    'business_id' => $businessId,
                    'credit_purchase_id' => $purchase->getKey(),
                    'product' => $purchase->product->value,
                    'cause' => $cause->value,
                    'units_shortfall' => $shortfall,
                ]);

                return CreditClawbackOutcome::Shortfall;
            }

            return CreditClawbackOutcome::Clawed;
        });
    }

    /**
     * What this reversal is worth, in the purchase's own ledger units.
     *
     * ⚠️ **PROPORTIONAL, BECAUSE A PARTIAL REFUND IS A REAL THING ON BOTH
     * GATEWAYS** and `charge.refunded` fires for one. Integer arithmetic
     * throughout: the units and the price are both integers on the row, and
     * nothing here touches a float — `18` §Money handling's rule, which the
     * neighbouring `authAmount()` converter exists to honour on the way in.
     *
     * ⚠️ **`intdiv` FLOORS, AND THE DIRECTION IS CHOSEN.** A rounding unit is
     * left with the tenant rather than taken from them, because this is a debit
     * against somebody and the safe error is the one that takes too little.
     *
     * ⛔ **AND IT IS CAPPED AT WHAT WAS GRANTED.** Stripe's own dispute reference
     * says the disputed amount *"can differ [from the charge] … usually because of
     * currency fluctuation or because only part of the order is disputed"*
     * (fetched 2026-08-20), so a reversal genuinely can exceed the price. Without
     * the cap a dispute a few cents over would claw back more units than the
     * purchase ever created.
     */
    private function unitsFor(CreditPurchase $purchase, Money $reversed): int
    {
        return min(
            $purchase->units,
            intdiv($purchase->units * $reversed->minorUnits, $purchase->price_cents),
        );
    }

    /**
     * The line the owner sees, in outcome language.
     *
     * ⛔ **IT NAMES THE CONSEQUENCE AND INVENTS NO DEBT.** `22`'s rule is that
     * every string names what the person controls; the two endings differ because
     * the facts differ, and the shortfall wording deliberately stops at *"had
     * already been used"* rather than stating a number of units owed. **This
     * product has no debt and must not imply one** — the balance is clamped at
     * zero (6385), so a sentence implying arrears would describe a state that
     * does not exist and would be the first thing support was asked about.
     *
     * ⚠️ **NO FIGURE AND NO CARD DETAIL**, on {@see CreditPurchases::credit()}'s
     * precedent: an activity payload is broadcast and holds no personal data.
     */
    private function tenantTitle(CreditPurchase $purchase, CreditReversalCause $cause, int $shortfall): string
    {
        $opening = 'Your '.$purchase->product->value.' credit top-up '.$cause->tenantWording();

        return $shortfall > 0
            ? $opening.', and the credit it bought had already been used'
            : $opening.', so that credit has been removed';
    }
}
