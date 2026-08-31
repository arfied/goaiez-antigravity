<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Exceptions\CreditMovementRefused;
use App\Services\Config\DefaultsRegistry;
use App\Services\Mail\MailDrivers;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * **One unit per platform email** — the retail meter for the email channel
 * (decision 3299).
 *
 * ⛔ **UNTIL THIS CLASS EXISTED, EVERY CUSTOMER-FACING EMAIL THIS SYSTEM COULD
 * SEND WAS FREE**, and it was free *on purpose*: `ReviewInviteSender`'s own
 * docblock recorded 2902 — *"email debits nothing, and that is deliberate rather
 * than an oversight … email is metered separately at $20/10,000 (2071) against a
 * meter that is not built."* **This is that meter**, and the rate it charges is
 * not 2071's: 3299 corrects it tenfold to **$20 per 1,000**, taken from the
 * top-up SKUs rather than from the headline, because $50 buys 2,500 emails
 * (3302) and that is $20 per 1,000 to the cent.
 *
 * ✅ **2902's OBJECTION IS ANSWERED RATHER THAN CARRIED, AS OF 2026-08-14**
 * (3419). 2902 refused to debit an email against the credit ledger at all,
 * because *"debiting an SMS credit for an email would charge the wrong pool for
 * the wrong product"*. That objection was about **which pool**, and it was
 * correct — 3298 grants 500 SMS, a month of AI credit and 1,000 emails as three
 * separate monthly allotments, and the table had no product dimension to express
 * them. 3299 settled that an email **is** a metered spend, and 3297 makes an
 * unmetered cost path a spend nothing can stop now that the dollar cap is deleted
 * (3293) — so this class shipped debiting the one undifferentiated balance and
 * named itself, in this docblock, as *"the one place that changes when the ledger
 * learns which product a unit belongs to."*
 *
 * **This is that change.** {@see self::debitForSend()} names
 * {@see CreditProduct::Email}, an email no longer spends a text, and the 1,000
 * emails a month 3298 grants have somewhere to live. ⚠️ **It was two days of real
 * sends against the wrong pool**, and the migration deliberately leaves those
 * rows labelled SMS: they *were* spending the SMS balance in fact as well as in
 * the column, and relabelling them now would move a balance that has already been
 * spent.
 *
 * ## The exemption is one named method, because it was inferred from a comma
 *
 * ✅ **3300 WAS A READING AND IS NOW A RULING (3438).** The owner wrote *"email
 * credits $20 per thousand for ses google and imap are free."* Punctuated one way
 * that exempts a tenant's own connected Google or IMAP mailbox; punctuated
 * another it says email is free, contradicting the price in the same sentence.
 * The exempting reading was taken because a tenant sending from their own mailbox
 * (2072) costs us nothing, and because the error it risked was undercharging
 * rather than billing for a send we never paid for. **Asked directly, the owner
 * answered "Own domain free."** The reading was right.
 *
 * ⛔ **BUT "OWN DOMAIN" IS NOT "OWN MAILBOX", AND THE NARROW READING IS THE ONE
 * THIS CLASS IMPLEMENTS** (3439). A custom sending *domain* riding **our** SES is
 * not a tenant's own mailbox: we still pay Amazon for every one of those sends,
 * so exempting it would give away a metered product rather than decline to bill
 * for something free. **Free means the send never touches our transport.** Do not
 * widen this exemption to a custom sending domain on the strength of that answer
 * — it is a different product and it costs us money.
 *
 * **So the exemption is {@see self::UNMETERED_MAILERS} and nothing else** — one
 * array, in one file, and reversing the reading is deleting or adding a line in
 * it. It is deliberately not a condition threaded through the send path, because
 * a pricing rule inferred from a missing comma is a rule that may have to be
 * withdrawn, and a rule scattered across three files is withdrawn incompletely.
 *
 * ⛔ **THE ARRAY IS EMPTY TODAY, WHICH MEANS THE EXEMPTION HAS NO LIVE SUBJECT
 * AND EVERY EMAIL THIS APPLICATION SENDS IS METERED.** Stated plainly rather
 * than left to read as a working feature, because decision 256 is exactly this:
 * a branch that matches nothing passes vacuously. **There is no tenant-mailbox
 * *send* path in `app/`** — `MicrosoftGraphService` is the tenant's own mailbox
 * and `CLAUDE.md` records it as having no caller, the only IMAP in the codebase
 * is `SupportInbox` reading *our* support mailbox, and nothing anywhere sends a
 * customer email through a tenant's credentials.
 *
 * ⛔ **AND THE `gmail` MAILER IS NOT THE THING 3300 EXEMPTS.** It is the
 * *platform's* Google Workspace internal-app transport (2093, email sending transport) — the
 * primary path at soft launch — and adding it to the array would exempt the
 * transport that carries almost every send, leaving a meter that meters nothing.
 * The exemption belongs to a mailbox **the tenant connected**, which by
 * construction does not go through `PlatformMailer` at all.
 */
