<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\MessageCostKind;
use App\Models\InboundMessage;
use App\Models\MessageCostEntry;
use App\Services\Sms\TenantNumbers;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The one place this application records what a message actually cost us
 * (T137 R9, decision 2134).
 *
 * ⛔ **INTERNAL. NOT RETAIL. NEVER SHOWN TO A TENANT AS A PRICE.** R9 sets two
 * books beside each other; what the tenant is charged lives in `credit_ledger`
 * and is `App\Services\Billing\SmsCreditUnits`' arithmetic. ⛔ **The figure is
 * deliberately not repeated here (12487)** — this paragraph has carried three
 * different ones (R9's *"each exactly one"*, 9182's text-and-media, 12461's
 * `ceil(characters / 160) + photos`) and the sentence around them never
 * changed. ⚠️ **9182 moved that book and left
 * this one alone, in terms**: *"the internal cost book is untouched … the two
 * books stay two books."* This book is provider cost in
 * **millicents** (3729), for margin visibility, and R9's own words are *"never
 * shown as retail, never hardcoded"*.
 *
 * An `ArchitectureTest` lint makes this the only file in `app/` permitted to
 * touch `MessageCostEntry` at all, on `CreditLedger`'s and `AuditService`'s
 * precedent (625). That is what turns "never shown" from an instruction into a
 * property: a resource, a Livewire component or an export cannot reach the
 * model, so the wrong answer is not one somebody can reach for in a hurry.
 *
 * ⚠️ **NO RATE LIVES HERE.** This class records a cost it is given; it does not
 * know what a segment costs, and it must not learn. "Never hardcoded" means the
 * figure comes from the rate schedule, which is L2's — a constant in this file
 * would be a carrier price in the one place nobody greps for prices, and it
 * would go stale the first time a carrier moved.
 *
 * ✅ **EVERY ONE OF THE SIX KINDS NOW HAS A WRITER, AS OF 2026-08-17**
 * (4800–4805). `PlatformMessageSender` writes outbound SMS and MMS;
 * `ReviewInviteSender` writes outbound email (3730) **and, since 4802, its own
 * outbound SMS** — 2976, closed by extracting the segment estimator to
 * `SmsSegments` rather than copying it; `InboundMessages` writes inbound SMS and
 * inbound MMS (4804); `DeliveryReceipts` writes the undelivered fee (4805). The
 * last two are 2549's named seams.
 *
 * ⛔ **AND THIS PARAGRAPH SAID "FOUR OF THE SIX KINDS STILL HAVE NONE" WHILE
 * NAMING THREE, WHICH IS WORTH LEAVING VISIBLE** (4806). The "four" was correct
 * when the enum had five cases and `OutboundEmail` did not exist; 3728 added the
 * sixth **with** its writer and carried the old figure forward, this docblock
 * repeated it, and 4689(a) repeated it again from here — so one off-by-one
 * travelled through three documents and into a work order. **The correct figure
 * was three, and 2555 said three at the time.** 2505's shape at its most
 * ordinary: a sentence that was true when written, restated as a fact about
 * today.
 *
 * ⚠️ **A WRITER IS NOT A ROW, AND EVERY RATE STILL SEEDS TO ZERO** (2547, 3731).
 * Zero means unset rather than free, {@see MessageRates::costFor()} answers null
 * and each writer returns without writing — so **the book stays empty until an
 * operator enters the figures**, and no send, receipt or inbound message is
 * refused or delayed over it. ⛔ **The undelivered fee needs TWO figures rather
 * than one and is the only kind that does**: a rate, and
 * `messaging.carrier_billable_undelivered_groups`, because a rate cannot say
 * *which* failures a carrier bills for (4805).
 *
 * ⛔ **SO "SIX WRITERS" MUST NOT BE READ AS "THE MARGIN BOOK WORKS".** What
 * changed is that entering a rate now produces rows for every kind, where before
 * it produced rows for three and silence for the rest — silence indistinguishable
 * from "that kind costs nothing", on the book whose only job is margin.
 *
 * ⛔ **AND IT IS THE ONLY FILE THAT MAY READ THE BOOK, WHICH IS WHY THE
 * "IS THIS PRICED AT ALL" GUARD LIVES HERE AND NOT ON A SCREEN** (3200, 3201).
 * The chokepoint lint above means no resource, component, job or export can
 * reach `MessageCostEntry`, so every present and future reader of the total
 * arrives through {@see self::totalCost()}. Putting the guard on each surface
 * would be a rule each new surface has to remember; putting it here makes an
 * unguarded read unspellable — the total comes back as a {@see CostBookTotal}
 * that has to be asked whether it means anything before it will give up a
 * number.
 *
 * ## Finding the rows that were lost rather than never owed (3898)
 *
 * ⛔ **A LOST COST ROW HAS ONE `Log::warning` BEHIND IT AND NOTHING ELSE.** Both
 * writers swallow every failure so that bookkeeping can never stop a message
 * (2547, 3881), which means the book can be short and look complete. The two
 * causes are not hypothetical: the migration's own docblock names a deploy
 * window in which every insert raises `42703` because the code says
 * `cost_millicents` at a table that still says `cost_cents` (3891), and a
 * deadlock on a busy `insertOrIgnore` is the other.
 *
 * ⚠️ **NO COUNTER AND NO METRIC, DELIBERATELY** (3898). This application has no
 * metrics facility to hang one on — ⛔ **this said "Telescope ships deliberately
 * undiscovered" until 2026-08-20, when Telescope was removed outright rather
 * than published (6320–6326); the Observability module is still unbuilt and the
 * conclusion is unchanged** — so a "counter" would be a new
 * durable store with one writer and no reader, which is 272's shape and the
 * exact failure this lane spent its first commit closing. What is shipped
 * instead is the query, because it needs no infrastructure and it is what an
 * operator would otherwise have to derive under time pressure:
 *
 * ```sql
 * -- Sends with no cost row, for a tenant, since a deploy.
 * -- Run as the owner role; `business_id` is explicit because this is a
 * -- reconciliation and not a request.
 * SELECT o.id, o.channel, o.created_at
 *   FROM outreach_messages o
 *   LEFT JOIN message_cost_entries c
 *     ON c.ref_type = 'outreach_message' AND c.ref_id = o.id
 *  WHERE o.business_id = :business_id
 *    AND o.created_at >= :since
 *    AND c.id IS NULL
 *  ORDER BY o.created_at;
 * ```
 *
 * ⚠️ **A NON-EMPTY RESULT IS NOT YET A DEFECT**, and reading it as one is the
 * mistake to avoid. Every rate seeds to `0` and an unset rate writes no row at
 * all — so the rows this returns are *candidates*. Check
 * {@see MessageRates::pricedKinds()} for the period first, then the log lines,
 * which carry `business_id` and our own ids and never a recipient.
 *
 * ⚠️ **THE THIRD EXCUSE THIS PARAGRAPH LISTED IS GONE AND THE QUERY IS SHARPER
 * FOR IT** (4802). It read *"and the review invite's own SMS books nothing by
 * design (2976)"*, which meant a whole send path was expected to be missing and
 * an operator had to know that before reading the output. Both of that path's
 * channels book a cost now, so an outreach row with no cost row is either an
 * unpriced kind or a real gap — two possibilities rather than three.
 */
