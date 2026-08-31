<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\MessageCostKind;
use App\Services\Config\DefaultsRegistry;
use App\Support\Money;

/**
 * The provider rate schedule — what a message costs **us** (T137 R9,
 * decision 2546).
 *
 * `MessageCostLedger`'s docblock names this class's absence in terms: *"NO RATE
 * LIVES HERE. This class records a cost it is given; it does not know what a
 * segment costs, and it must not learn. 'Never hardcoded' means the figure comes
 * from the rate schedule, which is L2's."* This is that schedule, and it is the
 * only thing in `app/` that turns a message into a number of cents.
 *
 * ⛔ **INTERNAL. NOT RETAIL. NOT A PRICE.** What the tenant is charged does not
 * turn on this figure at all, whatever this answers — see {@see SendCredits}.
 * ⛔ **THE RETAIL RULE IS NOT RESTATED HERE AND THAT IS THE REPAIR (12487)**: it
 * read *"one credit per send — an SMS, an MMS, or the SMS+MMS pair are each
 * exactly one"* until 9182 reversed it, and *"one for the text and one for the
 * media"* until 12461 reversed it again; the independence of the two books is what the paragraph
 * was for and is unchanged, and 9182 says so in terms — *"the internal cost book
 * is untouched … the two books stay two books"*. R9's words about this one are
 * *"never shown as retail, never hardcoded"*.
 *
 * ## Millicents, and why the unit is not cents
 *
 * ⚠️ **A CARRIER SMS COSTS LESS THAN A CENT AND `Money` CANNOT SAY SO.**
 * `Money` is integer minor units, so a rate expressed in cents is either `0` —
 * which the ledger's own CHECK refuses, correctly, because *"we do not know"
 * reading as "it was free" is the error that makes the whole table say the wrong
 * thing* — or `1`, which overstates a sub-cent send by a third or more.
 * Infobip's own model says as much: `MessagePrice::$pricePerMessage` is a
 * **float**, which is a vendor telling you the unit is finer than a cent.
 *
 * So the schedule is in **millicents** — integer thousandths of a cent, so 750
 * is $0.0075 — and it stays in millicents all the way into the book.
 *
 * ✅ **THIS CLASS NO LONGER CONVERTS TO CENTS, AND 2546's DELIBERATE
 * OVERSTATEMENT IS GONE WITH THE CONVERSION** (3729). It used to round each
 * message to cents and floor the result at 1, so that a sub-cent send became a
 * row rather than a zero the CHECK refuses — an argued 33% overstatement on an
 * SMS. ⛔ **On email the same floor overstated by a hundredfold**: SES charges
 * on the order of 10 millicents an email against 3299's 2-cent retail price, so
 * a ~200:1 product would have been booked at roughly 2:1. `message_cost_entries`
 * now stores millicents, the ledger's total converts **once, on the whole
 * book**, and the reason is the one 2546 already gave for converting per message
 * rather than per segment: rounding early and often is what loses the money.
 *
 * ## Every rate seeds to zero, and zero means "unset" rather than "free"
 *
 * ⛔ **NOTHING HERE GUESSES A VENDOR'S PRICE.** `CLAUDE.md` records four
 * separate burns from writing a plausible vendor figure from memory (255, 277,
 * 684, 1349) and the rule it draws is to verify against the raw artefact.
 * Infobip's US per-message rate is **per account** and is not in the generated
 * client, the API reference, or anything else this lane could read — so it is
 * the owner's to set, exactly as `messaging.platform_complaint_trip_bp` is
 * (2119). **The mechanism is the deliverable; the number is not ours.**
 *
 * ⚠️ **THE EMAIL RATE SEEDS TO ZERO FOR A DIFFERENT REASON, AND IT IS WORTH
 * KNOWING WHICH** (3731). Amazon SES publishes its price, and it was read rather
 * than remembered: `https://aws.amazon.com/ses/pricing/`, read 2026-08-14, which
 * quotes **$0.10 per 1,000 outbound emails à la carte** — the figure
 * `CLAUDE.md` carries as *"roughly $1/10,000"* — **and three plan tiers above
 * it**, Essentials at $0.16, Pro at $0.22 and Enterprise at $0.23 per 1,000 in
 * their first band, the last two with a fixed monthly fee. **Which of the four
 * applies is a fact about an account this platform does not yet have**
 * (`SUBPROCESSOR-INVENTORY.md` §2 lists SES as a dormant driver), and they
 * differ by more than a factor of two. Seeding the cheapest would have been the
 * plausible wrong figure this file exists to refuse.
 *
 * ⚠️ **THE CONSEQUENCE, SAID PLAINLY: UNTIL AN OPERATOR SETS A RATE, THE
 * INTERNAL COST BOOK STAYS EMPTY** (2547). {@see self::costFor()} answers null,
 * the sender writes no cost row, and no send is refused or delayed over it —
 * because a bookkeeping gap must never stop a message. This is written down
 * rather than left to be found, because a ledger with a writer that never fires
 * is `CLAUDE.md`'s writerless-control shape wearing a service's clothes, and the
 * only thing separating this from that is somebody entering four numbers.
 *
 * ✅ **AND THE READ SIDE NOW SAYS SO INSTEAD OF SAYING ZERO** (3200).
 * `MessageCostLedger::totalCost()` consults {@see self::isConfigured()} and
 * answers a `CostBookTotal` that refuses to give up a number until it is asked
 * whether the platform can price anything at all. The empty book is still empty;
 * what changed is that it can no longer be read as "$0 spent" (3105).
 */