final class EmailCredits
{
    /**
     * The retail rate: $20 per 1,000 emails, in integer cents (3299).
     *
     * ⚠️ **A CONSTANT HERE AND NOT A REGISTRY SEED, AND THAT IS AN INTEGRATION
     * DEBT RATHER THAN A DESIGN.** `38` Part 2's rule is that a pricing figure
     * lives in the registry; 3316 records that **not one figure in the credits
     * model has a registry key yet**, and `DefaultsRegistry::value()` raises on a
     * key the manifest has never declared — so reading `billing.email.…` today
     * would throw rather than fall back. The expected key is
     * **`billing.email.retail_cents_per_thousand`**, and when the manifest
     * declares it this constant becomes its fallback and then goes away.
     *
     * ⚠️ **THE FIGURE IS THE SKUs' AND NOT THE HEADLINE'S.** 2063 recorded $20
     * per 10,000 and 3299 supersedes it tenfold. Two independent statements agree
     * on this one: the owner's *"$20 per thousand"*, and 3302's $50-for-2,500
     * automatic top-up. **Do not "correct" it back toward 2063** — the test
     * beside this class pins both statements so that a correction has to argue
     * with the SKU.
     */
    public const int RETAIL_CENTS_PER_THOUSAND = 2_000;

    /**
     * What one email costs the tenant, in the ledger's own units.
     *
     * One unit per send — an email is one send however many transmissions a
     * retry makes, and a meter that counted transmissions would charge twice for
     * one message.
     *
     * ⚠️ **THIS SAID "THE SAME SHAPE AS {@see SendCredits}" AND THE TWO ARE NO
     * LONGER THE SAME SHAPE** (9182). A text is one credit and a text carrying
     * media is two; an email is one unit and has no second half to carry, since
     * `OutboundMessage::for()` throws on media addressed to the email channel.
     * **So this constant stays 1 and stops being an inheritance** — a lane
     * "harmonising" the two books would be applying an SMS ruling to a product
     * the owner did not price.
     */
    public const int UNITS_PER_SEND = 1;

    /**
     * The mailers whose sends are **not** metered — decision 3300's exemption,
     * and the whole of it.
     *
     * ⛔ **EMPTY, AND THE EMPTINESS IS THE HONEST STATE.** See the class
     * docblock: there is no tenant-connected-mailbox send path in this
     * application, so there is no subject for this exemption and no test can
     * drive its branch. `EmailMeteringTest` asserts the emptiness rather than
     * pretending otherwise, so that the day somebody adds an entry the build
     * turns red and sends them back to read what the exemption actually covers.
     *
     * ✅ **THE READING IS CONFIRMED (3438) AND THE LIST IS STILL EMPTY**, which is
     * not a contradiction: the ruling says a tenant's own mailbox is free, and
     * this application still has no way for a tenant to connect one. **A
     * confirmed exemption with no subject is still an exemption with no subject**
     * — what changed is that adding the entry no longer needs the owner asked
     * again, only a send path to exist. ⚠️ **A custom sending domain on our SES
     * is not that path** (3439).
     *
     * ⛔ **NEVER `gmail` AND NEVER `smtp`.** Both are platform transports —
     * Workspace (2093) and SES (2068) — and either would exempt the path that
     * carries our own mail. What belongs here is the *mailer name a tenant's own
     * connected mailbox is sent through*, if one is ever routed through
     * `PlatformMailer` at all.
     *
     * @var list<string>
     */
    public const array UNMETERED_MAILERS = [];

    /**
     * Why the balance moved, in words a person can read a year later.
     *
     * ⚠️ **IT WAS THE STAND-IN FOR THE MISSING PRODUCT DIMENSION AND IT IS NOT
     * ANY MORE** (3419). `credit_ledger` had one balance and `ref_type` is the
     * outreach message on both channels, so this sentence was the only thing
     * telling an email debit from an SMS debit after the fact. `product` does that
     * now, structurally, and a screen filters on it rather than matching prose.
     *
     * ✅ **IT SURVIVES ANYWAY, AND DELIBERATELY.** The rows this wrote are already
     * in the table and are backfilled as SMS; the sentence is the only thing on
     * them that says otherwise, so removing it would erase the sole record of what
     * those movements actually paid for. It costs one nullable column's worth of
     * text and it is what somebody reconciling that fortnight will read.
     */
    public const string SPEND_REASON = 'Platform email send';