final class MessageCostLedger
{
    /**
     * @param  MessageRates  $rates  The rate schedule, consulted on the read
     *                               path rather than only on the write path.
     *                               ⚠️ **Defaulted rather than required**, on
     *                               `MessageRates`' own `DefaultsRegistry`
     *                               precedent, so that `new MessageCostLedger`
     *                               keeps working everywhere it already appears
     *                               — a required argument here would have been a
     *                               refactor of every call site dressed up as a
     *                               safety fix.
     */
    public function __construct(
        private readonly MessageRates $rates = new MessageRates,
    ) {}

    /**
     * Record one provider cost.
     *
     * ⚠️ **`$idempotencyKey` IS REQUIRED AND HAS NO DEFAULT, WHICH IS §3 RAIL 1
     * EXPRESSED AS A SIGNATURE.** A delivery receipt can arrive twice and a job
     * can be retried; both would otherwise write the same carrier cost twice and
     * make margin look worse than it is, permanently, in a table nobody
     * reconciles against a statement. Defaulting it to a UUID would make every
     * call idempotent-looking and none of them idempotent.
     *
     * Returns false when the key had already been recorded — the caller's signal
     * that this was a redelivery, not an error.
     *
     * @param  int  $costMillicents  **Thousandths of a cent, not cents** (3729),
     *                               and normally {@see MessageRates::costFor()}'s
     *                               answer passed straight through. Signed: a
     *                               carrier credit — a fee billed and then
     *                               reversed — is genuinely negative, and the
     *                               alternative is editing the original row,
     *                               which this table forbids. ⚠️ **The parameter
     *                               was a `Money` until 3729**, so a caller that
     *                               has not moved would understate its own costs
     *                               by a thousandfold rather than fail; nothing
     *                               in `app/` is in that position, because the
     *                               two callers both take the figure from the
     *                               schedule.
     */
    public function record(
        MessageCostKind $kind,
        int $costMillicents,
        string $idempotencyKey,
        ?int $segments = null,
        ?string $refType = null,
        ?int $refId = null,
    ): bool {
        Tenancy::idOrFail();

        $this->refuseIncoherent($costMillicents, $idempotencyKey, $segments, $refType, $refId);

        /*
         * The claim is an INSERT, not a check-then-insert — decision 350's
         * lesson, that the second form holds only sequentially. Two workers
         * handling one redelivered receipt are exactly that race, and
         * `insertOrIgnore` on the unique key is what makes the second one a
         * no-op rather than a duplicate charge.
         *
         * ⚠️ **AND THAT IS WHY EVERY COLUMN IS WRITTEN OUT HERE INSTEAD OF
         * GOING THROUGH THE MODEL.** `insertOrIgnore` goes straight to the query
         * builder, so nothing fills `business_id` from the tenant, nothing
         * stamps `created_at`, and no cast converts the enum — all three of
         * which a `save()` would have done. The first version of this method
         * built a model and passed its attributes, and the timestamp was
         * missing: caught by this slice's own test rather than in production,
         * which is the only reason it is a comment and not an incident.
         */
        return MessageCostEntry::query()->insertOrIgnore([
            'business_id' => Tenancy::idOrFail(),
            'kind' => $kind->value,
            'cost_millicents' => $costMillicents,
            // ⚠️ **THE SCHEDULE'S CURRENCY, NOT THE CALLER'S** (3729). It used
            // to ride along inside the `Money`; asking the schedule that priced
            // the row removes the one way the two could disagree — a row
            // labelled with a currency its rate was never quoted in.
            //
            // ⛔ **AND IT IS VALIDATED THERE, WHICH IT WAS NOT WHEN 3729 MOVED
            // IT** (3887). `MessageRates::currency()` reads an Ops-editable free
            // text field, and `char(3)` **pads** a two-letter code rather than
            // refusing it — so `'US'` inserted cleanly and then vanished from
            // `totalCost('USD')`, which answered zero with `priced` true. It now
            // throws instead, and 3881 is what keeps that throw from taking a
            // customer's message with it.
            'currency' => $this->rates->currency(),
            'segments' => $segments,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'idempotency_key' => $idempotencyKey,
            'created_at' => Carbon::now(),
        ]) === 1;
    }