final class MessageRates
{
    public function __construct(
        private readonly DefaultsRegistry $defaults = new DefaultsRegistry,
    ) {}

    /**
     * What one message of this kind cost us **in millicents**, or null when
     * nobody has said.
     *
     * ⚠️ **THE RETURN IS NOT `Money` AND THAT IS THE WHOLE OF 3729.** It was,
     * and `Money` is integer cents, so this method had to round every message
     * before the ledger ever saw it. The unit now travels intact to the column
     * and the rounding happens once, on the total, in {@see CostBookTotal}.
     *
     * @param  int|null  $segments  Carrier segments, for the kinds billed per
     *                              segment. ⚠️ **Null is "not a segmented
     *                              product", never "one segment"** — MMS is
     *                              billed per message, an email is one email,
     *                              and defaulting it to 1 would price a
     *                              three-segment SMS as one the moment a caller
     *                              forgot to count.
     */
    public function costFor(MessageCostKind $kind, ?int $segments = null): ?int
    {
        $millicents = $this->rateFor($kind);

        if ($millicents <= 0) {
            // Unset. Not free — see the class docblock. The caller writes
            // nothing rather than writing a zero the ledger would refuse.
            return null;
        }

        // ⚠️ **THE MULTIPLIER IS THE SEGMENT COUNT ONLY WHERE THE CARRIER BILLS
        // PER SEGMENT.** `MessageCostKind::OutboundMms` carries a media fee per
        // *message* and `OutboundEmail` is billed per email, so multiplying
        // either by a segment count somebody happened to pass would invent a
        // charge the vendor never made.
        //
        // ⛔ **AND INBOUND SMS IS THE ONE CASE WHERE WE DO NOT KNOW** — 4811,
        // answered at 4924. Infobip's inbound webhook carries `smsCount`, *"the
        // number of parts the message content was split into"*, beside a
        // `pricePerMessage` described as *"Price per one SMS"*
        // (`https://www.infobip.com/docs/api/channels/sms/inbound-sms/receive-inbound-sms-messages.md`,
        // read 2026-08-18) — **and states no billing rule joining the two.**
        // Reading "per one SMS" as "per part" is plausible, and a plausible
        // vendor figure is what 255, 277, 684 and 1349 each were. So the
        // contract fact is an operator's to state, seeded false, which books
        // per message: **short rather than wrong**, 4805's own distinction on
        // the identical question about the same unread rate card.
        $units = match (true) {
            $kind === MessageCostKind::OutboundSms => max(1, $segments ?? 1),
            $kind === MessageCostKind::InboundSms && $this->billsInboundPerSegment() => max(1, $segments ?? 1),
            default => 1,
        };

        return $millicents * $units;
    }