    public function __construct(
        private readonly CreditLedger $credits = new CreditLedger,
        private readonly MailDrivers $drivers = new MailDrivers,
        private readonly DefaultsRegistry $defaults = new DefaultsRegistry,
    ) {}

    /**
     * Debit one email unit and record the send, together or not at all.
     *
     * ⚠️ **THE SHAPE IS {@see SendCredits::debitForSend()}'s, DELIBERATELY, AND
     * THE REASONING TRANSFERS WHOLE.** §3 rail 1: *"credit debit is transactional
     * with the send"*. The closure persists **our own write** — the
     * `outreach_messages` row a queued job will then send from — and never the
     * transport call, because a network round trip inside a database transaction
     * is what `BillingCheckout` refuses to do. The failure this rules out is the
     * expensive, silent one: **an email that went out and was never charged
     * for**, and its mirror, a unit taken for a message that was never written.
     *
     * ⚠️ **THE REFERENCE IS DERIVED FROM THE CLOSURE'S RESULT** (2548): the row
     * being paid for does not exist until the closure has run, so its id is not
     * knowable one line earlier. A movement with a `ref_type` and no `ref_id` is
     * refused by the ledger and by a CHECK, so this throws rather than writing an
     * untraceable spend.
     *
     * ✅ **THIS DEBITS THE EMAIL PRODUCT, AND THE SEAM THIS DOCBLOCK NAMED IS
     * CLOSED** (3419). It said: *"an email presently spends a unit from the same
     * balance a text spends one from … when the ledger gains those dimensions this
     * method is the only place in the email path that changes."* It was the only
     * place, and this is the change — one enum case on one call. The kind, the
     * pool selection and the monthly-then-top-up spill-over still belong to the
     * ledger's own API, and nothing above here knows about pools at all.
     *
     * ⚠️ **THE 1,000-EMAIL MONTHLY ALLOTMENT NOW HAS SOMEWHERE TO GO** (3298), and
     * `ResetMonthlyCredits` grants it. ⛔ **The email top-up pool still has no
     * funder** — `CreditKind::Purchase` is constructed nowhere in `app/` and a
     * `BillingTest` lint keeps it that way — so an account that exhausts its
     * thousand emails sends none until the period boundary. Fail-closed, stated
     * rather than discovered.
     *
     * ⚠️ **AN EXEMPT MAILER RUNS THE CLOSURE AND DEBITS NOTHING** (3300). The
     * send is still recorded — an unmetered send is still a message somebody
     * received, and `outreach_messages` is the consent record's counterpart
     * rather than a billing artefact.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $recordTheSend  Persists the send. ⚠️ **Our
     *                                              own writes only** — never the
     *                                              mail transport.
     * @param  int|null  $refId  The id of the thing being paid for, when the
     *                           caller already has it. Null takes it from the
     *                           closure's result.
     * @return TReturn
     *
     * @throws CreditMovementRefused The tenant has no credit left. ⚠️ **A caller
     *                               on a send path catches this and degrades** —
     *                               2904 and rule 43's surviving half (3294): an
     *                               exhausted balance never throws its way out
     *                               into the customer's journey.
     * @throws InvalidArgumentException when no reference can be established.
     * @throws QueryException whatever the closure's own writes raise.
     */
    public function debitForSend(
        callable $recordTheSend,
        string $refType,
        ?int $refId = null,
    ): mixed {
        return DB::transaction(function () use ($recordTheSend, $refType, $refId): mixed {
            $result = $recordTheSend();

            if (! $this->metersSendsThrough($this->drivers->active())) {
                return $result;
            }

            $reference = $refId ?? ($result instanceof Model ? (int) $result->getKey() : null);

            if ($reference === null) {
                throw new InvalidArgumentException(
                    'An email debit needs something to point at. No ref id was supplied and the '
                    .'closure returned nothing with a key, so the movement would be an unreferenced '
                    .'spend — refused by the ledger and by a CHECK, and traceable to no message.'
                );
            }

            // ⚠️ THE DEBIT COMES SECOND, AND THE ORDER IS NOT ARBITRARY.
            // `CreditLedger::record()` throws when the balance would go negative,
            // and a throw here rolls the send's own row back with it — so a
            // tenant who has run out gets no email and no debit, rather than an
            // email they were not charged for.
            $this->credits->record(
                product: CreditProduct::Email,
                kind: CreditKind::Consume,
                delta: -self::UNITS_PER_SEND,
                actor: 'system',
                reason: self::SPEND_REASON,
                refType: $refType,
                refId: $reference,
            );

            return $result;
        });
    }