    /**
     * What this tenant has cost us, as a total — and whether that total means
     * anything.
     *
     * ⚠️ **A READER WITH NO SCREEN BEHIND IT IS DECISION 620's INVERSION AND
     * THIS ONE IS DELIBERATE.** `audit_log` spent this project's whole life with
     * eight writers and no reader, invisible because every writing test passed.
     * This method exists so that the margin question has an answer to ask, and
     * the admin screen that asks it is L7's. **It is not a tenant-facing figure**
     * and the lint above is what keeps it from becoming one by accident.
     *
     * ⛔ **IT ANSWERS A {@see CostBookTotal} RATHER THAN A `Money`, AND THAT IS
     * THE POINT OF THIS SLICE** (3200). Returning cents meant an unconfigured
     * platform answered `0` — the same answer as a tenant who has sent nothing,
     * from a table that cannot tell the two apart because an unset rate writes no
     * row at all (3104, 3105). The wrapper makes the difference part of the
     * answer instead of part of the reader's job.
     *
     * ⚠️ **THE UNPRICED BRANCH DOES NOT RUN THE QUERY**, deliberately: summing a
     * book whose rows could not have been written is arithmetic on an artefact,
     * and returning that sum beside a `false` would invite somebody to use it
     * "just for now".
     *
     * ⛔ **IT TAKES NO CURRENCY, AND IT USED TO** (3894). The caller passed one,
     * the `where` filtered on it, and the rows were labelled from
     * {@see MessageRates::currency()} — **two sources for one fact**. Setting
     * `messaging.carrier_cost_currency` to `EUR`, a legal validated value any
     * operator may enter, then made `totalCost('USD')` answer `$0.00` with
     * `priced` **true**: a wrong number that renders perfectly, on the ledger
     * whose only job is margin. That is 3105's failure mode reached by a
     * different road, inside the type built to close it. **There is exactly one
     * schedule and therefore exactly one currency this book can be in**, so the
     * parameter could only ever be right by agreeing with the schedule or wrong
     * by disagreeing with it.
     *
     * ⚠️ **IT NOW THROWS ON A CURRENCY NOBODY CAN SPELL, AND THAT IS THE RIGHT
     * DIRECTION HERE** — this is a read, not a send. 2547's *"a bookkeeping gap
     * must never stop a message"* governs the write path, where the throw is
     * caught and logged (3881); a report is allowed to refuse rather than quote
     * a figure whose unit nobody can name.
     *
     * ⛔ **THE SUM HAS NO KIND PREDICATE AND MUST NOT GAIN ONE** (3893). See
     * {@see CostBookTotal::unpricedKinds()} — the list describes the *schedule*
     * and never the rows, so a kind whose rate was cleared after its rows were
     * written is named as unpriced **while its spend stays in this total**.
     * Filtering it out would drop real recorded spend and understate the book,
     * and understating is the direction {@see CostBookTotal} already refuses.
     *
     * @uncalled 3201, restated at 3741, 3894, 4755(b) and 4928 - L7's screen is
     *   not built, and building one here to give the guard something to guard
     *   would be inventing a consumer for a mechanism, which is 272's shape
     *   with the arrow reversed. The tag is 9163's and adds no ruling: it is
     *   here so `ops:method-callers` reports a five-times-argued decision as a
     *   decision rather than as news, and so the day something calls this the
     *   note is stale and the build says so.
     */
    public function totalCost(): CostBookTotal
    {
        Tenancy::idOrFail();

        // ⚠️ **THE SCHEDULE'S CURRENCY, WHICH IS THE CALL THE WRITE PATH MAKES**
        // (3894). One source, so the filter below cannot disagree with the label
        // on the rows it is filtering.
        $currency = $this->rates->currency();

        // ⚠️ THE SCHEDULE, NOT THE ROWS. See `CostBookTotal` for why a cleared
        // rate makes an existing book unreadable rather than merely stale.
        //
        // ⚠️ **THE LIST RATHER THAN THE FLAG SINCE 3888**, so a reader can tell a
        // whole book from one that prices email and nothing else — the
        // configuration this platform can actually reach today (3731).
        $pricedKinds = $this->rates->pricedKinds();

        if ($pricedKinds === []) {
            return CostBookTotal::unpriced($currency);
        }

        // ⚠️ **SUMMED IN MILLICENTS AND ROUNDED ONCE, INSIDE `CostBookTotal`**
        // (3729). Rounding each row on the way in — which is what the schedule
        // used to do — costs up to a cent per row, and the rows this book now
        // carries are individually worth a hundredth of one.
        $millicents = (int) MessageCostEntry::query()
            ->where('currency', $currency)
            ->sum('cost_millicents');

        return CostBookTotal::priced($millicents, $currency, $pricedKinds);
    }