    /**
     * Whether this platform can price anything at all.
     *
     * ⚠️ **A READER FOR THE GAP, SO THE GAP IS ASKABLE RATHER THAN INFERRED.**
     * Every rate seeding to zero is a deliberate unset state, and the difference
     * between "configured and cheap" and "never configured" is invisible from
     * the cost table itself, which looks identical in both cases: empty.
     *
     * ✅ **AND IT HAS A CALLER NOW** (3200, 3201). It sat here with none — 3105
     * found it and said so in terms — while the only read of the cost book
     * answered a bare `Money`, so an unpriced platform reported a spend of zero.
     * `MessageCostLedger::totalCost()` asks this first, and it is the *only*
     * caller by design: the chokepoint lint keeps `MessageCostEntry` reachable
     * from that one file, so guarding it there guards every reader there will
     * ever be, rather than every reader remembering.
     *
     * ⚠️ **"ANY RATE" IS COARSER THAN IT LOOKS, AND SINCE 3888 THE ANSWER TRAVELS
     * BESIDE IT RATHER THAN BEING RE-DERIVED** (3202). A platform that has priced
     * SMS and not MMS still answers true here — the coarse question is the right
     * one for "does this book mean anything at all" — but {@see self::pricedKinds()}
     * is what a reader consults to tell a whole book from a partial one, and
     * {@see CostBookTotal} now carries it so the distinction reaches the reader
     * rather than staying in this file.
     *
     * ⚠️ **PARTIAL PRICING IS THE LIKELY STEADY STATE OF THIS BRANCH, NOT A
     * HYPOTHETICAL** (3888). Amazon SES publishes a price and Infobip's five are
     * per-account and unknowable (3731), so the configuration an operator can
     * actually reach today is email priced and the other five unset — a book that
     * answers `priced` while omitting the dominant cost, on the one ledger whose
     * job is margin.
     */
    public function isConfigured(): bool
    {
        return $this->pricedKinds() !== [];
    }

    /**
     * Which kinds this platform can actually price.
     *
     * ⚠️ **THE SHARP FORM OF {@see self::isConfigured()}, AND IT STILL CANNOT SAY
     * "GENUINELY FREE"** (3202). Zero means unset by convention, so a kind absent
     * from this list is one nobody has entered a rate for — never one a vendor
     * does not charge for. Nothing in the schedule has a spelling for the second,
     * and inventing one here would need the vendor contracts this platform does
     * not have.
     *
     * @return list<MessageCostKind>
     */
    public function pricedKinds(): array
    {
        $priced = [];

        foreach (MessageCostKind::cases() as $kind) {
            if ($this->rateFor($kind) > 0) {
                $priced[] = $kind;
            }
        }

        return $priced;
    }

    /**
     * The currency our providers bill us in.
     *
     * ⚠️ **NOT THE TENANT'S CURRENCY, AND THE TWO ARE ALLOWED TO DIFFER.** The
     * cost table carries its own `currency` column beside its minor units for
     * exactly this reason (2058 puts multi-currency in scope), and a carrier
     * bills in its own currency regardless of what a tenant pays in. Reusing a
     * billing currency here would silently convert nothing and label it wrongly.
     *
     * ⚠️ **PUBLIC SINCE 3729, AND READ BY THE LEDGER RATHER THAN PASSED TO IT.**
     * `costFor()` used to hand back a `Money`, which carried the currency along
     * with the amount; a bare integer of millicents cannot. Having
     * {@see MessageCostLedger} ask *the schedule that priced the row* removes
     * the one way the two could disagree — a caller labelling a row with a
     * currency the rate was not quoted in.
     *
     * ⛔ **VALIDATED HERE, BECAUSE MAKING THIS PUBLIC IS WHAT LOST THE
     * VALIDATION** (3887). Until 3729 the code travelled to the column inside a
     * `Money`, and `Money::of()` refuses anything that is not `^[A-Z]{3}$`.
     * Reading the registry directly dropped that check on the *write* path in
     * the same commit that added it to the read path — 314–316's shape, and the
     * value is free text: {@see DefaultsRegistry::set()} validates that a key is
     * declared and nothing about what an operator typed into it.
     *
     * ⚠️ **THE TWO FAILURES IT PREVENTS ARE NOT THE SAME SHAPE, AND THE FIRST IS
     * THE DANGEROUS ONE.** `'EURO'` is four characters into a `char(3)` and
     * throws `SQLSTATE 22001` — loud, and by 3881 no longer able to take a send
     * with it. `'US'` is two, and `char(3)` **pads it**: the insert succeeds, and
     * `totalCost('USD')` then filters on `'USD'`, misses every padded row, and
     * answers **zero with `priced === true`** — 3105's exact trap, arriving
     * through the fix for 3105.
     *
     * ⚠️ **EMPTY STILL MEANS `USD` AND ONLY EMPTY DOES.** An unset registry
     * string is the declared-but-never-entered state every key in this schedule
     * ships in; a *wrong* string is somebody's typo, and defaulting that one to
     * `USD` would silently relabel a book an operator believed was in euros.
     *
     * @throws \InvalidArgumentException when the configured code is not a
     *                                   three-letter ISO 4217 code. Callers on
     *                                   a send path must not let this stop a
     *                                   message — see {@see MessageCostLedger}.
     */
    public function currency(): string
    {
        $currency = $this->defaults->stringOrNull('messaging.carrier_cost_currency');
        $normalised = strtoupper(trim((string) ($currency === null || trim($currency) === '' ? 'USD' : $currency)));

        return Money::of(0, $normalised)->currency;
    }