    /**
     * Whether a send carried by this mailer is metered at all — decision 3300.
     *
     * ⚠️ **THE ONE DECISION POINT.** Everything the exemption is, is this method
     * and the array it reads. It takes a *mailer name* rather than a transport
     * for `MailDrivers`' own reason: two `smtp` mailers can point at two
     * different vendors, and the mailer key is the only thing that tells them
     * apart.
     */
    public function metersSendsThrough(string $mailer): bool
    {
        return ! in_array(mb_strtolower(trim($mailer)), $this->unmeteredMailers(), true);
    }

    /**
     * {@see self::UNMETERED_MAILERS}, through a declared type.
     *
     * ⚠️ **THE INDIRECTION IS NOT DECORATION AND IT IS NOT A WORKAROUND EITHER.**
     * An empty constant narrows to `array{}`, and Larastan then reports the
     * comparison above as *"will always evaluate to false"* — correctly, today.
     * The fix it invites is deleting the comparison, which would delete the
     * exemption 3300 asks for and leave nothing to reverse when the reading is
     * confirmed or withdrawn. Declaring the list's real type is the honest
     * answer: this is a configuration list that is *expected* to gain entries,
     * and its current emptiness is a fact about the product rather than about
     * the type.
     *
     * @return list<string>
     */
    private function unmeteredMailers(): array
    {
        return self::UNMETERED_MAILERS;
    }

    /**
     * What this many emails costs at the retail rate — $20 per 1,000 (3299).
     *
     * ⚠️ **NOTHING ON THE SEND PATH READS THIS, AND SAYING SO IS THE POINT**
     * (272's shape, sixteen instances and counting). The send path debits
     * *units*, because the SKUs are denominated in emails — $50 buys 2,500, not
     * "$50 of email". This prices a top-up, and **the top-up path is unbuilt**:
     * nothing in `app/` can sell an email credit today, which `BillingTest`'s
     * funding lint holds deliberately. It exists so that 3299's figure has one
     * home in code rather than living only in prose, and so that the tenfold
     * correction of 2063 cannot be silently reverted.
     *
     * Integer arithmetic throughout, and a remainder rounds **up**: a partial
     * thousand is charged as the emails it contains, never floored to a discount
     * nobody offered. There is no float anywhere in this method — `18` §Money
     * handling, and `Money` has no way to produce one. ⚠️ **The round-up branch
     * is unreachable at today's rate and no test claims otherwise**: $20 per
     * 1,000 is exactly two cents an email, so the remainder is always zero. It
     * is there for a rate that is not a whole number of cents per email, which
     * every SKU in 3301–3303 happens not to be.
     *
     * @throws InvalidArgumentException on a negative count, which is not a
     *                                  refund and must not be spelled as one.
     */
    public function retailCostOf(int $emails): Money
    {
        if ($emails < 0) {
            throw new InvalidArgumentException(
                "A negative number of emails ({$emails}) has no retail cost. Money coming back "
                .'to a tenant is a refund movement on the ledger, not a negative price.'
            );
        }

        $total = $emails * $this->retailCentsPerThousand();

        return Money::of(intdiv($total, 1_000) + ($total % 1_000 === 0 ? 0 : 1), $this->currency());
    }

    /**
     * What we charge per 1,000 emails, in integer cents — **read from the
     * registry, not from the constant beside it**.
     *
     * ✅ **THIS IS THE OWNER'S 2026-08-14 DIRECTIVE APPLIED** (decision 3415):
     * *"everything need to be adjustable in admin so we can make changes on
     * credits and plan packages as we go stuff does not need to be hard coded."*
     * The rate was a class constant only because the lane that wrote this class
     * could not edit the manifest — its own docblock said so, and named
     * `credits.rate.email_cents_per_thousand` as the key to point at once it
     * existed. It exists (3325), so this points at it.
     *
     * ⚠️ **THE CONSTANT SURVIVES AS THE FALLBACK AND NOT AS THE ANSWER.**
     * `intOr()` rather than `int()` is deliberate: a metering path that throws
     * because somebody removed a registry row would refuse a send over a missing
     * *price*, and 3294's surviving half of rule 43 is graceful degradation. The
     * fallback is the same figure the manifest seeds, so the two can only differ
     * if a key is deleted outright.
     */
    private function retailCentsPerThousand(): int
    {
        return $this->defaults->intOr(
            'credits.rate.email_cents_per_thousand',
            self::RETAIL_CENTS_PER_THOUSAND,
        );
    }

    /**
     * The currency every stored cents figure is denominated in.
     *
     * `billing.currency`, the registry key `PlanPricing` already reads, rather
     * than a second literal — 2058 puts multi-currency in scope and a hardcoded
     * `USD` here would be the copy that disagrees.
     */
    private function currency(): string
    {
        $currency = $this->defaults->stringOrNull('billing.currency');

        return strtoupper(trim($currency === null || trim($currency) === '' ? 'USD' : $currency));
    }
}