    /**
     * How many inbound messages this book could not attribute to anybody — 4810,
     * closed as a **reader** at 4925.
     *
     * ⛔ **THE HOLE IT MEASURES IS REAL AND IS TODAY THE COMMON CASE.**
     * `InboundMessages::recordCost()` resolves the tenant from *our* receiving
     * number (R8's reverse lookup) and returns without writing when that answers
     * null — which is every message arriving on the shared Lane A pool number,
     * because that number belongs to nobody by design. The cost is real, the
     * carrier billed it, and it is booked nowhere. **A flat line on the inbound
     * book is therefore not evidence of quiet customers**, and until this method
     * existed nothing could tell the two apart.
     *
     * ## Why this is not a new table, having actually weighed the one offered
     *
     * ⛔ **`voice_usage_events` IS THE PRECEDENT AND IT DOES NOT TRANSFER,
     * WHICH IS WORTH WRITING DOWN BECAUSE IT LOOKS LIKE IT SHOULD.** That table
     * is platform-scoped with a nullable `business_id` for the same reason this
     * gap exists — *"the rows that matter most have no tenant"* — and it is the
     * shape a reader of 4810 would reach for first. **Its load-bearing argument
     * is a ceiling**: 4686 built it so `VoiceSpend` could *enforce* against
     * unattributed minutes, and its own docblock says *"a budget that cannot see
     * its own spend is not a budget."* ⚠️ **There is no ceiling here.** The
     * margin book records what a message cost **us**; 3297's enumeration is
     * about what bounds a **tenant's** spend, and conflating them makes a solved
     * problem look open and an open one look solved (4689(a)). Nothing about
     * this figure gates anything.
     *
     * ⛔ **AND THE ROWS ALREADY EXIST.** Every unattributable inbound message
     * writes an `inbound_messages` row — that table is deliberately un-tenanted
     * for the same reason (*"an inbound STOP has no tenant"*), so it already
     * holds exactly the population a new table would have re-counted. **A second
     * durable count of one fact is 2976's drift defect on the one book whose
     * purpose is reconciliation**, and it would have needed its own migration,
     * its own RLS exemption in `TenancyTest`'s `$exempt`, and its own writer on
     * a webhook path where 2547 forbids bookkeeping from ever costing a message.
     *
     * ⛔ **MAKING `message_cost_entries.business_id` NULLABLE WAS THE OTHER
     * TEMPTING ANSWER AND IT IS THE ONE W12 ALREADY REFUSED** (4810): the table
     * is tenant-owned, RLS-`ENABLE`d and `FORCE`d, and its only read path is
     * per-tenant, so an untenanted row would belong to nobody, be invisible to
     * `totalCost()` under the policy, and quietly weaken the boundary to carry a
     * statistic.
     *
     * ## What is given up, said plainly
     *
     * ⚠️ **A COUNT AND NOT A COST, BECAUSE A COST WOULD BE A FICTION.** Pricing
     * these needs a rate nobody has entered (2547) and, on the SMS half, the
     * per-part contract term 4924 leaves withheld — so a money figure here would
     * be two guesses multiplied. The count is the honest ceiling on how wrong
     * the book can be.
     *
     * ⚠️ **AND IT HAS NO SCREEN, WHICH IS EXACTLY {@see self::totalCost()}'s OWN
     * POSITION.** That method has no caller in `app/` either; the cost book's
     * reader is L7's (4813(b), 620's inversion). This is not a control shipped
     * without a writer (272) — it is a *read* that completes the surface the
     * screen will consume, and it would be worse to ship the total without the
     * qualifier beside it than to ship neither.
     *
     * ⚠️ **THE RESOLUTION IS `TenantNumbers::tenantFor()` AND MUST STAY SO.**
     * Reimplementing "which business owns this number" as a join here would be a
     * second answer to a question that already has one, and the two would drift
     * exactly as two segment estimators would. It is resolved once per *distinct*
     * receiving number rather than once per message, which is a handful of
     * lookups however busy the platform is. Pulled from the container rather
     * than promoted into the constructor, because `new MessageCostLedger` with
     * no arguments is this class's documented shape and four call sites rely on
     * it.
     *
     * ⚠️ **A NULL `to_number` COUNTS.** Some inbound payloads omit the receiving
     * number entirely; `recordCost()` returns on that too, so the message is
     * just as unbooked and leaving it out would understate the gap — which is
     * the one direction this whole family of types refuses (3105).
     */
    public function unattributedInboundCount(): int
    {
        $numbers = app(TenantNumbers::class);

        // ⚠️ **`toBase()` BECAUSE THE SELECT IS AN AGGREGATE AND NOT A MODEL.**
        // `received` is not a column on `InboundMessage`, and hydrating rows to
        // read a count would allocate one object per inbound message ever
        // received. Scopes are applied on the way down, so nothing is widened.
        /** @var Collection<int|string, int> $byNumber */
        $byNumber = InboundMessage::query()
            ->selectRaw('to_number, count(*) as received')
            ->groupBy('to_number')
            ->toBase()
            ->pluck('received', 'to_number');

        $unattributed = 0;

        foreach ($byNumber as $toNumber => $received) {
            $toNumber = is_string($toNumber) ? trim($toNumber) : '';

            if ($toNumber === '' || $numbers->tenantFor($toNumber) === null) {
                $unattributed += (int) $received;
            }
        }

        return $unattributed;
    }

