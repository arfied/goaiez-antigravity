<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where one attempt to buy credit got to (decision 3102's missing funder).
 *
 * ⛔ **THE WHOLE ENUM EXISTS BECAUSE THE MONEY AND THE CREDIT MOVE AT DIFFERENT
 * MOMENTS, AND MUST.** 2056 makes the webhook the source of truth on both
 * gateways, so a charge that succeeds synchronously has *not* yet credited
 * anybody — the credit is written when the notification arrives and verifies.
 * Between those two instants a purchase is {@see self::Pending}, and that state
 * is a real thing an operator can be shown rather than a gap in the record.
 *
 * ⚠️ **THE TWO UNHAPPY ENDINGS ARE DELIBERATELY NOT ONE STATE.**
 * {@see self::Failed} is "no money moved" and needs nobody. {@see self::Mismatched}
 * is **money moved and no credit was written**, because what the gateway says
 * was paid does not match the SKU this row was opened for — a person has to look
 * at it, and collapsing it into `failed` would hide a taken payment behind a word
 * that means the opposite.
 *
 * A string column cast to this enum, never a database enum type — `CLAUDE.md`'s
 * standing rule, with a CHECK naming the same five values (216's two layers).
 */
enum CreditPurchaseStatus: string
{
    /**
     * Opened, confirmed, and not yet paid for as far as this application knows.
     *
     * ⚠️ **A PENDING ROW IS NOT AN IDLE ROW.** On Stripe it means a Checkout
     * Session is open (or was abandoned). On Authorize.Net it may mean the charge
     * has already been *authorised and captured* — {@see self::Authorized} — and
     * it is only this state before that response comes back.
     */
    case Pending = 'pending';

    /**
     * The gateway took the money and the notification has not arrived yet.
     *
     * ⛔ **THIS IS THE ONE AN OPERATOR MUST BE ABLE TO SEE, AND IT IS THE STATE
     * "PAID BUT UNCREDITED" LIVES IN.** Authorize.Net publishes no webhook retry
     * schedule, so a notification that is lost is simply lost — the row keeps its
     * transaction id and its amount and says plainly that the money moved and the
     * credit did not. Stripe retries for three days, so on that gateway this state
     * is nearly always momentary.
     *
     * ⚠️ **IT IS NOT REACHED ON STRIPE AT ALL**, because Checkout takes the money
     * on Stripe's own page and the first thing we hear is the webhook. The
     * asymmetry is the vendors', not ours.
     */
    case Authorized = 'authorized';

    /**
     * Paid, verified against the SKU, and written to the top-up pool.
     *
     * The terminal happy state. `credit_purchases.credited_at` is set in the same
     * transaction as the `CreditKind::Purchase` row, and a partial unique index on
     * `credit_ledger` makes a second one impossible even if this column were
     * somehow wrong.
     */
    case Credited = 'credited';

    /**
     * The gateway refused, or could not be reached, and no money moved.
     *
     * Terminal and uninteresting: the tenant tries again, which opens a new row.
     * A retry deliberately does **not** reuse this one — a second attempt is a
     * second operation and needs its own idempotency handle at the vendor.
     */
    case Failed = 'failed';

    /**
     * Money moved and the credit was refused, because the amount paid is not the
     * SKU's price.
     *
     * ⛔ **NOTHING IS CREDITED ON THIS PATH AND THAT IS THE POINT.** Crediting
     * whatever quantity the row asked for, at whatever price the notification
     * happened to carry, is a free-credit hole: a caller who could name the price
     * could name a cent and receive ten thousand messages. So the two are compared
     * and a disagreement stops the credit rather than rounding it.
     *
     * ⚠️ **IT NEEDS A PERSON, WHICH IS WHY IT IS NOT `failed`.** Somebody has been
     * charged. The row carries the amount claimed and the amount expected, and a
     * support operator settles it with an `Adjust` or a refund.
     */
    case Mismatched = 'mismatched';

    /**
     * Whether this state means the money is gone from the customer's card.
     *
     * A `match` rather than a comparison so that a sixth state cannot inherit an
     * answer nobody chose — and here the answer decides whether somebody is owed
     * something.
     */
    public function moneyMoved(): bool
    {
        return match ($this) {
            self::Authorized, self::Credited, self::Mismatched => true,
            self::Pending, self::Failed => false,
        };
    }

    /**
     * Whether a settlement may still be applied to a row in this state.
     *
     * ⚠️ **`Credited` IS FALSE AND IT IS THE WHOLE IDEMPOTENCY QUESTION IN ONE
     * LINE.** Both gateways redeliver, both can deliver out of order, and the
     * conditional `UPDATE … WHERE status IN (settleable)` that claims a purchase
     * is what makes a replay a no-op rather than a second credit.
     */
    public function isSettleable(): bool
    {
        return match ($this) {
            self::Pending, self::Authorized => true,
            self::Credited, self::Failed, self::Mismatched => false,
        };
    }
}
