<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Billing\CreditClawbacks;
use App\Services\Billing\CreditPurchases;

/**
 * What happened when a reversed payment met the credit it had bought.
 *
 * ⚠️ **THE MIRROR OF {@see CreditSettlement}, AND NOT A PERSISTED COLUMN EITHER.**
 * That enum records what one *settlement* attempt did; this records what one
 * *reversal* did, and a webhook handler turns both into a
 * {@see GatewayEventOutcome}. Keeping them apart matters for the same reason
 * `CreditSettlement` gives: the interesting answers are all *"we have a row for
 * it"* and they mean very different things.
 *
 * ⛔ **THREE OF THE SIX ARE SUCCESSES AND MUST BE ANSWERED 2xx.**
 * {@see self::Clawed}, {@see self::Shortfall} and {@see self::Nothing} are all
 * finished outcomes; a handler that treated a redelivery as a failure would ask
 * for another one, and Stripe would oblige for three days.
 */
enum CreditClawbackOutcome: string
{
    /**
     * The whole of what this reversal was worth came back out of the balance.
     *
     * The happy path, and the one that only exists when the tenant had not yet
     * spent what they bought.
     */
    case Clawed = 'clawed';

    /**
     * Some — possibly none — of what this reversal was worth came back, and the
     * rest had already been spent.
     *
     * ⛔ **THIS IS THE OUTCOME THAT COSTS US MONEY AND IT IS DELIBERATELY NOT
     * `Clawed`** (decision 6385). The balance is clamped at zero rather than
     * driven negative, so the shortfall is real spending we have paid a carrier
     * or a model provider for and will not be paid for. It carries the figure to
     * the audit entry and to a warning log, because the ledger cannot record a
     * movement that did not happen.
     */
    case Shortfall = 'shortfall';

    /**
     * This purchase has already had everything back that this reversal warrants.
     *
     * ⚠️ **THE SECOND IDEMPOTENCY LAYER SPEAKING** — the first is the gateway
     * event id. A redelivery under a *new* event id, or a second event describing
     * the same reversal, arrives here and moves nothing, because the clawback is
     * computed from what has already been clawed back rather than from the event.
     * See {@see CreditClawbacks::reverse()}.
     */
    case Nothing = 'nothing';

    /**
     * The purchase never reached `credited`, so there is no credit to remove.
     *
     * ⚠️ **THE OPPOSITE CASE FROM `PurchaseReconciliation`, AND THEY STAY APART**
     * (6396). That sweep handles *money taken, credit never granted* and says in
     * terms that *"returning money is not something this sweep does"*. This
     * handles *credit granted, money returned*. The overlap is a single instant —
     * a payment reversed before its own settlement notification arrived — and
     * this is the arm that meets it.
     *
     * ⛔ **NOTHING HERE MOVES THE PURCHASE'S STATUS.** `CreditPurchases::settle()`
     * claims `WHERE status IN ('pending','authorized')`, so a row this marked
     * terminal could never be credited by a notification that arrived afterwards
     * — `PurchaseReconciliation`'s *"the sweep must not be able to disarm the path
     * it exists to back up"*, read across. Decision 6396 records what that leaves
     * open.
     */
    case NotCredited = 'not_credited';

    /**
     * No purchase of ours matches this reversal.
     *
     * Ordinary rather than an error, exactly as {@see CreditSettlement::Unknown}
     * is: one merchant account serves whatever else it serves, and a refunded
     * *subscription* payment is not a top-up. ⚠️ **On Authorize.Net it is also
     * the ordinary answer for a refund we genuinely made**, because that vendor's
     * refund notification carries the refund's own transaction id rather than the
     * original's — 6393.
     */
    case Unknown = 'unknown';

    /**
     * A purchase matched and the amount reversed cannot be compared to its price.
     *
     * ⛔ **NEVER GUESS AN AMOUNT FOR A DEBIT.** A missing figure, a figure in a
     * currency this purchase was not sold in, or one the vendor's own format did
     * not yield exactly, all land here and move nothing. The mirror of
     * {@see CreditSettlement::Mismatched}: money moved, the comparison could not
     * be made, and a person has to look.
     */
    case Unreadable = 'unreadable';
}
