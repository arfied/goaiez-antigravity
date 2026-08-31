<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What incurred a line on the **internal** cost ledger (T137 R9, decision 2134).
 *
 * ⚠️ **THIS IS NOT THE CREDIT LEDGER AND MUST NEVER BE READ AS ONE.** R9 sets
 * two books beside each other and they answer different questions.
 * ⛔ **9182 MOVED THE RETAIL ONE AND LEFT THIS ONE ALONE, IN TERMS** — *"the
 * internal cost book is untouched … the two books stay two books"* — so this
 * enum, `message_cost_entries` and the rate schedule behind them are exactly as
 * they were. What changed is the line below it:
 *
 *   retail    `credit_ledger` — what the **tenant** is charged, and the only
 *             one of the two a tenant-facing screen may show. ⛔ **THE
 *             ARITHMETIC IS DELIBERATELY NOT RESTATED HERE — 2026-08-30
 *             (12487).** This line has carried it twice and been stale twice:
 *             R9's *"each = 1 credit"* until 9182, then *"one for the text and
 *             one for the media"* until 12461's `ceil(characters / 160) +
 *             photos`. **`App\Services\Billing\SmsCreditUnits` is the rule and
 *             `SendCredits` is the one place it is applied.** ⛔ **The property
 *             that has survived every repricing is the one worth keeping: the
 *             quantity turns on what the message CARRIED and never on which
 *             campaign sent it** — *"reactivation costs more"* is a consequence,
 *             because reactivation is where a pair is composed.
 *   internal  `message_cost_entries` — the **true provider cost**, in
 *             millicents: segments, MMS fees, billable inbound from the AI
 *             two-way conversation, fees on messages that never arrived, and —
 *             since 3728 — what our own email transport charges us. It exists
 *             for margin visibility.
 *
 * ⛔ **THE INTERNAL COST IS NEVER SHOWN AS RETAIL AND NEVER HARDCODED** — R9's
 * own words, and the money-number law. An `ArchitectureTest` lint keeps
 * `MessageCostEntry` reachable only from `App\Services\Billing\MessageCostLedger`
 * — spelled in prose rather than with an `@see` tag, because Pint promotes an
 * `@see` into a real `use` import and an enum importing a service is a
 * dependency nobody chose. That is the mechanical half of "never shown": a resource or a Blade template
 * cannot reach the model to print it.
 *
 * ⚠️ **THREE OF THE SIX CASES COST MONEY AND DEBIT NO CREDIT**, which is the
 * whole reason the two books cannot be one. A billable inbound message is a
 * carrier charge with no send behind it; an undelivered-message fee is a charge
 * for something the recipient never got. Folding either into the credit ledger
 * would bill a tenant for their customer replying to them.
 *
 * ⚠️ **"CARRIER" IS NOW THE WRONG WORD FOR ONE OF THEM AND THE CASE IS KEPT
 * HERE ANYWAY** (3728). {@see self::OutboundEmail} is billed by Amazon SES
 * rather than by a mobile carrier, and an email is not a message a carrier ever
 * sees. It belongs in this enum regardless, because the question this book
 * answers — *"what did serving this tenant cost us"* — is one question, and a
 * second parallel book for email would be a second thing to remember to sum.
 *
 * A string cast to a PHP backed enum, never a database enum — `CLAUDE.md`.
 */
enum MessageCostKind: string
{
    /**
     * An outbound SMS. Cost varies with segment count, which is why the entry
     * carries `segments` beside its cents rather than only a total.
     */
    case OutboundSms = 'outbound_sms';

    /** An outbound MMS. Carries a media fee on top of, or instead of, segments. */
    case OutboundMms = 'outbound_mms';

    /**
     * An inbound message the carrier bills us for.
     *
     * ⚠️ **THE AI TWO-WAY CONVERSATION IS WHAT MAKES THIS MATERIAL** (the
     * missed-call SMS agent). A one-shot notification has one inbound reply at
     * most; a conversation has as many as the customer wants to send, and every
     * one is a cost with no retail credit behind it. **It debits no credit** —
     * charging a tenant a credit because their customer replied is the wrong
     * product, and it would also be a charge driven by somebody else's behaviour.
     */
    case InboundSms = 'inbound_sms';

    /** An inbound MMS, for the same reason as above. */
    case InboundMms = 'inbound_mms';

    /**
     * A fee charged for a message that was not delivered.
     *
     * ⚠️ **A CARRIER STILL BILLS FOR SOME OF THESE AND R9 NAMES THEM EXPLICITLY**
     * ("fees on undelivered"). It is recorded as its own kind rather than folded
     * into the original send's entry, because the two arrive at different times —
     * the send is priced when it is submitted and the delivery receipt lands
     * later — and rewriting a cost row when the DLR arrives would make the ledger
     * mutable, which is the property it must not have.
     */
    case UndeliveredFee = 'undelivered_fee';

    /**
     * One platform email, priced by whatever transport carried it.
     *
     * ⛔ **OUR COST, AND THE ONE FIGURE MOST LIKELY TO BE CONFUSED WITH ITS
     * RETAIL TWIN.** The tenant is charged one *email unit* per send
     * (`App\Services\Billing\EmailCredits`, $20 per 1,000 — 3299). This case is
     * the other side of that trade: Amazon SES's own charge for the same send,
     * which decision 3411 recorded as *"recorded nowhere, while we sell email at
     * $20 per 1,000"* — 3105's trap in a second place, and the gap 3728 closes.
     *
     * ⚠️ **IT IS WHY THE BOOK IS DENOMINATED IN MILLICENTS RATHER THAN CENTS**
     * (3729). One email costs SES a hundredth of a cent or so, and the old
     * cents column had to floor a sub-cent send at 1 — a 33% overstatement on an
     * SMS and a hundredfold one here, which would have reported a ~200:1 product
     * as roughly 2:1 on the one book whose job is margin.
     *
     * ⚠️ **NO SEGMENTS, EVER.** An email is one email however long it is; a
     * segment count on one of these rows would be a claim no invoice supports.
     */
    case OutboundEmail = 'outbound_email';

    /**
     * Whether a send of this kind is the tenant's, and therefore has a retail
     * credit beside it.
     *
     * A `match` rather than a comparison so a seventh case cannot inherit an
     * answer nobody chose.
     *
     * ⚠️ **{@see self::OutboundEmail} ANSWERS TRUE, AND THE UNIT ON THE OTHER
     * SIDE IS AN EMAIL RATHER THAN AN SMS CREDIT** (3298's three allotments).
     * The question is whether a tenant-initiated send sits behind this cost, not
     * which pool paid for it.
     */
    public function isOutbound(): bool
    {
        return match ($this) {
            self::OutboundSms, self::OutboundMms, self::OutboundEmail => true,
            self::InboundSms, self::InboundMms, self::UndeliveredFee => false,
        };
    }
}