    /**
     * The refusals that belong above the database.
     *
     * Every one is also a CHECK constraint. Both layers are wanted for decision
     * 216's reason — the constraint catches the repair script that reached
     * neither the enum nor this method, and this gives a caller an error naming
     * the rule instead of SQLSTATE 23514.
     */
    private function refuseIncoherent(
        int $costMillicents,
        string $idempotencyKey,
        ?int $segments,
        ?string $refType,
        ?int $refId,
    ): void {
        if ($costMillicents === 0) {
            throw new InvalidArgumentException(
                'A cost of zero millicents records nothing. It is what an unconfigured rate '
                .'schedule returns, and afterwards "we do not know" cannot be told apart from '
                .'"it was free" — on the ledger whose whole job is measuring margin.'
            );
        }

        if (trim($idempotencyKey) === '') {
            throw new InvalidArgumentException(
                'A cost entry needs an idempotency key. Without one a redelivered delivery '
                .'receipt writes the carrier\'s charge twice, in a table nobody reconciles.'
            );
        }

        if ($segments !== null && $segments < 1) {
            throw new InvalidArgumentException(
                'A segment count of zero is a claim that a message had no segments, which '
                .'is a different and false statement from not knowing. Pass null.'
            );
        }

        if (($refType === null) !== ($refId === null)) {
            throw new InvalidArgumentException(
                'A reference is both ref_type and ref_id or neither. Half of one is a '
                .'pointer at nothing, and it fails at read time rather than here.'
            );
        }
    }
}
