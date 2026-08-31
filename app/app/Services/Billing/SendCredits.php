<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\CreditKind;
use App\Enums\CreditPool;
use App\Enums\CreditProduct;
use App\Enums\MessageCostKind;
use App\Enums\OutreachPurpose;
use App\Exceptions\CreditMovementRefused;
use App\Models\CreditLedgerEntry;
use App\Models\OutreachMessage;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * **`ceil(characters / 160) + photos`** — the retail half of T137 R9 as the
 * owner settled it on 2026-08-30 (decision 12461), after 9182 and 9193.
 *
 * ⛔ **R9's RETAIL SENTENCE IS OVERRIDDEN AND THE OLD WORDING IS KEPT BECAUSE
 * THE ARGUMENT IS WHAT MOVED.** R9 read: *"1 credit per send — an SMS, an MMS,
 * or the SMS+MMS pair sent together on review reactivation each = 1 credit."*
 * The owner reversed the last clause on 2026-08-24: *"the text is a credit, the
 * media is a credit, a message carrying both is two"* — and was shown what it
 * costs before he agreed, which is why 9193 is a row of its own.
 *
 * ⚠️ **SO THE QUANTITY TURNS ON WHAT THE MESSAGE CARRIED AND NEVER ON WHICH
 * CAMPAIGN SENT IT.** *"Reactivation costs two"* is a consequence rather than
 * the rule: reactivation is simply where the pair is composed. **A text alone is
 * one. A text with a picture on it is two.** Nothing here reads a campaign kind,
 * a purpose or a channel to decide it.
 *
 * ⚠️ **AND THE DOCUMENT IS NOT EDITED.** `T137` still says what it said; what
 * changed is the ruling above it.
 *
 * ⛔ **THIS CLASS IS NOW BEHIND THE RULING RATHER THAN AHEAD OF IT — 2026-08-30
 * (12461), AND THE INSTRUCTION THAT STOOD HERE HAS BEEN CARRIED OUT.** It read
 * *"do not 'correct' this class back toward R9's sentence — read 9182 and 9193
 * first"*, and that is what happened: 9182 and 9193 were put to the owner with
 * what they cost in front of him, and **he moved.** The rule he settled on is
 *
 *     credits = ceil(characters / 160) + number_of_photos
 *
 * counted **by characters, uniformly** — an emoji is one character and there is
 * **no encoding branch** — which **confirms** 9182 for a short text carrying one
 * photo (still two) and **supersedes** it everywhere else: length now counts,
 * and **each** photo counts.
 *
 * ✅ **AND {@see self::creditsFor()} NOW IMPLEMENTS IT — BUILT 2026-08-30
 * (12480).** The arithmetic itself lives in `SmsCreditUnits`, not here, for one
 * reason: it must be **testable against `SmsSegments` without either class
 * importing the other**, because the two both count characters, both use 160,
 * and mean entirely different things. `CostBookWritersTest` asserts they
 * disagree on a real body.
 *
 * ⚠️ **THE ROW GAINED A QUANTITY AND LOST A FLAG.** `carried_media` was a
 * `?bool` and is now `media_count`, an `?int` — the boolean's own migration said
 * it was chosen to avoid inviting *"a pricing change no owner ruled on"*, and
 * that condition expired the day he ruled on it.
 *
 * ⛔ **`body` IS NOW LOAD-BEARING FOR THE CHARGE.** It was already the only
 * record of the exact words a stranger received; it is now also the only thing
 * that explains the size of the movement pointing at the row. **A sweep that
 * cleared it would silently take the explanation of every past charge with it**
 * — nothing prunes it today, and this sentence is what a lane adding a retention
 * period over this table has to answer.
 *
 * ⚠️ **160 IS THE OWNER'S SECOND ANSWER AND NOT A TRANSCRIPTION OF OURS.** He
 * first said 159; asked to confirm, he moved to 160. **159 is
 * `ReviewAskCatalog::CEILING`, a copy-writing budget with headroom for a link —
 * a writing guideline and never a billing unit**, and the two must not be
 * re-fused.
 *
 * ⚠️ **TWO CARRIER COSTS ARE KNOWINGLY ABSORBED** (12462), both favouring the
 * tenant and neither a defect to fix: one emoji cuts a carrier segment from 160
 * characters to **70**, and carriers bill **153** per part once a message
 * splits, where this rule charges per 160 throughout. **He was shown the first
 * and chose the simpler rule.**
 *
 * ⛔ **THE OTHER HALF OF THE OLD CLAUSE SURVIVES INTACT AND IS STILL WHY THIS
 * CLASS EXISTS.** The pair is **one send**: one `SendKey`, one
 * `outreach_messages` row, one call to a driver, one movement asked for here. A
 * caller that debited per *transmission* would still be wrong — it would charge
 * twice by accident rather than twice on purpose, and would charge twice again
 * on the day a third segment appeared. The rule lives here so that there is one
 * place it can be true.
 *
 * ⚠️ **THE DEBIT IS TRANSACTIONAL WITH THE SEND — §3 RAIL 1, VERBATIM**:
 * *"idempotency everywhere a send or charge happens; one key per send/charge;
 * retries and double-clicks can never duplicate a message or a debit; **credit
 * debit is transactional with the send**"*. {@see self::debitForSend()} takes
 * the send itself as a closure and runs it inside the same database transaction
 * as the debit, so the two outcomes are the same outcome. The failure it rules
 * out is specific and is the expensive one: **a message that went out and was
 * never charged for**, which is silent, and its mirror — a credit taken for a
 * message the carrier refused.
 *
 * ⚠️ **AND A NETWORK CALL INSIDE A DATABASE TRANSACTION IS EXACTLY WHAT
 * `BillingCheckout` REFUSES TO DO, SO THE DIFFERENCE IS WORTH STATING.** There,
 * a Stripe call inside the registration transaction would hold a transaction
 * open across a round trip to a third party, and a rollback after Stripe
 * answered would destroy our record of a customer that now exists. Here the
 * closure is deliberately **not** the carrier call: it is the *record* of the
 * send — the outreach row that the queued job will then transmit from. The
 * transaction spans two of our own writes and no socket at all. A sender that
 * passes an HTTP call into this closure has misread it, and the docblock is
 * where that is said, because the signature cannot say it.
 *
 * ⚠️ **THIS SAID "DEBITS THE PURCHASED POOL ONLY" UNTIL 2026-08-13 AND NOW DEBITS
 * BOTH POOLS IN 3307's ORDER** — monthly allotment first, spilling into top-up
 * when it is exhausted. `CreditLedger` chooses the pool; this class chooses
 * whether to *restrict* it, and does so for exactly one case.
 *
 * ⛔ **AND THAT CASE IS SMS BROADCASTING, WHICH MAY NEVER SPEND THE MONTHLY
 * ALLOTMENT** (3309: *"monthly sms credits can not be used for this"*, and 3310
 * requiring the tenant's own brand and number besides). It is the one clause of
 * the old two-pool rule the owner kept when he dropped the other, and 3114's
 * warning applies in both directions: getting which is which backwards inverts
 * the feature. {@see self::restrictedPoolFor()} is where the distinction is made
 * and why it is made from the send's own recorded purpose.
 *
 * ⚠️ **EVERY MOVEMENT HERE NAMES {@see CreditProduct::Sms} SINCE 2026-08-14**
 * (3419). The ledger now holds three products and the parameter has no default,
 * so this class states its own product on every call rather than inheriting one.
 * The point of that shape is the sibling: `EmailCredits` debited *this* balance
 * until the same slice, because there was only one — an email spent a text, which
 * is 2902's objection carried for two days and now answered.
 */
final class SendCredits
{
    public function __construct(
        private readonly CreditLedger $credits = new CreditLedger,
    ) {}

    /**
     * Debit what this send costs and record it, together or not at all.
     *
     * ⚠️ **THE AMOUNT IS READ OFF THE ROW THE CLOSURE JUST WROTE** — see
     * {@see self::creditsFor()}. It is `ceil(characters / 160)` for the body
     * plus one for each photo (12461), so it is **no longer bounded by two**.
     *
     * ⚠️ **`$refId` IS OPTIONAL BECAUSE THE ROW BEING PAID FOR DOES NOT EXIST
     * UNTIL THE CLOSURE HAS RUN** (decision 2548). This method's whole shape
     * says the closure *persists the send* — so on the real path the thing the
     * debit refers to is the row that closure just inserted, and its id is not
     * knowable one line earlier. Passing an id the caller already has works for
     * a campaign recipient and not for an `outreach_messages` row, and the first
     * real caller writes the second. Omit it and the reference is taken from the
     * closure's own return value when that is an Eloquent model.
     *
     * ⚠️ **THE REFERENCE IS STILL REQUIRED, JUST DERIVED.** A credit movement
     * with `ref_type` and no `ref_id` is refused by `CreditLedger` and by a
     * CHECK, because half a pointer fails at read time rather than at write
     * time. If neither an id nor a model arrives, this throws rather than
     * quietly writing an unreferenced debit.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $recordTheSend  Persists the send. ⚠️ **Our
     *                                              own writes only** — never a
     *                                              carrier call. See the class
     *                                              docblock.
     * @param  int|null  $refId  The id of the thing being paid for, when the
     *                           caller already has it. Null takes it from the
     *                           closure's result.
     * @return TReturn
     *
     * @throws CreditMovementRefused The tenant has no credit left.
     * @throws InvalidArgumentException when no reference can be established
     *                                  either way.
     * @throws QueryException whatever the closure's own writes raise, and the
     *                        load-bearing case is a **unique violation on the
     *                        send-key claim** — `PlatformMessageSender` passes an
     *                        INSERT into `outreach_messages` as the closure, and
     *                        SQLSTATE 23505 there is a duplicate send that must
     *                        take the debit down with it.
     *                        ⚠️ **THIS LINE IS NOT DOCUMENTATION POLISH.**
     *                        Larastan reads `@throws` as the *complete* list for
     *                        a called method, so without it the caller's
     *                        `catch (QueryException)` analyses as a dead catch —
     *                        which it reported as `catch.neverThrown`, and which
     *                        would have been "fixed" by deleting the whole
     *                        deduplication branch. The gate was right and the
     *                        annotation was the thing missing.
     */
    public function debitForSend(
        callable $recordTheSend,
        string $refType,
        ?int $refId = null,
    ): mixed {
        return DB::transaction(function () use ($recordTheSend, $refType, $refId): mixed {
            $result = $recordTheSend();

            $reference = $refId ?? ($result instanceof Model ? (int) $result->getKey() : null);

            if ($reference === null) {
                throw new InvalidArgumentException(
                    'A credit debit needs something to point at. No ref id was supplied and the '
                    .'closure returned nothing with a key, so the movement would be an unreferenced '
                    .'spend — which is refused by the ledger and by a CHECK, and which nobody could '
                    .'trace back to the message it paid for.'
                );
            }

            // ⚠️ THE DEBIT COMES SECOND, AND THE ORDER IS NOT ARBITRARY.
            // `CreditLedger::record()` throws when the balance would go
            // negative, and a throw here rolls the send's own row back with it —
            // so a tenant who has run out gets no message and no debit, rather
            // than a message they were not charged for. Debiting first would
            // work equally well for that case and would leave the debit standing
            // if the send's write failed for any other reason.
            //
            // ⛔ **12461 MADE THAT ORDERING MATTER MORE AGAIN.** A tenant
            // with one credit left cannot send a long text, or a text with a
            // picture on it, and the honest outcome is **no message and no
            // debit** — never a three-credit message charged one. The ledger
            // refuses the whole movement rather than taking what it can, so
            // there is no partial charge to unwind. ⚠️ **The gap between what a
            // caller can afford and what a send costs is WIDER than it was**:
            // under 9182 the worst case was two, and it is now unbounded by the
            // body's length.
            $restrictTo = $this->restrictedPoolFor($result);

            // ⛔ **ONE DERIVATION FOR BOTH ARMS, AND IT IS DELIBERATELY ABOVE
            // THE BRANCH.** A broadcast and a plan send that disagreed about
            // what a picture costs would be two prices for one product, and the
            // send this ruling is actually about — a reactivation campaign —
            // takes the restricted arm.
            $credits = $this->creditsFor($result);

            if ($restrictTo instanceof CreditPool) {
                $this->credits->recordFromPool(
                    product: CreditProduct::Sms,
                    pool: $restrictTo,
                    kind: CreditKind::Consume,
                    delta: -$credits,
                    actor: 'system',
                    reason: null,
                    refType: $refType,
                    refId: $reference,
                );

                return $result;
            }

            $this->credits->record(
                product: CreditProduct::Sms,
                kind: CreditKind::Consume,
                delta: -$credits,
                actor: 'system',
                reason: null,
                refType: $refType,
                refId: $reference,
            );

            return $result;
        });
    }

    /**
     * The only pool this send may be paid from, or null to follow the draw order.
     *
     * ⛔ **MARKETING IS THE BROADCAST, AND THE PURPOSE COLUMN IS WHERE THAT IS
     * ALREADY WRITTEN DOWN.** `RunCampaignJob` is the only thing in this
     * application that sends `OutreachPurpose::Marketing` — missed-call text-back
     * and opt-in confirmation both send `Transactional`, and the review-invite
     * path writes `review_request` — so the recorded purpose of the row the
     * closure just inserted *is* the answer to "is this a broadcast", with no new
     * parameter and no second vocabulary to keep in step.
     *
     * ⚠️ **READ OFF THE PERSISTED ROW RATHER THAN PASSED IN, WHICH IS 2548's SHAPE
     * AGAIN.** `debitForSend()` already derives the debit's reference from the
     * closure's return value for the same reason: the send's own record is the
     * thing that exists at this moment and the thing an auditor will read later. A
     * caller-supplied flag could disagree with the row it was sent alongside; this
     * cannot.
     *
     * ⚠️ **WHAT IT CANNOT DO, SAID PLAINLY.** It classifies by *purpose*, so a
     * future broadcast that records itself as transactional would draw on the
     * monthly pool and nothing here would notice. That is a real limit rather than
     * a theoretical one — 3310's other two preconditions (the tenant's own 10DLC
     * brand and their own number) are **unbuilt**, and the feature gate that
     * carries them is where a broadcast will eventually be recognised for certain.
     * This is the half of 3309 that can be enforced from inside the debit today,
     * and it is stated rather than claimed complete (314–316).
     *
     * @param  mixed  $result  Whatever the send closure returned.
     */
    private function restrictedPoolFor(mixed $result): ?CreditPool
    {
        if (! $result instanceof OutreachMessage) {
            return null;
        }

        // `getAttribute()` rather than `->purpose`: the column is a plain string
        // with no cast and no `@property` on the model, so the property form is a
        // Larastan finding rather than a read.
        return $result->getAttribute('purpose') === OutreachPurpose::Marketing->value
            ? CreditPool::TopUp
            : null;
    }

    /**
     * What this send costs, in SMS credits — the owner's rule of 2026-08-30
     * (decision 12461), after 9182 and 9193.
     *
     * **`ceil(characters / 160) + photos`**, counted by characters with no
     * encoding branch. A 160-character text is one; 300 characters are two; a
     * short text with a photo is two; a short text with two photos is three.
     * **There is no campaign kind in the arithmetic.**
     *
     * ⛔ **THE ARITHMETIC IS `SmsCreditUnits`' AND NOT THIS METHOD'S, AND THE
     * SPLIT IS NOT TIDINESS.** `SmsSegments` already counts characters against
     * 160 for the **carrier**, under a build-failing lint that there be exactly
     * one such estimator. The retail unit is a second, deliberately different
     * count — 160 throughout where the carrier bills 153 per part after a split
     * — so it needs a home the lint can name, and a test that asserts the two
     * **disagree** on a real body. Inlining it here would have put the retail
     * rule inside a file the lint had to excuse for the wrong reason.
     *
     * ⚠️ **READ OFF THE PERSISTED ROW RATHER THAN PASSED IN, WHICH IS 2548's
     * SHAPE AND {@see self::restrictedPoolFor()}'s ARGUMENT VERBATIM.** *"A
     * caller-supplied flag could disagree with the row it was sent alongside;
     * this cannot."* And here there is a second reason that method does not
     * have: **the credit movement's reference points at this row**, so a `-3` an
     * operator or a disputing tenant reads a month later is explained by the
     * thing it points at rather than by nothing at all.
     *
     * ⛔ **WHICH NOW MAKES `body` PART OF THE PRICE RECORD.** Under 9182 the row
     * explained a `-2` with one boolean; under this rule the explanation is the
     * **body itself** plus the photo count. `PlatformMessageSender::claim()`
     * already calls that column *"the exact words a stranger received, and the
     * only record of them"* — it is now also the only record of why the charge
     * was what it was.
     *
     * ⚠️ **WHAT IT STILL CANNOT SEE, SAID PLAINLY** (314–316). The count is of
     * **code points**, so a ZWJ emoji sequence bills as several characters;
     * `ext-intl` is not loaded on this server, so the grapheme count that would
     * match *"an emoji is one character"* exactly is a dependency decision and
     * not a quiet swap. `SmsCreditUnits` carries the measurement.
     *
     * ⚠️ **AND NULL IS STILL NOT A THIRD ANSWER.** `null` on `media_count` means
     * *sent before the column existed*, which cannot be a row this method is
     * looking at: the debit reads the row its own closure has just inserted, and
     * every writer states the fact. It scores as **no photos** — never as
     * *unknown* — and the body is priced normally, because the body is the half
     * that was always there.
     *
     * @param  mixed  $result  Whatever the send closure returned.
     */
    private function creditsFor(mixed $result): int
    {
        if (! $result instanceof OutreachMessage) {
            return 1;
        }

        return SmsCreditUnits::forSend(
            $result->body,
            $result->media_count ?? 0,
        );
    }

    /**
     * Whether this tenant can afford one more send.
     *
     * ⚠️ **ASKING IS NOT RESERVING**, and nothing here pretends otherwise. Two
     * concurrent sends can both see a balance of one and both proceed; the debit
     * inside `debitForSend()` is what actually refuses the second, under the
     * lock `CreditLedger::record()` takes on the business row. This exists so a
     * scheduler can skip a tenant cheaply, not so a caller can skip the debit.
     *
     * ⚠️ **IT ANSWERS FOR BOTH SMS POOLS ADDED, WHICH IS OPTIMISTIC FOR A
     * BROADCAST.** A tenant holding 500 monthly credits and no top-up can afford a
     * review invite and cannot afford a broadcast (3309), and this says yes to
     * both. It stays that way deliberately: the method has **no caller anywhere in
     * `app/`**, and adding a pool-aware sibling for a caller that does not exist is
     * the lint that matches nothing (256). The broadcast pre-check that does exist
     * asks `CreditLedger::balance(CreditProduct::Sms, CreditPool::TopUp)` for
     * itself — see `BroadcastPreconditions`.
     *
     * ⚠️ **AND IT ANSWERS ONLY FOR TEXTS** (3419). A tenant with no SMS credit and
     * a full email allotment cannot send one more text, and that is what this now
     * says; before the products existed it could not have distinguished the two.
     *
     * ⛔ **AND SINCE 12461 THAT OPTIMISM IS UNBOUNDED RATHER THAN OFF BY ONE.**
     * 9182 made a send cost one credit or two, so this method was wrong by at
     * most a factor of two. **The rule of 2026-08-30 puts no ceiling on it at
     * all**: a send costs `ceil(characters / 160) + photos`, so a tenant holding
     * one credit gets a yes here for a message that will cost five.
     * ⚠️ **It stays that way for the reason above and the reason is unchanged**
     * — the method has **no caller anywhere in `app/`**, and a body-aware
     * sibling for a caller that does not exist is the lint that matches nothing
     * (256). ⛔ **The first caller owes the parameters**, and it now owes two;
     * what actually refuses the send is the debit, which reads the row.
     */
    public function canAffordOneSend(): bool
    {
        return $this->credits->balance(CreditProduct::Sms) >= 1;
    }

    /**
     * The retail kinds, spelled out, so nothing has to re-derive R9's rule.
     *
     * ⚠️ **`MessageCostKind::InboundSms` AND `InboundMms` ARE ABSENT AND THAT IS
     * THE POINT.** They cost real money and debit **no** credit: charging a
     * tenant because their customer replied is the wrong product, and it would
     * be a charge driven entirely by somebody else's behaviour — which on the
     * AI two-way conversation path is unbounded. The internal cost
     * ledger records them; this one does not.
     *
     * `UndeliveredFee` is absent for a related reason: a credit is charged for
     * the send, once, when it is sent. A later fee for non-delivery is a cost we
     * absorb, not a second thing to bill.
     *
     * ⛔ **`OutboundEmail` IS ABSENT TOO, AND IT IS THE ONE THAT WOULD HAVE GONE
     * WRONG QUIETLY** (3733). This method was `$kind->isOutbound()`, which is a
     * true statement about direction and became the wrong answer the moment the
     * cost book learned about email (3728): an email debits an **email** unit
     * through `EmailCredits`, and **this class is the SMS pool** — `balance()`
     * and `canAffordOneSend()` both ask `CreditProduct::Sms` and have since
     * 3419. Answering true here would have told a reader that
     * {@see self::debitForSend()} is what charges for an email, which would
     * spend a text's credit on a message that already paid for itself.
     * ⚠️ **A `match` rather than a subtraction from `isOutbound()`**, so a
     * seventh kind is a compile-time conversation rather than an inherited
     * answer.
     */
    public function debitsACredit(MessageCostKind $kind): bool
    {
        return match ($kind) {
            MessageCostKind::OutboundSms, MessageCostKind::OutboundMms => true,
            MessageCostKind::OutboundEmail,
            MessageCostKind::InboundSms,
            MessageCostKind::InboundMms,
            MessageCostKind::UndeliveredFee => false,
        };
    }

    /**
     * The tenant's whole SMS balance, for a screen that shows it.
     *
     * A pass-through rather than a second reader: `CreditLedgerEntry` is
     * reachable only from `CreditLedger` by an `ArchitectureTest` lint, and this
     * class is a caller of that service rather than a second definition of the
     * balance. Named here so a sender does not import two services to answer one
     * question.
     *
     * ⚠️ **BOTH SMS POOLS, AND ONLY SMS** (3419). It was named "the purchased
     * balance" when the purchased pool was the only one; 3307 made it both pools
     * and this makes it one product. There is deliberately no cross-product
     * total — see `CreditLedger`'s class docblock for why one cannot exist.
     */
    public function balance(): int
    {
        return $this->credits->balance(CreditProduct::Sms);
    }
}
