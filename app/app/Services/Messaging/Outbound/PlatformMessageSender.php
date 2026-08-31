<?php

declare(strict_types=1);

namespace App\Services\Messaging\Outbound;

use App\Contracts\MessageSender;
use App\Contracts\SendDriver;
use App\Enums\MessageCostKind;
use App\Enums\OutreachChannel;
use App\Enums\OutreachStatus;
use App\Enums\SendRefusalReason;
use App\Exceptions\CreditMovementRefused;
use App\Exceptions\TextNotDeliverable;
use App\Models\Customer;
use App\Models\OutreachMessage;
use App\Services\Billing\MessageCostLedger;
use App\Services\Billing\MessageRates;
use App\Services\Billing\SendCredits;
use App\Services\Messaging\SendingGuard;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * The one implementation of {@see MessageSender} — and the thing decision 2206
 * recorded as missing.
 *
 * 2206, verbatim: *"`MessageSender` has no implementation yet … recorded because
 * an interface with no implementation is 272's shape wearing a contract's
 * clothes: **L3 and L5 can compile against it and nothing sends.**"*
 * `RunCampaignJob` has been calling `app(MessageSender::class)->send()` against
 * an unbound interface, which throws `BindingResolutionException` in production
 * and passed every test because every test binds a recording fake. This class
 * and its binding in `AppServiceProvider` are that gap closed.
 *
 * ## It closes three writerless books at once, which is why it is one class
 *
 * Each of these had a service, a table, a lint and a test, and **no caller in
 * `app/`** — `CLAUDE.md`'s most-recorded failure shape, three times over:
 *
 *   `SendCredits::debitForSend()`      R9's retail credit. **Every SMS on
 *                                      *this* path was free.**
 *   `MessageCostLedger::record()`      R9's internal carrier-cost book.
 *   the `SendKey` unique index         claimed by `SendDriver`'s docblock and
 *                                      absent from the schema until 2541.
 *
 * They are one class because they are one transaction. A sender that debited in
 * one place and recorded cost in another would be two chances to get the
 * ordering wrong, and §3 rail 1 is precisely about the ordering.
 *
 * ⛔ **THE FIRST LINE ABOVE SAID "EVERY SMS THIS SYSTEM COULD SEND WAS FREE"
 * UNTIL 2900, AND THAT CLAIM WAS TRUE OF ONE CALLER OUT OF TWO.** This class has
 * exactly one caller — `RunCampaignJob` — and the review-invite path never came
 * through it: `ReviewInviteSender` opened its own transaction, wrote its own
 * `outreach_messages` row and called the texter directly, so **every review
 * invite was still free for as long as this paragraph claimed otherwise.** That
 * is `docs/FAILURE-SHAPES.md`'s "protection layer asserted before it is true" exactly: a
 * docblock announcing a closed gap is what stops the next reviewer looking, and
 * this one stopped several.
 *
 * ⚠️ **THAT CLASS IS NAMED IN PROSE RATHER THAN WITH A `{@see}` FOR THIS FILE'S
 * OWN ESTABLISHED REASON** — Pint promotes a `{@see}` into a real `use`, and it
 * did so here on this slice's first lint run, exactly as the `segments()`
 * docblock below already records happening once before.
 *
 * ⚠️ **WHAT IS TRUE NOW, STATED NARROWLY ON PURPOSE.** Both senders debit
 * through `SendCredits` — **one credit for the text and one for the media**
 * (9182, confirmed 9193, reversing R9's *"one credit per send"*) —
 * transactionally, against the same `ref_type`
 * ({@see OutreachMessage::CREDIT_REFERENCE_TYPE}), and — since 2970 — both
 * consult {@see SendingGuard} before writing anything, so the global halt, a
 * tenant's pause and 2102's automatic trip now reach **every** send path. They
 * are still **two implementations**, not one, because 2901 refused to route the
 * invite path through here. ⛔ **What remains this path's alone is the `SendKey`
 * unique claim and `MessageCostLedger`** (2975, 2976): the invite path has no
 * send key, so a duplicate invite is refused by its own gates and by
 * `SendReviewInviteJob`'s idempotency key rather than by an index, and its
 * carrier cost reaches no internal book. Do not widen this paragraph to "the
 * sender is now universal" without making it so first.
 *
 * ## The order of operations, and why each step is where it is
 *
 *   1. **Route to a driver by channel.** No driver is a programming error, not
 *      a refusal — a caller composed a message for a channel this application
 *      cannot carry, and answering "refused" would file that as a fact about
 *      the recipient.
 *   2. **{@see SendingGuard}, before anything is written.** The global halt,
 *      this tenant's pause, and 2102's automatic complaint-rate trip. Per
 *      message rather than per campaign, because *"a campaign runner that
 *      checked once at enqueue would authorise the whole run on one reading"* —
 *      the complaints arrive because the campaign is running. Nothing has been
 *      written at this point, so a refusal costs one query and no rollback.
 *   3. **Claim the key and debit the credit, in one transaction.** The claim is
 *      an INSERT into `outreach_messages` carrying the `SendKey`; the unique
 *      index refuses the second. §3 rail 1's two halves — *"retries and
 *      double-clicks can never duplicate a message **or a debit**"* — are the
 *      same half here, because the debit only happens if the claim did.
 *   4. **The wire.** Then the row is completed in place with the carrier's
 *      handle, and the transaction commits.
 *   5. **Record the internal cost — after the commit, on purpose** (3880–3882),
 *      from {@see MessageRates}. A separate book from step 3 and never shown as
 *      retail. ⛔ **It was step 4, inside the transaction and *before* the wire,
 *      and that ordering opened a duplicate-send hole**: a bookkeeping failure
 *      was swallowed, the carrier took the message, and the commit then failed
 *      and rolled the whole claim back for a job that retried and sent again.
 *      Past the commit the send is a fact nothing bookkeeping can undo.
 *
 * ## Where the wire call sits, and the trade that decides it
 *
 * ⚠️ **THE CARRIER CALL IS INSIDE THE TRANSACTION, WHICH IS WHAT
 * `ReviewInviteSender` ALREADY DOES AND WHAT `SendCredits`' DOCBLOCK WARNS
 * ABOUT.** Both positions are real, so the argument is written down rather than
 * settled by whichever file was read last (decision 2551).
 *
 * `SendCredits` says a network call must not go inside the debit's transaction,
 * citing `BillingCheckout`'s refusal to call Stripe inside the registration
 * transaction. That refusal is honoured **literally**: the closure handed to
 * `debitForSend()` contains our own writes and no socket. What sits inside the
 * *outer* transaction is the wire call, and it is there because the alternative
 * is worse in a way that reaches a member of the public:
 *
 *   - **Outside the transaction**, a `TextNotDeliverable` leaves a committed row
 *     and a committed debit for a message that never went. Repairing that needs
 *     a compensating `CreditKind::Refund` entry — and if instead the claim is
 *     released so the job can retry, a vendor **timeout** (which may mean the
 *     message did go) becomes a second text to a real person. `Texter`'s own
 *     docblock calls that out: *"a retried SMS that actually succeeded the first
 *     time is a second message to a real person, not a duplicated read."*
 *   - **Inside the transaction**, a throw rolls the row, the debit and the cost
 *     entry back together, the job retries with backoff, and the retry re-claims
 *     the same key cleanly. No compensation, no refund kind, no poisoned key.
 *
 * ⚠️ **THE COST IS A DATABASE TRANSACTION HELD ACROSS A VENDOR ROUND TRIP** —
 * bounded by `services.infobip.timeout`, ten seconds by default. That is a real
 * cost at campaign volume and it is the thing to revisit first if connection
 * pressure ever shows up; what must not happen is somebody moving the call out
 * without also building the compensation, because the suite would stay green
 * either way.
 *
 * ⚠️ **THE RESIDUAL RISK IS NAMED: A TIMEOUT AFTER THE CARRIER TOOK THE
 * MESSAGE** rolls our record back while the handset still receives it. The
 * retry then re-claims and sends again. Closing that needs vendor-side
 * idempotency, and 2545 records that Infobip's `destinations[].messageId` is not
 * documented as deduplicating — so it is a known gap rather than a solved one,
 * exactly as it was before this class existed.
 *
 * ⛔ **THAT PARAGRAPH IS THE 2026-08-11 READING AND ITS SECOND SENTENCE IS NOW
 * TRUE OF ONE CALLER OUT OF FOUR — BOTH READINGS KEPT AND DATED, 2026-08-21
 * (7068).** *"The retry then re-claims and sends again"* was the whole of it,
 * and this class was the only place in the codebase that had written the risk
 * down. **What changed is one layer up rather than in here**: the transport now
 * says which of the two outcomes it was (7060), and the three jobs that send a
 * single keyed message — `SendReviewInviteJob`, `SendOptInConfirmationJob`,
 * `SendMissedCallTextBackJob` — keep their `automation_runs` claim when the
 * carrier may already hold the message, so the queue's retry never reaches this
 * method a second time. **Nothing about this transaction moved**: the row, the
 * debit and the cost entry still roll back together, and this class still has no
 * memory of an attempt whose outcome nobody knows.
 *
 * ⛔ **AND THE ONE CALLER IT IS STILL TRUE OF IS THIS CLASS'S OWN** (7069).
 * `RunCampaignJob` declares `idempotencyKey(): null` — deliberately, and its
 * docblock argues why — so it has no claim to keep. A `TextNotDeliverable`
 * thrown out of `sendTo()` skips `record()`, leaves the `campaign_recipients`
 * row untouched, and the queue retries the whole pass: the `send_key` this
 * method rolled back is free, the recipient is re-selected, and a second text
 * goes out. **That path is unfixed and is not this slice's to fix** — the guard
 * it needs is on the recipient row rather than on a run claim.
 * ⛔ **THAT PATH IS FIXED AND THIS PARAGRAPH'S CONSEQUENCE NO LONGER FOLLOWS —
 * CORRECTED 2026-08-21 (7280, on 7180–7199's work).** The guard landed exactly
 * where this paragraph said it belonged, **on the recipient row**:
 * `RunCampaignJob` now catches the transport failure and stamps
 * `CampaignRecipientStatus::Unknown`, which sits outside `isOutstanding()`, so
 * `batch()` never re-selects that recipient and the sweep never picks them up.
 * ⚠️ **THE MECHANISM THIS PARAGRAPH DESCRIBES IS UNCHANGED AND STILL TRUE** —
 * this class still rolls the claim back and still has no memory of an attempt
 * whose outcome nobody knows; what changed is that its one unguarded caller now
 * remembers for itself. ⚠️ **`RunCampaignJob` is a fourth reader of
 * `mayHaveReachedCarrier`** and `MessagingTest`'s reader lint was widened with
 * the argument rather than routed around (7182).
 *
 * ⚠️ **2545 WAS RE-VERIFIED AGAINST THE VENDOR'S OWN OPENAPI SPECIFICATION ON
 * 2026-08-21 AND IT HOLDS** (7065): `idempot`, `dedup`, `duplicate` and
 * `x-request` occur **zero** times in it, and `POST /sms/3/messages` declares no
 * header parameters at all. There is no vendor-side mechanism to negotiate.
 * ⚠️ **What the same artefact *does* offer is a way to find out afterwards
 * rather than a way to be safe in advance** — a caller-supplied
 * `destinations[].messageId` echoed into `GET /sms/3/logs?messageId=…` for 48
 * hours (7066). Unbuilt, and recorded rather than assumed.
 *
 * ## What it deliberately does not check
 *
 * ⛔ **QUIET HOURS ARE NOT RE-CHECKED HERE, AND {@see MessageSender}'s
 * GUARANTEE 5 IS ALREADY KEPT ELSEWHERE** (2207, and decision 2552).
 * `ConsentService::stateRefusal()` enforces recipient-local quiet hours at
 * permit time, marketing only, correctly exempting `Transactional` — the T69 law
 * is honoured and was not touched. A second implementation here would be two
 * places that can disagree about when a person may be texted, and the one that
 * disagrees is the one nobody reads. What 2207 says is *missing* is the
 * **deferral** — a refused marketing send is lost rather than rescheduled — and
 * that is a scheduler, not a check, and it is still owed.
 */
final class PlatformMessageSender implements MessageSender
{
    /**
     * What a credit movement and a cost entry point at.
     *
     * The `outreach_messages` row, on both books, so that "which message was
     * this credit spent on" and "which message cost us this" have the same
     * answer and can be joined.
     *
     * ⚠️ **THE STRING ITSELF MOVED TO THE MODEL AT 2900.** It was a private
     * literal here under the note that *"two spellings of one string in one
     * class is how a ledger ends up with rows nothing can find"* — and then a
     * second sender turned out to need the same value. An alias rather than a
     * copy, so the two cannot drift into two vocabularies for one pointer.
     */
    private const string REFERENCE_TYPE = OutreachMessage::CREDIT_REFERENCE_TYPE;

    /**
     * @param  array<string, SendDriver>  $drivers  Keyed by channel value.
     */
    public function __construct(
        private readonly SendingGuard $guard,
        private readonly SendCredits $credits,
        private readonly MessageCostLedger $costs,
        private readonly MessageRates $rates,
        private readonly SendSettlement $settlement,
        private readonly array $drivers,
    ) {}

    public function send(OutboundMessage $message): SendOutcome
    {
        $driver = $this->drivers[$message->channel()->value] ?? null;

        if (! $driver instanceof SendDriver) {
            // Loud. A caller composed a message for a channel with no wire, and
            // a refusal would file that as a fact about the recipient — see the
            // class docblock, step 1.
            throw new LogicException(
                "No send driver is registered for {$message->channel()->value}. A message was "
                .'composed for a channel this application cannot carry, which is a wiring mistake '
                .'rather than something to record against the person it was addressed to.'
            );
        }

        $refusal = $this->guard->refusalFor($message->channel());

        if ($refusal !== null) {
            // Nothing written, nothing debited, nothing to roll back.
            return SendOutcome::refused($message->key, $refusal);
        }

        return $this->transact($message, $driver);
    }

    /**
     * Claim, debit, price, send, settle — or none of it.
     *
     * ⚠️ **MANUAL `beginTransaction()` RATHER THAN `DB::transaction()`, AND
     * `ReviewInviteSender` MADE THE SAME CALL FOR THE SAME REASON.** A driver
     * refusal has to roll back *and return a value*, and a closure can only roll
     * back by throwing — which would turn an operator's kill switch into an
     * exception a queued job records as a failure and retries.
     *
     * @throws TextNotDeliverable rethrown after the rollback, so the calling
     *                            job retries with backoff against a key that is
     *                            free again.
     */
    private function transact(OutboundMessage $message, SendDriver $driver): SendOutcome
    {
        DB::beginTransaction();

        try {
            try {
                /** @var OutreachMessage $row */
                $row = $this->credits->debitForSend(
                    // ⚠️ **OUR OWN WRITES ONLY, WHICH IS `SendCredits`' OWN
                    // INSTRUCTION.** The carrier call is below, outside this
                    // closure. The reference for the debit is this row and its
                    // id does not exist until the closure has run, which is why
                    // `debitForSend()` derives it from the return value (2548).
                    recordTheSend: fn (): OutreachMessage => $this->claim($message),
                    refType: self::REFERENCE_TYPE,
                );
            } catch (QueryException $exception) {
                // 23505 is unique_violation on `outreach_messages.send_key`.
                // Any other SQLSTATE is a real failure and must not be swallowed
                // into "already sent" — a send that did not happen reported as a
                // duplicate is a message silently dropped.
                if ($exception->getCode() !== '23505') {
                    throw $exception;
                }

                DB::rollBack();

                // ⚠️ **THE ORIGINAL'S PROVIDER ID TRAVELS BACK** so the caller
                // reconciles rather than guesses which attempt won (2174). It is
                // read after the rollback, on a clean connection, because the
                // aborted statement poisoned the one inside.
                return SendOutcome::duplicate($message->key, $this->providerIdFor($message->key));
            } catch (CreditMovementRefused) {
                DB::rollBack();

                // Graceful degradation, never a hard failure and never a
                // surprise charge — `29` §2 rule 43. Nothing was written and
                // nothing was billed.
                return SendOutcome::refused($message->key, SendRefusalReason::InsufficientCredit);
            }

            $outcome = $driver->deliver($message);

            if (! $outcome->wasSent()) {
                // The channel could not carry it. The row and the debit go
                // back: a committed row here would claim a message that was
                // never sent and never will be, and the key would stay claimed
                // so no retry could ever send it. ⚠️ **THE COST ENTRY IS NOT
                // AMONG THEM AND NO LONGER NEEDS TO BE** (3880) — it is written
                // past the commit below, which this branch never reaches, so a
                // refused send books nothing rather than booking and unbooking.
                DB::rollBack();

                return $outcome;
            }

            // ⚠️ **CALLED HERE RATHER THAN THROUGH A PRIVATE `settle()` WRAPPER,
            // AND LARASTAN IS WHY** (3047). `wasSent()` asserts the provider id
            // is a `non-empty-string` — an invariant `SendOutcome`'s constructor
            // already refuses to break, since *"an accepted send that cannot be
            // named can never have its delivery receipt applied"*. That
            // narrowing is live on this line and is **lost the moment the
            // outcome is handed to another method**, which is what a wrapper
            // did. The alternatives were both worse: re-checking for null inside
            // the wrapper writes a branch no test can drive, and a cast would
            // turn the one case that must never happen into an empty string
            // silently joined against by every future delivery receipt.
            $this->settlement->settle($row, $outcome->providerMessageId, $outcome->numberId);

            DB::commit();
        } catch (Throwable $exception) {
            // ⚠️ `transactionLevel()` GUARDED, BECAUSE THE TWO EARLY RETURNS
            // ABOVE HAVE ALREADY ROLLED BACK. Rolling back twice throws a
            // different exception from inside the handler and buries the real
            // one.
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            throw $exception;
        }

        // ⛔ **AFTER THE COMMIT, AND OUTSIDE THE `catch` — BOTH HALVES ARE
        // LOAD-BEARING** (3880, 3881). The send is a committed fact before a
        // single bookkeeping statement runs, so nothing this line does can
        // reach the row, the debit, the `SendKey` claim or the consent trail.
        // ⚠️ **OUTSIDE THE `catch` BECAUSE INSIDE IT WOULD REBUILD THE HOLE IN
        // MINIATURE**: after `DB::commit()` the transaction level is zero, so
        // the handler above would not roll back — it would simply rethrow, the
        // job would retry, and the retry would send the same message to the
        // same handset a second time.
        $this->recordCost($message, $row);

        return $outcome;
    }

    /**
     * Write the row that *is* the claim on this send key.
     *
     * ⚠️ **THE CLAIM IS THE INSERT AND NEVER A CHECK-THEN-INSERT** — decision
     * 350's rule. Two workers handed the same retried job both read "no row" and
     * both proceed; only the unique index refuses the second, and it refuses it
     * *before* the credit is debited, because `debitForSend()` runs the closure
     * first and the throw takes the debit with it.
     *
     * ⚠️ **THE ROW LANDS `Queued` WITH NO CARRIER HANDLE, WHICH IS THE HONEST
     * RESOLUTION OF TWO REQUIREMENTS THAT READ AS A CONFLICT** —
     * `ReviewInviteSender`'s finding, restated because this class repeats its
     * shape. `BUILD-PLAN` §2.10.1's third property wants the record of the
     * *decision* to exist before the vendor is called, so a process that dies
     * mid-send leaves evidence; the delivery-receipt webhook needs
     * `provider_msg_id` populated or the row sits `Queued` forever. Neither is
     * negotiable, so it is written first and completed in place.
     */
    private function claim(OutboundMessage $message): OutreachMessage
    {
        $customer = Customer::query()->find($message->permit->customerId);

        return OutreachMessage::create([
            'business_id' => Tenancy::idOrFail(),
            // ⚠️ **READ OFF THE CUSTOMER RATHER THAN PASSED IN, AND NULLABLE.**
            // `OutboundMessage` carries no location because the permit does not:
            // consent is per customer and channel. A location that cannot be
            // resolved leaves the column null, which the table already allows,
            // rather than refusing a send over a reporting dimension.
            'location_id' => $customer?->location_id,
            'customer_id' => $message->permit->customerId,
            'channel' => $message->channel(),
            'purpose' => $message->purpose->value,
            // Frozen from the permit — "a historical fact frozen at send time,
            // unlike customers.messaging_lane, which is derived current state".
            'lane' => $message->permit->lane,
            // ⚠️ **THE EXACT WORDS A STRANGER RECEIVED, AND THE ONLY RECORD OF
            // THEM.** Unlike a templated email this is not reconstructible: the
            // link, the name and the lane disclosure are all resolved at send
            // time.
            'body' => $message->body,
            // ⛔ **THIS DECIDES THE CHARGE, AND IT IS WRITTEN HERE BECAUSE THIS
            // IS THE ONLY MOMENT BOTH FACTS ARE IN ONE SCOPE** (12461). The
            // media list rides the `OutboundMessage`; the credit movement points
            // at this row; `SendCredits::creditsFor()` reads the column rather
            // than taking a flag, on 2548's argument. ⚠️ **The URLs themselves
            // are deliberately NOT stored** — a campaign media URL names a
            // picture composed with a named customer on it, and how many were
            // sent is all the ledger needs to explain a `-3`.
            //
            // ⛔ **A COUNT AND NOT `isMultimedia()`, SINCE 2026-08-30.** The
            // owner ruled that **each** photo is a credit, so the predicate that
            // served the boolean column now under-reports the moment a second
            // item is ever composed. `count()` over the list the message already
            // carries cannot: it is the same array the driver submits.
            'media_count' => count($message->mediaUrls),
            'send_key' => $message->key->value,
            'status' => OutreachStatus::Queued,
            'created_at' => now(),
        ]);
    }

    /**
     * The internal carrier cost — on the same path, and deliberately **after**
     * the send transaction rather than inside it.
     *
     * ⛔ **A SECOND BOOK, NEVER THE RETAIL ONE.** ⚠️ **THIS SAID "THE TENANT
     * PAID EXACTLY ONE CREDIT ABOVE WHATEVER THIS RECORDS" AND 9182 REVERSED
     * IT**: the tenant paid one credit for the text and one for the media, so a
     * message reaching the `OutboundMms` branch below cost **two**. The
     * independence is what the paragraph was for and it is unchanged — this
     * figure is never shown as a price and is never derived from the other book.
     *
     * ⛔ **AND "TWO" STOPPED BEING THE ANSWER ON 2026-08-30 (12461).** Retail is
     * now `ceil(characters / 160) + photos`, so a long MMS costs three or more
     * and **the retail charge has no ceiling this paragraph can name.** The
     * independence is the durable half; every arithmetic restatement of the
     * other book in this docblock has gone stale within a fortnight, twice.
     *
     * ⛔ **AND THE TWO NO LONGER TURN ON THE SAME QUESTION AT ALL, WHICH MAKES
     * THEM LESS REDUNDANT RATHER THAN MORE.** This branch asks
     * `isMultimedia()` — a yes/no about the *endpoint*, which is the right
     * question for a carrier cost kind. `SendCredits` reads `media_count` and
     * the `body` off the persisted row. **They agree on every send this
     * application can compose today** and would part the moment a second media
     * item is ever attached. ⚠️ **Reading one off the other was already
     * impossible for a second reason and still is**: this row is written
     * **after** the commit and is skipped entirely while the rate is unset
     * (2547), so on every deployment today the retail charge is a fact and this
     * row does not exist.
     *
     * ⚠️ **NO ROW WHEN NO RATE IS CONFIGURED, AND THE SEND STILL GOES.**
     * {@see MessageRates} seeds every rate to zero because Infobip's per-account
     * price is in no artefact this lane could read, and a bookkeeping gap must
     * never stop a message. The consequence — an empty cost book until an
     * operator enters four numbers — is decision 2547 and is stated rather than
     * hidden.
     *
     * ⚠️ **THE IDEMPOTENCY KEY IS DERIVED FROM THE SEND KEY**, so a retry that
     * somehow reached here twice writes one row. It is namespaced by kind
     * because a later delivery receipt writes an `UndeliveredFee` row against
     * the same send and must not collide with this one.
     *
     * ⛔ **AND NO OTHER BOOKKEEPING FAILURE CAN STOP THE SEND EITHER** (3729's
     * lane, corrected at 3880–3882). "No rate" was the only failure handled at
     * first; every other one propagated into `transact()`'s `catch (Throwable)`,
     * rolled the transaction back and rethrew — so a typo in the currency
     * registry key (3742's neighbourhood, argued at 3739), a deadlock, or a
     * constraint this table gains later would have turned a carrier-cost bug
     * into an undelivered message and a failed job. 2547's rule runs the other
     * way: the cost row is what is given up.
     *
     * ⛔ **AND THE FIRST ATTEMPT AT THAT WAS WORSE THAN THE DEFECT, WHICH IS WHY
     * THIS SITS AFTER THE COMMIT** (3880). It was a nested `DB::transaction()`
     * around this body, with a docblock claiming a `SAVEPOINT` contained the
     * failure. **Laravel does not roll back to the savepoint on a concurrency
     * error** — `ManagesTransactions::handleTransactionException()` decrements
     * the counter and rethrows a `DeadlockException` without issuing
     * `ROLLBACK TO SAVEPOINT` — so a real deadlock (`40P01`) left the outer
     * transaction aborted and the savepoint dangling. ⛔ **On this path that was
     * a duplicate-send hole that did not exist before the guard was added**: the
     * throw was swallowed here, `deliver()` then handed the message to the
     * carrier, `settle()` raised `25P02`, the handler rolled everything back —
     * the row, the debit, the `SendKey` claim, the consent trail — and the job
     * retried against a key that was free again and **texted a stranger twice**.
     * Verified against the dev Postgres: `22012` is contained by that savepoint
     * and `40P01` is not.
     *
     * ⚠️ **SO THE CONTAINMENT IS THE ORDERING, NOT A SAVEPOINT** (3881). This
     * runs at transaction level zero, after `COMMIT`, where a failed statement
     * aborts nothing because there is nothing open to abort — and the safety no
     * longer depends on classifying the error correctly, which is what the
     * savepoint required and what `lock_timeout` (`55P03`), a statement timeout
     * and a PHP-level throw would each have got wrong in a different way.
     *
     * ⚠️ **WHAT THAT GIVES UP, SAID PLAINLY** (3882): the cost row is no longer
     * atomic with the send, so a process that dies between the commit and this
     * line loses it. That is the trade 2547 already made — a missing row
     * understates our own cost on a book only we read — and the `Log::warning`
     * below is what makes it recoverable.
     *
     * ⚠️ **A FUTURE CALLER THAT WRAPS `send()` IN ITS OWN TRANSACTION RE-OPENS
     * THIS, AND NEITHER OF TODAY'S TWO DOES** (3883). `RunCampaignJob` is the
     * only caller and opens none. If one ever does, the guard is a hand-managed
     * `SAVEPOINT`/`ROLLBACK TO SAVEPOINT` pair — **never** a nested
     * `DB::transaction()`, for the reason two paragraphs up.
     */
    private function recordCost(OutboundMessage $message, OutreachMessage $row): void
    {
        try {
            $kind = $message->isMultimedia() ? MessageCostKind::OutboundMms : MessageCostKind::OutboundSms;

            // ⚠️ **SEGMENTS ARE PASSED ONLY FOR THE PRODUCT BILLED BY THEM.**
            // MMS carries a media fee per message, and a segment count on one
            // would be a claim the carrier never made —
            // `message_cost_entries.segments` is nullable for exactly this, and
            // zero would be a different, false statement from not knowing.
            $segments = $kind === MessageCostKind::OutboundSms ? $this->segments($message->body) : null;

            // ⚠️ **MILLICENTS SINCE 3729, NOT A `Money`.** The schedule no
            // longer rounds each message to cents on the way past — a floor
            // that overstated a sub-cent SMS by a third and would have
            // overstated an email by a hundredfold — so the figure travels
            // intact and the book rounds once, on its total.
            $costMillicents = $this->rates->costFor($kind, $segments);

            if ($costMillicents === null) {
                return;
            }

            $this->costs->record(
                kind: $kind,
                costMillicents: $costMillicents,
                idempotencyKey: 'send:'.$message->key->value.':'.$kind->value,
                segments: $segments,
                refType: self::REFERENCE_TYPE,
                refId: (int) $row->getKey(),
            );
        } catch (Throwable $e) {
            // ⚠️ **THE CLASS NAME AND OUR OWN IDS, NEVER THE BODY OR THE
            // NUMBER.** This class holds a member of the public's mobile number
            // and the words sent to them; neither goes to a log. The send key is
            // ours and is what an operator reconciles a missing row against.
            //
            // ⚠️ **AND THE RECONCILIATION QUERY IS IN `MessageCostLedger`'s
            // DOCBLOCK RATHER THAN LEFT TO BE DERIVED** (3898). No counter and
            // no metric: this application has no metrics facility to hang one
            // on, and a durable counter with one writer and no reader is 272's
            // shape — the very thing this lane's first commit closed.
            Log::warning('A message was sent and its provider cost was not booked.', [
                'reason' => $e::class,
                'business_id' => Tenancy::id(),
                'send_key' => $message->key->value,
            ]);
        }
    }

    /**
     * What the first send of this key was called, when it can be read.
     *
     * Null is a real answer rather than a failure: the winning attempt may still
     * be inside its own uncommitted transaction, or may have been rolled back
     * between the collision and this read. The caller gets a duplicate either
     * way — what it must not get is a fabricated id.
     */
    private function providerIdFor(SendKey $key): ?string
    {
        $id = OutreachMessage::query()
            ->where('send_key', $key->value)
            ->value('provider_msg_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Roughly how many carrier segments this body will be billed as.
     *
     * ⛔ **THE ARITHMETIC MOVED TO {@see SmsSegments} AND THIS IS NOW A
     * DELEGATION** (4800). It was private to this class, and being private here
     * is exactly what 2976 recorded as the blocker on booking the review
     * invite's own SMS: *"a second copy of a cost estimator is two figures that
     * drift in the one book whose purpose is reconciliation."* The second caller
     * needed the estimate, so the answer was **one estimator**, never a copy of
     * this one. Every caveat about what it does not model — GSM-03.38's
     * alphabet, the seven double-width characters, the UCS-2 fallback — moved
     * with the code, so the limitation and the implementation cannot drift
     * apart either.
     *
     * ⚠️ **THE METHOD IS KEPT RATHER THAN INLINED AT ITS CALL SITE**, so that
     * somebody looking for the segment count in *this* file still finds it, and
     * finds the pointer.
     */
    private function segments(string $body): int
    {
        return SmsSegments::count($body);
    }

    /**
     * The channels this sender can actually carry, for a caller that wants to
     * ask before composing.
     *
     * ⚠️ **A READER FOR THE DRIVER MAP, SO "IS EMAIL WIRED" IS ASKABLE RATHER
     * THAN DISCOVERED BY A `LogicException` IN A QUEUE WORKER.** Email has no
     * `SendDriver` today (2553) and the honest way to say so is a method, not a
     * comment.
     *
     * @return list<OutreachChannel>
     */
    public function channels(): array
    {
        return array_values(array_map(
            static fn (SendDriver $driver): OutreachChannel => $driver->channel(),
            $this->drivers,
        ));
    }
}