    /**
     * Whether our carrier contract bills a multi-part inbound SMS per part.
     *
     * ⛔ **A CONTRACT FACT, NOT A FEATURE TOGGLE.** `CLAUDE.md` forbids adding
     * toggles and this is the same class of thing as
     * `messaging.carrier_billable_undelivered_groups` (4805) and as every rate
     * in this schedule: a term of an Infobip agreement this platform holds no
     * copy of, which nobody here may answer by plausibility. **Seeded false**,
     * because false books one part's rate per inbound message — the direction
     * that leaves the book *short* rather than *wrong*, and the only one a
     * recorded segment count can be used to correct afterwards.
     *
     * ⚠️ **IT DOES NOT AFFECT WHAT IS RECORDED, ONLY WHAT IS CHARGED.**
     * `InboundMessages` writes the carrier's own `smsCount` onto every inbound
     * SMS cost row whatever this answers, so flipping it is a recomputation over
     * rows that already carry the fact, rather than a change that only helps
     * from the day it is set.
     *
     * ⚠️ **INBOUND MMS IS NOT COVERED AND MUST NOT BE.** Its renderer carries no
     * part count at all (4259) and MMS is a per-message product on the outbound
     * side too, so there is nothing to multiply and nothing to decide.
     */
    private function billsInboundPerSegment(): bool
    {
        return $this->defaults->value('messaging.carrier_bills_inbound_sms_per_segment') === true;
    }

    /**
     * The configured rate for one kind, in millicents.
     *
     * A `match` rather than a computed key name, so that a seventh
     * {@see MessageCostKind} is a compile-time conversation rather than a
     * silently missing registry lookup returning zero — which would read as
     * "unset" and be indistinguishable from a rate nobody entered.
     *
     * ⚠️ **THE EMAIL KEY IS `provider_cost`, NOT `carrier_cost`, AND THE WORD IS
     * DOING WORK** (3732). Amazon SES is not a carrier and an email is not a
     * message a carrier ever sees; naming it `carrier_cost_outbound_email` would
     * have put an SES invoice under a heading an operator reconciles against an
     * Infobip statement. Everything else about it is the siblings' shape —
     * millicents, seeded zero, Ops-editable (3415).
     */
    private function rateFor(MessageCostKind $kind): int
    {
        return $this->defaults->intOr(match ($kind) {
            MessageCostKind::OutboundSms => 'messaging.carrier_cost_outbound_sms_millicents',
            MessageCostKind::OutboundMms => 'messaging.carrier_cost_outbound_mms_millicents',
            MessageCostKind::InboundSms => 'messaging.carrier_cost_inbound_sms_millicents',
            MessageCostKind::InboundMms => 'messaging.carrier_cost_inbound_mms_millicents',
            MessageCostKind::UndeliveredFee => 'messaging.carrier_cost_undelivered_fee_millicents',
            MessageCostKind::OutboundEmail => 'messaging.provider_cost_outbound_email_millicents',
        }, 0);
    }
}
