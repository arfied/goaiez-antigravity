<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Contracts\Billing\GatewayRequestFailure;
use App\Enums\CreditPool;
use App\Enums\CreditProduct;
use App\Enums\CreditPurchaseStatus;
use App\Enums\CreditTopUpTier;
use App\Enums\CreditUnit;
use App\Exceptions\CreditChargeUnconfirmed;
use App\Exceptions\CreditPurchaseRefused;
use App\Models\AutoTopUpArrangement;
use App\Models\Business;
use App\Models\CreditPurchase;
use App\Models\Subscription;
use App\Services\Billing\AuthorizeNetApi;
use App\Services\Billing\AutoTopUps;
use App\Services\Billing\CreditLedger;
use App\Services\Billing\CreditTopUps;
use App\Services\Billing\GatewayRefusals;
use App\Services\Billing\StripeApi;
use App\Services\Billing\Subscriptions;
use App\Services\Billing\TopUpCatalog;
use App\Services\Config\DefaultsRegistry;
use App\Support\Billing\PurchaseConfirmation;
use App\Support\Billing\TopUpSku;
use App\Support\Money;
use App\Support\PlanPricing;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * The tenant's credit — what they have, and how to get more (3482).
 *
 * ⛔ **`CreditTopUps` HAD NO CALLER IN `app/` AND THIS IS IT.** 3482 stated the
 * gap rather than leaving it to be discovered: *"the funder is a service with
 * tests and no UI … what is owed is an account-billing screen that collects the
 * confirmation and calls one of two methods. Whoever builds it must not
 * re-implement the confirmation as a form boolean."* It does not — the
 * confirmation is a {@see PurchaseConfirmation} built from the figure this screen
 * actually rendered, and the tick box is only what makes the person press twice.
 *
 * ## Six balances, never one
 *
 * ⛔ **THREE PRODUCTS × TWO POOLS, REPORTED APART, AND NOTHING HERE ADDS TWO OF
 * THEM TOGETHER** (3315, 3419). The three are not even in the same unit — texts
 * and emails are counts of sends, AI is money — and the two lifetimes differ
 * inside each one: the monthly allotment restarts on the first of the month
 * (3440) and purchased credit never expires (3307). A tenant shown one number
 * cannot understand why a campaign was refused, which is the failure this layout
 * exists to prevent.
 *
 * ⚠️ **AND THE HARDEST THING IN THE MODEL IS EXPLAINED HERE OR NOWHERE** (3309):
 * an SMS broadcast spends purchased credit **only**, so a tenant holding 500
 * monthly texts and no purchased ones is refused a campaign and is right to think
 * that looks like a bug. The screen says it in the tenant's own words, on the
 * text card, before they meet it.
 *
 * ## Wording the inactive plan, which is a ruling and not a style choice
 *
 * ⛔ **NEVER "EXPIRED"** (3441, 3466). Purchased credit is unspendable while the
 * plan is not running and is otherwise untouched — *"'your credits are waiting
 * for you' is the honest framing and 'expired' is not, because they have not."*
 * The note this screen renders says the credit is waiting and that nothing has
 * been taken away, and a test pins both halves.
 *
 * ## Where CONFIRM happens
 *
 * ⛔ **`CLAUDE.md` RESERVES CONFIRM FOR THREE THINGS AND *"ANYTHING THAT SPENDS
 * MONEY"* IS THE SECOND.** Pressing a pack does not buy it: it opens a panel
 * naming the amount and what it grants, with an unticked box under it. The figure
 * the panel shows is captured at that moment and travels to
 * {@see PurchaseConfirmation}, because prices are admin-editable (3415) and can
 * genuinely move between the render and the submit — `CreditPurchases::open()`
 * then refuses a purchase whose SKU price no longer matches, which is *"never
 * bill by surprise"* as a comparison rather than as an intention (3478).
 *
 * ⚠️ **THE CAPTURED FIGURE IS A PUBLIC LIVEWIRE PROPERTY AND SO IS EDITABLE BY
 * THE BROWSER — WHICH IS SAFE ONLY BECAUSE OF THAT COMPARISON.** A tampered
 * amount does not buy anything cheaply; it fails the check in `open()` and no
 * charge is made. Nothing here is trusted to be the price.
 *
 * ## Which gateway, and why the screen has to know
 *
 * ⚠️ **THE TWO METHODS ARE NOT INTERCHANGEABLE AND THE VENDORS' ASYMMETRY IS
 * WHY** (3448–3455). Authorize.Net charges a **stored** profile here and answers
 * at once; Stripe collects on its own page, so the browser leaves. A tenant with
 * no stored profile cannot be charged on Authorize.Net at all (3487) and goes to
 * Stripe, which is where a card is collected — SAQ-A is preserved by there being
 * no card field anywhere in this application.
 *
 * ⚠️ **VALIDATION IS INLINE RATHER THAN IN A FORM REQUEST**, which is
 * `Account\WidgetInstall`'s and `Account\Knowledge`'s shape and not a departure:
 * a `FormRequest` is resolved for an HTTP controller action and a Livewire action
 * is neither. `$this->validate()` is the framework's own answer here.
 *
 * ⚠️ **AUTHORIZATION IS A POLICY** (`CLAUDE.md`), and the read and the write are
 * split the way `WidgetInstall` splits them: anyone signed in may see what is
 * left — a manager needs to know whether the texts run out on Friday — and only
 * the owner may spend money. `SubscriptionPolicy::purchaseCredit()` carries the
 * argument.
 *
 * ## Buying more automatically — the arrangement (3517's first named gap)
 *
 * ⛔ **`AutoTopUps::agree()` HAD NO CALLER IN `app/` AND THIS IS IT.** 3515 stated
 * the gap in the same words 3482 used about the funder one slice earlier, and
 * 3517 listed it first: *"no tenant-facing screen … whoever builds the screen must
 * not re-implement the confirmation as a form boolean, and must collect the
 * ceiling as the one tenant-facing setting 3306 authorises."* Nothing could create
 * an arrangement, so no arrangement could exist, so `RunAutoTopUps` swept an empty
 * set every night — 272's shape with a charge attached.
 *
 * ⛔ **THE OFF STATE IS THE ABSENCE OF A ROW AND THIS SCREEN NEVER CREATES ONE AS
 * A SIDE EFFECT OF BEING LOOKED AT** (3488, 2064). Rendering reads; only
 * {@see self::agreeToAutomatic()} writes, and it refuses without the tick box.
 *
 * ⛔ **CONFIRM IS ON THE ARRANGEMENT** (2064, 3490), so the panel is the one the
 * packs above already use: the per-charge amount, what it buys, the monthly limit,
 * and an unticked box. The sentence it prints is the sentence stored on the row,
 * from one method, because *"what did you show them"* is the question a chargeback
 * asks about a charge made months later with nobody watching.
 *
 * ⚠️ **THE CEILING IS OFFERED AS MULTIPLES OF ONE PAYMENT, AND THAT IS NOT A
 * SIMPLIFICATION.** 3493 bounds the charge *about to be made*, so a limit of $75
 * against a $50 payment stops at exactly the same place a limit of $50 does —
 * offering a free-text figure would offer a difference that does not exist, and
 * integer cents come out of a multiplication of two integers rather than out of a
 * parsed dollar string (`18` §Money handling).
 *
 * ⛔ **AN ARRANGEMENT WITHOUT A CARD ON FILE SUSPENDS ITSELF IN THREE NIGHTS.**
 * The automatic charge is `CreditTopUps::chargeStoredCard()`, which refuses a
 * tenant with no stored Authorize.Net profile (3487); the sweep counts that
 * refusal as a decline, and three consecutive declines suspend the arrangement
 * (3501). So this screen refuses to arrange one at all without a card, and says
 * why — Stripe's answer is a hosted page, and there is no browser at 3am.
 *
 * ⚠️ **NOTHING TELLS A TENANT WHEN AN ARRANGEMENT SUSPENDS ITSELF, AND THE SCREEN
 * SAYS SO RATHER THAN IMPLYING OTHERWISE** (3517's honest gap). This page is the
 * only surface that shows it, so the page states that it is.
 */
#[Layout('components.account.layout')]
final class Credit extends Component
{
    /**
     * The pack a person has pressed, awaiting their confirmation.
     *
     * Held as the enum's stored value rather than as the enum, because a Livewire
     * property round-trips through JSON and a tampered value has to come back as
     * an unrecognised string this component refuses rather than as a fatal.
     */
    public ?string $pendingProduct = null;

    public ?string $pendingTier = null;

    /**
     * The price, in integer cents, exactly as the confirmation panel printed it.
     *
     * ⛔ **CAPTURED WHEN THE PANEL OPENED, NEVER RE-READ AT SUBMIT.** That is the
     * whole of 3478: re-reading the catalogue here would charge whatever the price
     * had become and file a confirmation record appearing to authorise it.
     */
    public ?int $shownPriceCents = null;

    /**
     * What that pack grants, in the registry's own denomination — a count of
     * sends for texts and emails, integer **cents** for AI (3303, 3480).
     *
     * ⚠️ **NEVER SPENT AND NEVER CONVERTED HERE.** It is display only;
     * `TopUpCatalog` is the one place a seed becomes ledger units.
     */
    public ?int $shownGrantSeed = null;

    /** The tick box. Unchecked by default, and `accepted` is what refuses a miss. */
    public bool $confirmed = false;

    /**
     * Whether the browser has just come back from the payment page.
     *
     * ⚠️ **IT IS A "WE HAVE ASKED", NOT A BALANCE** (2056, 3457). The credit is
     * written by the gateway's notification and by nothing else, so this says the
     * payment was taken and the balance follows — never that it has landed.
     */
    public bool $paymentTaken = false;

    public bool $paymentStopped = false;

    /**
     * The product whose automatic arrangement is being agreed, awaiting the box.
     *
     * Held as the stored value rather than as the enum, for `$pendingProduct`'s
     * reason: a tampered value comes back as an unrecognised string this component
     * refuses rather than as a fatal.
     */
    public ?string $arrangingProduct = null;

    /**
     * What one automatic payment costs, in integer cents, exactly as the panel
     * printed it — captured when the panel opened and never re-read at submit.
     *
     * ⚠️ **`AutoTopUps::agree()` COMPARES IT AGAINST THE SKU AND REFUSES A
     * MISMATCH**, which is what makes a browser-editable public property safe
     * here, the same way `$shownPriceCents` is safe above.
     */
    public ?int $arrangementPriceCents = null;

    /**
     * What one automatic payment buys, in the registry's own denomination.
     *
     * ⛔ **`agree()` DOES NOT CHECK THIS ONE — {@see self::agreeToAutomatic()}
     * DOES, AT SUBMIT, AGAINST THE SKU.** The class docblock above claimed the
     * service's comparison covered every browser-editable property here; it
     * covers the price. Unchecked, this figure reached the *stored wording* — so
     * a tenant could file *"…charge you $50 for 1,000,000 text messages"* in our
     * own evidence table, and `AutoTopUps::confirmationFor()` would put it on
     * every `credit_purchases` row made under the arrangement. It is
     * self-directed and steals nothing; it forges the document we would produce
     * to defend a chargeback, which is the more expensive of the two.
     */
    public ?int $arrangementGrantSeed = null;

    /**
     * The monthly limit, in integer cents — 3306's one tenant-writable setting.
     *
     * ⛔ **VALIDATED AGAINST THE OFFERED FIGURES RATHER THAN TRUSTED.** A tampered
     * ceiling can only ever raise the tenant's own limit on their own spending,
     * and it would still be recorded truthfully in the wording — but a stored
     * figure that is not a multiple of one payment behaves identically to the
     * multiple below it (3493), so accepting one would store a number that means
     * nothing.
     */
    public ?int $ceilingCents = null;

    /** The arrangement's own tick box. Unchecked, always, and `accepted`. */
    public bool $automaticConfirmed = false;

    /**
     * ⚠️ **THE LONGEST MONTHLY LIMIT ON OFFER, AS A COUNT OF PAYMENTS.**
     *
     * Ten rather than a figure in cents, because the payment's price is
     * admin-editable (3415) and a cents cap would drift away from it on the first
     * Ops edit. At today's $50 automatic payment it is $50 to $500 a month.
     */
    private const int MOST_PAYMENTS_A_MONTH = 10;

    /**
     * How many past payments this screen lists (4842).
     *
     * ⚠️ **A CAP RATHER THAN A PAGER, AND THAT IS THE SMALLER SUPPORT SURFACE.**
     * A tenant on the largest automatic arrangement makes ten payments a month, so
     * two dozen covers the recent past for everybody; a pager on a page whose main
     * subject is a balance would be a second navigation for a list nobody reads
     * twice. Older payments are named as available on request rather than
     * silently dropped — a receipt list that stops without saying so reads as a
     * missing payment.
     */
    private const int RECEIPTS_SHOWN = 24;

    public function mount(): void
    {
        // Two flags and nothing else travel in the return URL — no amount, no
        // reference, no identifier. Whatever a person edits into these query
        // strings changes a sentence and touches no money.
        $this->paymentTaken = request()->query('paid') === '1';
        $this->paymentStopped = request()->query('stopped') === '1';
    }

    /**
     * Open the confirmation for one pack.
     *
     * ⚠️ **THE PRICE AND THE GRANT ARE READ ONCE, HERE, AND THE PANEL PRINTS WHAT
     * WAS READ.** Rendering the catalogue again inside the panel would let the two
     * disagree within one page load.
     */
    public function choose(string $product, string $tier, TopUpCatalog $catalog): void
    {
        Gate::authorize('purchaseCredit', Subscription::class);

        $sku = $this->skuFor($catalog, $product, $tier);

        if (! $sku instanceof TopUpSku) {
            return;
        }

        $this->pendingProduct = $sku->product->value;
        $this->pendingTier = $sku->tier->value;
        $this->shownPriceCents = $sku->price->minorUnits;
        $this->shownGrantSeed = $sku->grantSeed;

        // Unticked every time the panel opens. A box that stayed ticked from a
        // previous pack would carry one amount's agreement onto another's.
        $this->confirmed = false;
        $this->resetErrorBag();
    }

    /** Close the confirmation without buying anything. */
    public function cancelPurchase(): void
    {
        $this->pendingProduct = null;
        $this->pendingTier = null;
        $this->shownPriceCents = null;
        $this->shownGrantSeed = null;
        $this->confirmed = false;
        $this->resetErrorBag();
    }

    /**
     * Charge the card on file, or send the browser to the payment page.
     *
     * ⚠️ **NOTHING HERE DECIDES WHAT A PURCHASE IS WORTH.** The confirmation
     * carries the figure that was shown; `CreditPurchases::open()` compares it
     * against the SKU and refuses a mismatch, and the credit itself is written by
     * the gateway's notification long after this method has returned (3456).
     *
     * ⛔ **THE GRANT IS COMPARED HERE, THOUGH, AND `open()` DOES NOT COMPARE IT.**
     * The same hole the fix wave found on the arrangement panel exists on this
     * one, because it is the same pair of properties: the price is checked one
     * layer down and the *grant* is checked nowhere, while both reach
     * `confirmation_wording`. A tenant editing it files *"You are paying $50 for
     * 1,000,000 text messages"* in our own evidence table — self-directed, so it
     * buys nothing, and it forges the record we would answer a chargeback with.
     */
    public function buy(
        CreditTopUps $topUps,
        Subscriptions $subscriptions,
        DefaultsRegistry $registry,
        TopUpCatalog $catalog,
    ): void {
        Gate::authorize('purchaseCredit', Subscription::class);

        $business = $this->business();

        // ⛔ THE PAGE ALREADY SAYS THIS CREDIT CANNOT BE SPENT, AND IT WAS STILL
        // SELLING IT (3867). `CreditTopUps` refuses the same thing one layer down
        // and is driven by its own test; this is here because the honest answer to
        // a press is a sentence about the plan rather than the generic "we could
        // not start that payment" a bare refusal would produce — and because the
        // absent button is not the gate (398).
        if (! $subscriptions->isEntitled($business)) {
            $this->cancelPurchase();

            // ⚠️ NEITHER "again" NOR "what you have already bought" (9332). From
            // 2026-08-25 the commonest way to reach this refusal is a free trial
            // that ran out on somebody who has never had a plan and has never
            // bought a credit, and both phrases are false sentences to them. The
            // panel at the top of this page carries the date and the reason; a
            // toast is not where that belongs.
            Toaster::error('Start your plan to buy credit. Anything you have already bought is waiting for you and nothing has been taken away.');

            return;
        }

        $product = CreditProduct::tryFrom($this->pendingProduct ?? '');
        $tier = CreditTopUpTier::tryFrom($this->pendingTier ?? '');
        $cents = $this->shownPriceCents;

        if (! $product instanceof CreditProduct || ! $tier instanceof CreditTopUpTier || $cents === null) {
            // Nothing was chosen, or the choice came back unrecognisable. Closing
            // the panel is the honest answer: there is no amount to confirm.
            $this->cancelPurchase();

            return;
        }

        $this->validate(
            ['confirmed' => ['accepted']],
            ['confirmed.accepted' => 'Tick the box to confirm the amount before we charge anything.'],
        );

        // ⚠️ THE GRANT ONLY, AND NOT THE PRICE (398). `CreditPurchases::open()`
        // refuses a price that is not the SKU's and has its own test driving that;
        // a second comparison in front of it would refuse first and leave the real
        // guard unfalsifiable.
        $sku = $this->pricedSku($catalog, $product, $tier);

        if (! $sku instanceof TopUpSku || $sku->grantSeed !== $this->shownGrantSeed) {
            Toaster::error('That has changed since this page opened. Nothing has been charged — open it again to see what it costs now.');

            $this->cancelPurchase();

            return;
        }

        $wording = $this->confirmationWording();

        if ($wording === null) {
            $this->cancelPurchase();

            return;
        }

        try {
            $confirmation = PurchaseConfirmation::given(
                actor: $this->actor(),
                amountShown: Money::of($cents, $this->currency($registry)),
                wording: $wording,
            );

            if ($this->hasStoredCard($subscriptions->for($business))) {
                $topUps->chargeStoredCard($business, $product, $tier, $confirmation);

                $this->cancelPurchase();

                // Outcome language, no personal data, and it does not claim the
                // balance has moved — the notification is what credits it (3457).
                Toaster::success('Payment taken — your credit appears here as soon as it clears');

                return;
            }

            if (! StripeApi::isLive()) {
                $this->cancelPurchase();
                Toaster::error('Credit purchases are currently unavailable. Please contact us to top up.');
                return;
            }

            $url = $topUps->checkoutUrl(
                $business,
                $product,
                $tier,
                $confirmation,
                route('account.credit', ['paid' => '1']),
                route('account.credit', ['stopped' => '1']),
            );
        } catch (CreditPurchaseRefused) {
            // ⛔ NEVER THE EXCEPTION'S OWN MESSAGE. Those are written for an
            // operator and quote seeded figures and key names; this is the
            // sentence a customer reads.
            Toaster::error('We could not start that payment. Nothing has been charged — ask us and we will sort it out.');

            return;
        } catch (CreditChargeUnconfirmed) {
            // ⛔ THE ONE SENTENCE THAT MAY NOT SAY "NOTHING HAS BEEN CHARGED".
            // The request reached the gateway and the answer was lost, so the
            // money may well have moved — and the branch below said the opposite
            // to this exact case until the fix wave, which is billing by surprise
            // with a reassurance attached. It also must not invite a second press:
            // a retry here is how somebody is charged twice.
            $this->cancelPurchase();

            Toaster::error('We could not tell whether that payment went through. Do not try again — check back shortly, and ask us if it is not clear.');

            return;
        } catch (GatewayRequestFailure $e) {
            // ⛔ **THIS ARM COULD NOT BE REACHED BY THE COMMONEST CAUSE OF IT,
            // AND THE PRESS ANSWERED A 500 — 9294.** An unset gateway
            // credential raised a bare `RuntimeException` from
            // `PlatformCredentials::get()`, outside both clients' error
            // classification, so it was none of the three types caught here.
            // Livewire renders a failed update in a **full-page modal**
            // (`showHtmlModal`, verified against the shipped bundle), so a
            // person who pressed Buy met Laravel's *Server Error* page over the
            // credit screen — and a `pending` `credit_purchases` row was left
            // behind. **Every deployment of this application has been in that
            // state.**
            //
            // ⛔ **AND THE SENTENCE BELOW WOULD HAVE BEEN WRONG EVEN ONCE THE
            // ARM CAUGHT IT**: it names the payment provider as the refuser and
            // invites a retry, when the provider was never asked and no retry
            // can succeed. `configuration` is the classified reason both
            // exception classes already carry, and `Account\Plan` has branched
            // on it for months.
            //
            // ⛔ **AND THE CATCH IS THE CONTRACT NOW, NOT A UNION OF TWO CLASS
            // NAMES** (12240). `AuthorizeNetRequestFailed|StripeRequestFailed`
            // compiled only because the two classes happened to declare the
            // same four properties, and the day one of them was renamed this
            // press would have failed at runtime with a customer standing on
            // it. {@see GatewayRequestFailure} is what those four are.
            // ⛔ **THIS SURFACE TOLD THE BUYER SOMETHING AND TOLD NOBODY ELSE
            // ANYTHING** (12420). The four flags below decide between four
            // sentences, and until this line existed the choice left no trace at
            // all: `Account\Plan`, `BillingController`,
            // `AuthorizeNetCheckoutController` and `CancelSubscriptionController`
            // have each written a warning on this exact fault for months, and
            // this file contained no `Log::` call anywhere.
            //
            // ⛔ **AND THE TWO CATEGORIES EXIST ONLY IN A LOG LINE.**
            // {@see GatewayRequestFailure::$malformedRequest} says so in as many
            // words: the customer copy converges with `configuration` — both
            // arms below send {@see GatewayRefusals::cannotTakePayment()} — so
            // *"paste a credential"* and *"our own request is wrong"* are
            // indistinguishable from every surface a person can read. Without
            // this line the distinction the third category was built for does
            // not exist on this path.
            //
            // ⚠️ **`retryable` IS LOGGED HERE AND ON NONE OF THE OTHER FOUR,
            // BECAUSE THIS IS THE ONLY SCREEN WITH FOUR ARMS.** With
            // `configuration` and `malformed_request` both false, a vendor that
            // never answered and a card the vendor genuinely declined write an
            // identical line — and they are the two entries in this catch whose
            // operator responses differ most: one is an outage to watch, the
            // other is nothing for us to do at all.
            //
            // ⛔ **THE CLASSIFIED REASON, NEVER THE VENDOR'S MESSAGE**, and
            // never the throwable — both exception classes' rule. The `text`
            // field quotes the value it rejected, and a nonce and a customer
            // profile id are both arguments a few frames up.
            //
            // ⚠️ **A TOAST IS NOT THIS RECORD** (104). The sentences below are
            // written for the buyer; this line is the operator's, and it is the
            // only one of the two that names which fault it was.
            Log::warning('a credit top-up was refused by the gateway', [
                'business_id' => $business->id,
                'reason' => $e->reason,
                'configuration' => $e->configuration,
                'malformed_request' => $e->malformedRequest,
                'retryable' => $e->retryable,
            ]);

            if ($e->configuration) {
                // ⛔ **THE SENTENCE STOPPED SAYING *"TRY AGAIN"* AND THE BUTTON
                // KEPT SAYING IT — FOUND BY RENDERING THE SCREEN (12432).** Every
                // arm here returned with the confirmation panel still open, the
                // tick still ticked and *"Yes, charge me $50 now"* live under a
                // toast that reads *"reply to any email from us and we will sort
                // it out with you."* {@see GatewayRefusals}'s own docblock is
                // explicit that none of its sentences invites a retry, because
                // *"nothing a customer does can paste a credential, and 'try
                // again' on this fault is the loop `/billing` was in — press,
                // bounce, press."* **The copy was repaired and the control was
                // not.**
                //
                // ⚠️ **ONLY THE TWO ARMS WHOSE SENTENCE REFUSES A RETRY.** The
                // `retryable` arm below says *"please try again in a moment"* and
                // a live button is what that sentence means; the card arm is the
                // one place the buyer can act. Closing the panel there would
                // withdraw the control the sentence just offered.
                $this->cancelPurchase();

                Toaster::error(GatewayRefusals::cannotTakePayment());

                return;
            }

            // ⛔ **THE ARM THIS SCREEN COULD NOT TAKE, AND THE ONLY SURFACE
            // WHERE ITS ABSENCE CHANGED WHAT A CUSTOMER READS** (11965, 12241).
            // A malformed-request code means the vendor read **our** request and
            // objected to its shape: `E00014` is a required field we did not
            // send, `E00003` is our own key ordering, and on Stripe it is a
            // parameter of ours that could not be read. None of the five is in
            // `CONFIGURATION_CODES`, so every one of them fell to the last line
            // below and told somebody whose card is perfectly good to go and
            // check it — **the exact sentence the category was built to stop**,
            // reintroduced one arm over because this file had no arm.
            //
            // ⚠️ **THE SENTENCE IS THE CREDENTIAL ONE AND THE CATEGORIES ARE
            // STILL TWO**, which is `AuthorizeNetCheckoutController::refusal()`'s
            // reasoning unchanged: from where the buyer stands both are *"nothing
            // you can do, nothing was charged, talk to us"*, and what differs is
            // the operator's next move.
            if ($e->malformedRequest) {
                // The same sentence, so the same control: a fault in our own
                // request is not one a second press can change (12432).
                $this->cancelPurchase();

                Toaster::error(GatewayRefusals::cannotTakePayment());

                return;
            }

            // ⛔ **THE THIRD WRONG ARM, FOUND WHILE FIXING THE SECOND, AND IT
            // NEEDS NO NEW FLAG** (12242). This screen is the only one of the
            // four that never asked `retryable`. So a Stripe 500, a rate limit
            // and a connection failure — **`StripeRequestFailed::unreachable()`,
            // which says in its own name that nothing came back to classify** —
            // all reached the last line and told the buyer *"your payment
            // provider did not accept that"* about a provider that never
            // answered. The other three surfaces have branched on this for
            // months and this one is the odd file out.
            //
            // ⚠️ **THE AUTHORIZE.NET HALF OF IT WAS ALREADY SAFE FOR AN
            // UNRELATED REASON** and that is why it was invisible:
            // `AuthorizeNetRequestFailed::unreachable()` also sets
            // `outcomeUnknown`, so `CreditTopUps::chargeStoredCard()` converts it
            // to a `CreditChargeUnconfirmed` that the arm above catches. Stripe
            // carries no such flag, so the hole was one gateway wide.
            //
            // ✅ **THE FIFTH COPY IS MOVED, AND ONE OF THE FIVE WAS NOT THE
            // SAME SENTENCE** (12424). The note that stood here called it owed
            // and named four inline siblings; {@see GatewayRefusals::cannotReachGateway()}
            // is now the only spelling in `app/`. ⛔ **`CancelSubscriptionController`
            // was carrying *"your* payment provider" with different
            // punctuation**, so the exact-string grep this note invited finds
            // four and reports the move complete — and the pronoun was a wrong
            // claim rather than a variant, because what did not answer is our
            // own gateway on our own contract. That method's docblock carries
            // the argument and the one question the cancel arm still owes.
            if ($e->retryable) {
                Toaster::error(GatewayRefusals::cannotReachGateway());

                return;
            }

            // ⚠️ **WHAT IS LEFT IS THE CARD, AND ON THIS SCREEN THAT IS LITERAL.**
            // The remaining population is an Authorize.Net decline on the profile
            // already stored against this tenant's plan — a `responseCode` other
            // than 1 with a decline code — so *"the card on your plan"* names the
            // right object. It is the one arm here where the buyer can act.
            Toaster::error('Your payment provider did not accept that. Nothing has been charged — check the card on your plan, or try again.');

            return;
        }

        $this->redirect($url);
    }

    /**
     * The sentence the person is agreeing to, verbatim.
     *
     * ⛔ **ONE METHOD, READ BY THE PANEL AND WRITTEN TO THE RECORD.** A second
     * copy composed at submit time is how *"what did you show them"* stops being
     * answerable — and that question is the only reason `PurchaseConfirmation`
     * stores a wording rather than a boolean.
     */
    public function confirmationWording(): ?string
    {
        $product = CreditProduct::tryFrom($this->pendingProduct ?? '');
        $amount = $this->confirmationAmount();
        $seed = $this->shownGrantSeed;

        if (! $product instanceof CreditProduct || $amount === null || $seed === null) {
            return null;
        }

        return 'You are paying '.$amount
            .' for '.$this->grantLabel($product, $seed, $this->currency(app(DefaultsRegistry::class))).'. '
            .'It is added to the credit you have bought, which never runs out of time.';
    }

    /**
     * The amount on the button and in the tick box's own label.
     *
     * ⚠️ **THE SAME FIGURE AS THE SENTENCE ABOVE, BECAUSE IT IS THE SAME METHOD
     * CALL.** Formatting `$shownPriceCents` a second time in the template would
     * be a second surface printing cents, which is exactly what decision 512
     * leaves `PlanPricing` as the only one of.
     */
    public function confirmationAmount(): ?string
    {
        $cents = $this->shownPriceCents;

        if ($cents === null) {
            return null;
        }

        return PlanPricing::format(Money::of($cents, $this->currency(app(DefaultsRegistry::class))));
    }

    /**
     * Open the arrangement panel for one product.
     *
     * ⛔ **OPENING IT ARRANGES NOTHING** (3488). No row is written until the box is
     * ticked and {@see self::agreeToAutomatic()} is called, because the absence of
     * a row is the only off state a bad default cannot flip.
     *
     * ⚠️ **THE CARD IS CHECKED BEFORE THE PANEL OPENS, NOT ONLY AT SUBMIT.**
     * Offering to arrange something that would suspend itself in three nights
     * (3487, 3501) is worse than refusing it, because the tenant would believe it
     * was running.
     */
    public function arrange(
        string $product,
        TopUpCatalog $catalog,
        Subscriptions $subscriptions,
        DefaultsRegistry $registry,
    ): void {
        Gate::authorize('purchaseCredit', Subscription::class);

        if (! $this->hasStoredCard($subscriptions->for($this->business()))) {
            Toaster::error('Buying automatically needs a card saved on your account. Save one on your plan and come back.');

            return;
        }

        $sku = $this->skuFor($catalog, $product, CreditTopUpTier::Automatic->value);

        if (! $sku instanceof TopUpSku) {
            return;
        }

        $this->arrangingProduct = $sku->product->value;
        $this->arrangementPriceCents = $sku->price->minorUnits;
        $this->arrangementGrantSeed = $sku->grantSeed;
        $this->ceilingCents = $this->startingCeilingCents($sku, $registry, $this->arrangementFor($sku->product));

        // Unticked every time, for `choose()`'s reason: a box that stayed ticked
        // would carry one product's agreement onto another's.
        $this->automaticConfirmed = false;
        $this->resetErrorBag();
    }

    /**
     * The monthly limit changed, so the agreement to the old one lapses.
     *
     * ⛔ **THE BOX UNTICKS ITSELF, FOR {@see self::choose()}'s REASON APPLIED TO
     * THE FIGURE THAT ACTUALLY VARIES HERE.** This class states that rule twice
     * for the *product* — *"a box that stayed ticked from a previous pack would
     * carry one amount's agreement onto another's"* — and the select is bound
     * `.live` while the box is deferred, so a tenant could tick at $50, change the
     * limit to $500 and submit an agreement to $500 whose only tick happened at
     * $50. The ceiling is the amount in this panel.
     *
     * ⚠️ **WHAT THE TEST PROVES IS NARROWER THAN THAT SENTENCE, AND SAYING SO IS
     * THE RULE** (352, 397, 565, 809). `Livewire::test()->set()` runs this hook, so
     * the test proves the hook unticks the box — it does **not** prove the browser
     * ordering the paragraph above describes, because `Livewire::test()` is not a
     * browser and there is no `.live` round trip in it. The ordering claim rests
     * on the template's own bindings, which a reader can check in one place;
     * a Browser-suite case is what would prove it, and there is none.
     */
    public function updatedCeilingCents(): void
    {
        $this->automaticConfirmed = false;
    }

    /** Close the arrangement panel without agreeing to anything. */
    public function dismissArrangement(): void
    {
        $this->arrangingProduct = null;
        $this->arrangementPriceCents = null;
        $this->arrangementGrantSeed = null;
        $this->ceilingCents = null;
        $this->automaticConfirmed = false;
        $this->resetErrorBag();
    }

    /**
     * Record the agreement — the only path in this application that creates one.
     *
     * ⛔ **AN EXISTING ARRANGEMENT IS CANCELLED AND A NEW ONE AGREED, RATHER THAN
     * THE OLD ONE EDITED.** `ceiling_cents` and the four agreement columns are
     * guarded (3489), and writing them from a component is precisely the
     * fabricated confirmation that guard exists to prevent. Cancelling keeps the
     * old row (3507), so *"what did they agree to in March"* stays answerable
     * after the limit moves in April.
     *
     * ⚠️ **THE PAIR IS ONE TRANSACTION.** `agree()` refuses for four reasons, all
     * of them before it writes; without the transaction a refusal would leave a
     * tenant who meant to change their limit with automatic buying switched off.
     *
     * ⛔ **THE NEW ARRANGEMENT'S MONTHLY WINDOW IS NOT EMPTY, AND THIS DOCBLOCK
     * SAID THE OPPOSITE UNTIL THE FIX WAVE.** It was a blocker rather than a
     * wording problem: `AutoTopUps` counted the window by arrangement id, so
     * re-agreeing reset it, and **lowering the limit was the cheapest way to spend
     * more** — $500 taken under a $500 ceiling, a nervous tenant dropping it to
     * $50, another $50 charged the same month, and nothing bounding the repeat.
     * The service now counts by account and product, so the month's spending
     * carries across the change and the wording no longer has to hedge.
     *
     * ⛔ **THE SKU IS RE-READ AND COMPARED HERE, NOT ONLY IN `agree()`.** That
     * method compares the price and nothing else, so `$arrangementGrantSeed` — a
     * public property, editable by the browser — reached the stored wording
     * unchecked. It steals nothing, because a tenant can only lie to themselves
     * about what their own $50 buys; what it does is manufacture the document we
     * would produce to defend a chargeback, and that record rides every
     * `credit_purchases` row made under the arrangement.
     */
    public function agreeToAutomatic(
        AutoTopUps $autoTopUps,
        Subscriptions $subscriptions,
        DefaultsRegistry $registry,
        TopUpCatalog $catalog,
    ): void {
        Gate::authorize('purchaseCredit', Subscription::class);

        $business = $this->business();
        $product = CreditProduct::tryFrom($this->arrangingProduct ?? '');
        $cents = $this->arrangementPriceCents;

        if (! $product instanceof CreditProduct || $cents === null) {
            $this->dismissArrangement();

            return;
        }

        // ⛔ THE CONTROL BEING ABSENT IS NOT THE GATE (398). A Livewire action is
        // reachable by anybody who can reach the component, so the card is asked
        // for again here and not only where the button is drawn.
        if (! $this->hasStoredCard($subscriptions->for($business))) {
            Toaster::error('Buying automatically needs a card saved on your account. Save one on your plan and come back.');

            $this->dismissArrangement();

            return;
        }

        $this->validate([
            'automaticConfirmed' => ['accepted'],
            'ceilingCents' => ['required', 'integer', Rule::in($this->ceilingChoiceCents())],
        ], [
            'automaticConfirmed.accepted' => 'Tick the box to agree to this before we set it up.',
            'ceilingCents.in' => 'Choose one of the monthly limits shown.',
            'ceilingCents.required' => 'Choose the most we may spend in a month.',
        ]);

        // ⛔ THE GRANT IS COMPARED AGAINST THE PRICE LIST AT SUBMIT, BECAUSE
        // NOTHING ELSE COMPARES IT. It is a public property and it reaches the
        // stored wording, which every charge made under this arrangement then
        // carries.
        //
        // ⚠️ AND THE PRICE IS DELIBERATELY NOT COMPARED HERE (398). `agree()`
        // already refuses a confirmation whose amount is not the SKU's, driven by
        // its own test; a second comparison in front of it would refuse first and
        // leave that one unfalsifiable — the exact shape this codebase keeps
        // finding. What is added is the check that was missing, and nothing else.
        $sku = $this->automaticSku($catalog, $product);

        if (! $sku instanceof TopUpSku || $sku->grantSeed !== $this->arrangementGrantSeed) {
            Toaster::error('That has changed since this page opened. Nothing has been set up — open it again to see what it costs now.');

            $this->dismissArrangement();

            return;
        }

        $wording = $this->arrangementWording();

        if ($wording === null) {
            $this->dismissArrangement();

            return;
        }

        $ceiling = Money::of((int) $this->ceilingCents, $this->currency($registry));

        try {
            $confirmation = PurchaseConfirmation::given(
                actor: $this->actor(),
                amountShown: Money::of($cents, $this->currency($registry)),
                wording: $wording,
            );

            DB::transaction(function () use ($autoTopUps, $business, $product, $ceiling, $confirmation): void {
                $existing = $this->arrangementFor($product);

                if ($existing instanceof AutoTopUpArrangement) {
                    $autoTopUps->cancel($existing, $this->actor());
                }

                $autoTopUps->agree($business, $product, CreditTopUpTier::Automatic, $ceiling, $confirmation);
            });
        } catch (CreditPurchaseRefused|QueryException) {
            // ⛔ NEVER THE EXCEPTION'S OWN MESSAGE — `buy()`'s rule. Those quote
            // seeded figures and key names and are written for an operator.
            //
            // ⚠️ AND `QueryException` IS NOT DEFENSIVE PADDING. The readable
            // refusal inside `agree()` reads the live arrangement before it
            // writes, so two submits racing each other both pass it and the
            // partial unique index refuses the second — reaching a tenant as an
            // uncaught 500 on the action that arranges a standing charge, with
            // the transaction already rolled back and nothing wrong with the
            // account. The index is the correct refusal; this is the sentence
            // that goes with it.
            Toaster::error('We could not set that up. Nothing has been charged — ask us and we will sort it out.');

            return;
        }

        $this->dismissArrangement();

        Toaster::success('Set up — we will buy more for you when you are running low');
    }

    /**
     * Stop buying automatically.
     *
     * ⚠️ **NO SECOND CONFIRMATION.** CONFIRM is reserved for the three things
     * `CLAUDE.md` names and *"anything that spends money"* is the one this touches
     * — stopping a standing charge spends nothing, and a confirmation here would
     * be a support surface in front of the safe direction.
     */
    public function turnOffAutomatic(string $product, AutoTopUps $autoTopUps): void
    {
        Gate::authorize('purchaseCredit', Subscription::class);

        $resolved = CreditProduct::tryFrom($product);

        if (! $resolved instanceof CreditProduct) {
            return;
        }

        $arrangement = $this->arrangementFor($resolved);

        if (! $arrangement instanceof AutoTopUpArrangement) {
            return;
        }

        $autoTopUps->cancel($arrangement, $this->actor());

        $this->dismissArrangement();

        Toaster::success('Turned off — nothing will be bought for you automatically');
    }

    /**
     * The sentence the person is agreeing to, verbatim, and the one stored.
     *
     * ⛔ **ONE METHOD, READ BY THE PANEL AND WRITTEN TO THE RECORD** —
     * {@see self::confirmationWording()}'s rule, and it matters more here: this
     * agreement authorises charges made months later with nobody watching, and
     * `AutoTopUps::confirmationFor()` puts this exact string on every one of them.
     *
     * ⛔ **IT NAMES HOW TO STOP IT, AND THAT IS NOT DECORATION.** The page says so
     * (and said so before this did), but the page is not the record: 3489 and 2064
     * make the stored sentence the answer to *"what did you show them"*, asked
     * about a charge made months later with nobody watching, and for a standing
     * card authorisation the cancellation mechanism is part of what has to have
     * been disclosed. A record that states amount, trigger and ceiling but not
     * that it can be turned off is a weaker document than the screen it came from.
     *
     * ⚠️ **AND THE MONTHLY LIMIT IS NOW STATED FLAT.** It used to end *"unless you
     * change that here"*, because re-agreeing reset the ceiling's window — the
     * hedge was covering a defect, and `AutoTopUps` counting by account and
     * product is what removed it rather than a better sentence.
     *
     * ⚠️ **IT HAS TO FIT `agreement_wording`, WHICH IS 255 CHARACTERS.** Postgres
     * errors rather than truncating, and every figure in it is admin-editable
     * (3415), so a test drives all three products at the longest limit on offer.
     */
    public function arrangementWording(): ?string
    {
        $product = CreditProduct::tryFrom($this->arrangingProduct ?? '');
        $amount = $this->arrangementAmount();
        $ceiling = $this->arrangementCeiling();
        $seed = $this->arrangementGrantSeed;

        if (! $product instanceof CreditProduct || $amount === null || $ceiling === null || $seed === null) {
            return null;
        }

        $currency = $this->currency(app(DefaultsRegistry::class));

        return 'You are letting us charge you '.$amount
            .' for '.$this->grantLabel($product, $seed, $currency)
            .' whenever '.$this->runningLowLabel($product).' runs low, without asking again — '
            .'and never more than '.$ceiling.' a month. You can turn this off here at any time.';
    }

    /** What one automatic payment costs, formatted once, for the panel and the record. */
    public function arrangementAmount(): ?string
    {
        $cents = $this->arrangementPriceCents;

        if ($cents === null) {
            return null;
        }

        return PlanPricing::format(Money::of($cents, $this->currency(app(DefaultsRegistry::class))));
    }

    /** The chosen monthly limit, formatted once, for the panel and the record. */
    public function arrangementCeiling(): ?string
    {
        $cents = $this->ceilingCents;

        if ($cents === null || $cents <= 0) {
            return null;
        }

        return PlanPricing::format(Money::of($cents, $this->currency(app(DefaultsRegistry::class))));
    }

    /**
     * The monthly limits on offer — whole multiples of one payment, in cents.
     *
     * ⛔ **INTEGER CENTS BY CONSTRUCTION** (`18` §Money handling). Two integers
     * multiplied; nothing here parses a dollar string and nothing divides.
     *
     * ⚠️ **ONLY MULTIPLES ARE DISTINGUISHABLE LIMITS.** 3493 refuses the charge
     * that *would* cross the ceiling, so $75 against a $50 payment stops in
     * exactly the same place $50 does. A free-text box would let a tenant set a
     * figure that does something different from what it says.
     *
     * @return list<array{cents: int, label: string}>
     */
    public function ceilingChoices(): array
    {
        $price = $this->arrangementPriceCents;

        if ($price === null || $price <= 0) {
            return [];
        }

        $currency = $this->currency(app(DefaultsRegistry::class));
        $choices = [];

        for ($payments = 1; $payments <= self::MOST_PAYMENTS_A_MONTH; $payments++) {
            $cents = $price * $payments;

            $choices[] = [
                'cents' => $cents,
                'label' => PlanPricing::format(Money::of($cents, $currency)).' a month',
            ];
        }

        return $choices;
    }

    public function render(
        CreditLedger $ledger,
        TopUpCatalog $catalog,
        Subscriptions $subscriptions,
        DefaultsRegistry $registry,
        AutoTopUps $autoTopUps,
    ): View {
        // Refused rather than resolved when there is no tenant — `WidgetInstall`'s
        // reasoning: internal staff belong to no business by design, so a
        // signed-in support agent typing this URL is the ordinary way to arrive
        // with nothing resolved, and letting `Tenancy::idOrFail()` reach the
        // renderer is a 500 that reads as our page being broken.
        $business = $this->business();
        $currency = $this->currency($registry);
        $receipts = $this->receipts();
        $subscription = $subscriptions->for($business);
        $paysWithCardOnFile = $this->hasStoredCard($subscription);

        return view('livewire.account.credit', [
            'cards' => $this->cards($ledger, $currency),
            'packs' => $this->packs($catalog, $currency),
            'resetsOn' => Carbon::now()->addMonthNoOverflow()->startOfMonth()->translatedFormat('j F'),

            // ⛔ THE 3441 NOTE. `Subscriptions::isEntitled()` is the same answer
            // the gate inside `CreditLedger` uses (3469), so this screen cannot
            // promise a spend the ledger would refuse, or stay quiet about one it
            // would.
            'planIsRunning' => $subscriptions->isEntitled($business),

            // ⛔ WHICH OF THE TWO WAYS A PLAN STOPS RUNNING, BECAUSE THE PANEL
            // ABOVE WAS WRITTEN FOR ONLY ONE OF THEM (9332). Every sentence on
            // this screen that fires on `! $planIsRunning` said *"start your plan
            // again"* — wording for an account that lapsed — and from 2026-08-25
            // the commonest way to reach it is a free trial running out on
            // somebody who has never had a plan to restart. The two need
            // different words and one of them needs a date.
            //
            // ⚠️ ASKED WITH THE ROW ALREADY IN HAND, so the screen makes one
            // subscription read rather than three.
            'trialHasEnded' => $subscriptions->noCardTrialHasEnded($business, $subscription),
            'trialEndsOn' => $subscriptions
                ->noCardTrialEndsAt($business, $subscription)
                ?->translatedFormat('j F Y'),

            'boughtTextCredit' => $ledger->balance(CreditProduct::Sms, CreditPool::TopUp),

            // T176 P17's low-balance banner. See `runningLow()` for why the
            // threshold is the automatic top-up's own and not a second figure.
            'runningLow' => $this->runningLow($ledger, $registry),
            'paysWithCardOnFile' => $paysWithCardOnFile,
            'mayBuy' => Gate::allows('purchaseCredit', Subscription::class),

            // ⛔ **A CONTROL THAT CANNOT SUCCEED AND IS NEVER WITHDRAWN**
            // (9296). With no gateway credential set — the state every
            // deployment has been in — pressing a pack and ticking the box
            // answered a 500 in a full-page modal. `arrange()`'s own docblock
            // already argues this shape: *"offering to arrange something that
            // would suspend itself in three nights is worse than refusing
            // it"*.
            //
            // ⚠️ **THE GATEWAY ASKED IS THE ONE THIS PRESS WOULD USE**, which
            // is the same branch `buy()` takes, so the panel cannot withhold
            // for a gateway the press would not have touched.
            // ⛔ **THE MARKUP IS NOT THE GATE** (398): `buy()` refuses on its
            // own, `CreditTopUps` refuses under that, and both are driven by
            // their own tests.
            'paymentsAreAvailable' => $paysWithCardOnFile
                ? AuthorizeNetApi::isConfigured()
                : StripeApi::isLive(),

            // ⛔ READ ONLY. Rendering must never bring an arrangement into
            // existence (3488) — a row appears when somebody ticks a box and
            // never as a side effect of looking at this page.
            'automatic' => $this->automaticRows($catalog, $currency, $autoTopUps),

            // ⛔ THE RECEIPT 3536 NAMED AND 4665 RECORDED AS UNBUILT (4842). Read
            // only, and only the payments where money actually moved — see
            // `receipts()` for why a `pending` row is not a payment and a
            // `mismatched` one is.
            //
            // ⚠️ TWO KEYS RATHER THAN ONE NESTED ARRAY, AND THAT IS A LINT
            // FINDING RATHER THAN A STYLE ONE (4852). `ScreenStatesTest`'s
            // record-list lint classifies a loop by its **source expression**, so
            // `@foreach ($receipts['rows'] as …)` is invisible to it: the empty
            // branch could be deleted with the build green. A bare `$receipts` is
            // a source it reads, which puts this list under the same gate as
            // every other list on every other screen.
            'receipts' => $receipts['rows'],
            'hasOlderReceipts' => $receipts['hasOlder'],
        ]);
    }

    /**
     * What each product's automatic arrangement is doing, if anything.
     *
     * ⚠️ **THE OPERATOR'S `stopped_reason` IS NEVER RENDERED.** It is written for
     * whoever has to explain the account to somebody — *"the price of this top-up
     * rose above the amount that was agreed"* — and it carries our words for our
     * machinery onto a customer's screen. What crosses to the template is the
     * cause as a word, and the template owns the sentence.
     *
     * ⛔ **AND THE CAUSE COMES FROM {@see AutoTopUps::stoppedCause()}, NOT FROM
     * THE FAILURE COUNT.** It was inferred here — *three failures means the card,
     * anything else means the price* — which is true of exactly the two causes
     * that exist today and false of every one added later: a suspension for any
     * future reason told the tenant *"The price changed"* about their own money.
     * The service that writes the reason is the only thing that can say what it
     * means.
     *
     * @return list<array{
     *     product: string,
     *     heading: string,
     *     state: string,
     *     stopped: ?string,
     *     amount: ?string,
     *     ceiling: ?string,
     *     offer: ?string,
     * }>
     */
    private function automaticRows(TopUpCatalog $catalog, string $currency, AutoTopUps $autoTopUps): array
    {
        $rows = [];

        foreach (CreditProduct::cases() as $product) {
            $arrangement = $this->arrangementFor($product);
            $sku = $this->automaticSku($catalog, $product);

            $rows[] = [
                'product' => $product->value,
                'heading' => $this->heading($product),
                'state' => $this->automaticState($arrangement),
                'stopped' => $arrangement instanceof AutoTopUpArrangement
                    ? $autoTopUps->stoppedCause($arrangement)
                    : null,
                'amount' => $arrangement instanceof AutoTopUpArrangement
                    ? PlanPricing::format($arrangement->agreedAmount())
                    : null,
                'ceiling' => $arrangement instanceof AutoTopUpArrangement
                    ? PlanPricing::format($arrangement->ceiling())
                    : null,
                'offer' => $sku instanceof TopUpSku
                    ? PlanPricing::format($sku->price).' for '.$this->grantLabel($product, $sku->grantSeed, $currency)
                    : null,
            ];
        }

        return $rows;
    }

    /**
     * ⛔ **`off` IS THE ABSENCE OF A ROW, NOT A COLUMN** (3488). *Stopped* is the
     * card failing or the price moving — the agreement stands and the tenant has
     * to make it again; *off* is a tenant who has never chosen or has cancelled.
     */
    private function automaticState(?AutoTopUpArrangement $arrangement): string
    {
        if (! $arrangement instanceof AutoTopUpArrangement) {
            return 'off';
        }

        return $arrangement->isLive() ? 'on' : 'stopped';
    }

    /**
     * The arrangement this tenant has for a product, live or stopped.
     *
     * ⚠️ **`cancelled_at IS NULL` AND NOTHING ELSE, WHICH IS THE PREDICATE
     * `AutoTopUps::agree()` REFUSES ON.** A reader that skipped stopped rows would
     * draw an "off" state with a "Set it up" button that then refuses, because the
     * partial unique index still holds the slot.
     */
    private function arrangementFor(CreditProduct $product): ?AutoTopUpArrangement
    {
        return AutoTopUpArrangement::query()
            ->where('product', $product->value)
            ->whereNull('cancelled_at')
            ->first();
    }

    /**
     * ⚠️ **AN UNPRICEABLE PRODUCT IS A REAL STATE**, for `packs()`'s reason: every
     * figure behind it is admin-editable and `TopUpCatalog` refuses a bad one
     * rather than selling it.
     */
    private function automaticSku(TopUpCatalog $catalog, CreditProduct $product): ?TopUpSku
    {
        return $this->pricedSku($catalog, $product, CreditTopUpTier::Automatic);
    }

    /**
     * One line of the price list, or null where it cannot be priced at all.
     *
     * ⚠️ **SILENT, UNLIKE {@see self::skuFor()}.** That one is answering a press
     * and owes the person a sentence; this one is comparing what the panel showed
     * against what the catalogue now says, where an unpriceable product and a
     * changed one deserve the same answer from the caller.
     */
    private function pricedSku(TopUpCatalog $catalog, CreditProduct $product, CreditTopUpTier $tier): ?TopUpSku
    {
        try {
            return $catalog->sku($product, $tier);
        } catch (CreditPurchaseRefused) {
            return null;
        }
    }

    /**
     * The monthly limit the panel opens on.
     *
     * ⛔ **A TENANT WHO HAS CHOSEN KEEPS THEIR FIGURE** — `reviews.default_invite_threshold`'s
     * shape (1420), and the registry's own description of this key says it: the
     * seed is the platform default only, and Ops cannot move a limit somebody set.
     * So changing the limit opens on the one they are already living with.
     *
     * ⚠️ **THE SEEDED DEFAULT, ROUNDED UP TO A WHOLE PAYMENT, NEVER DOWN.**
     * `credits.auto_topup.default_ceiling_cents` is the platform default (3306)
     * and the price beside it is admin-editable, so the two can stop being
     * multiples of each other on one Ops edit. Rounding down could land below one
     * payment, which `agree()` refuses outright (3494) — the panel would open on a
     * figure that cannot be agreed.
     */
    private function startingCeilingCents(
        TopUpSku $sku,
        DefaultsRegistry $registry,
        ?AutoTopUpArrangement $existing,
    ): int {
        $price = $sku->price->minorUnits;

        if ($existing instanceof AutoTopUpArrangement
            && $existing->ceiling_cents % $price === 0
            && $existing->ceiling_cents >= $price
            && intdiv($existing->ceiling_cents, $price) <= self::MOST_PAYMENTS_A_MONTH
        ) {
            return $existing->ceiling_cents;
        }

        $preferred = $registry->int('credits.auto_topup.default_ceiling_cents');

        // Integer arithmetic throughout — `intdiv` and a remainder rather than a
        // division that would produce a float and a cent that does not exist.
        $payments = intdiv($preferred, $price) + ($preferred % $price === 0 ? 0 : 1);

        return $price * max(1, min(self::MOST_PAYMENTS_A_MONTH, $payments));
    }

    /** @return list<int> */
    private function ceilingChoiceCents(): array
    {
        return array_map(
            static fn (array $choice): int => $choice['cents'],
            $this->ceilingChoices(),
        );
    }

    /**
     * What runs low, in the tenant's words, for the middle of a sentence.
     *
     * ⚠️ **NOT {@see self::heading()}** — that one titles a card and this one sits
     * inside *"whenever … runs low"*, where "Text messages" would not read as
     * English.
     */
    private function runningLowLabel(CreditProduct $product): string
    {
        return match ($product) {
            CreditProduct::Sms => 'your text credit',
            CreditProduct::Email => 'your email credit',
            CreditProduct::Ai => 'what the assistant writes with',
        };
    }

    /**
     * Which kinds of credit are close to running out (T176 P17).
     *
     * ⛔ **THE THRESHOLD IS `credits.auto_topup.threshold.*` — THE AUTOMATIC
     * TOP-UP'S OWN KEY — AND A SECOND FIGURE HERE WOULD BE THE DEFECT.** This
     * screen offers to set automatic top-up up *"whenever your credit runs low"*,
     * a few panels down, and that offer means the moment
     * {@see AutoTopUps::balanceIsLow()} fires. A separate "warn me at" number
     * would let the banner say a tenant is fine on the morning their card is
     * charged, or cry low for a week while nothing happened — decision 505's
     * shape (two readers of one meaning that can disagree), on the sentence that
     * decides whether somebody buys.
     *
     * ⛔ **AGAINST THE TOTAL SPENDABLE BALANCE, NOT THE TOP-UP POOL ALONE**, for
     * `balanceIsLow()`'s stated reason: a spend draws the monthly grant first and
     * spills into top-up (3307), so an empty top-up pool is the ordinary state of
     * an account inside its allowance, and warning on it would put a red panel in
     * front of every tenant on the first of the month.
     *
     * ⚠️ **IT IS A WARNING AND NOT A REFUSAL.** Nothing here stops a send; 2904's
     * rule is that an exhausted balance degrades and never throws, and this panel
     * is the part of that rule the tenant can act on before it happens.
     *
     * ⛔ **AND IT IS GATED ON {@see CreditLedger::everFunded()}, WHICH IS THE
     * DIFFERENCE BETWEEN A WARNING AND WALLPAPER.** A first draft of this method
     * compared the balance against the threshold and nothing else, and it would
     * have shown *"you are running low"* to **every account that has never held
     * credit at all** — which is every tenant between registration and the first
     * monthly run, plus every trial account `TrialEligibility` refuses a grant to
     * for having no confirmed listing. That is a red panel permanently on, in
     * front of the population it is least true of: they are not running down, they
     * were never funded. A banner that is always on is one nobody reads, and this
     * one has to still be legible on the day it matters.
     *
     * ⚠️ **`everFunded()` IS MONOTONIC AND APPEND-ONLY**, so once an account has
     * been granted or has bought anything the warning arms itself for that product
     * and cannot silently disarm — which is the property this use needs and the
     * reason it is the right predicate rather than "was granted this month".
     *
     * ⚠️ **WHAT THIS DOES NOT COVER, SAID RATHER THAN IMPLIED.** A never-funded
     * account is left with no warning here at all. The panels that speak to them
     * are the plan-not-running card (3441) and the bought-text card (3309), and
     * *"you were never granted an allotment"* is a different sentence with a
     * different cause — it is a question about `ResetMonthlyCredits` and about
     * `TrialEligibility`, not about a balance. It is named here rather than
     * answered.
     *
     * @return list<string> The labels of the products running low, in the
     *                      product's own order. Empty is the ordinary case and
     *                      the template renders nothing at all.
     */
    private function runningLow(CreditLedger $ledger, DefaultsRegistry $registry): array
    {
        $low = [];

        foreach (CreditProduct::cases() as $product) {
            if (! $ledger->everFunded($product)) {
                continue;
            }

            if ($ledger->balance($product) <= $registry->int($product->autoTopUpThresholdKey())) {
                $low[] = $this->runningLowLabel($product);
            }
        }

        return $low;
    }

    /**
     * The three product cards, each holding its two balances apart.
     *
     * @return list<array{heading: string, monthly: string, bought: string}>
     */
    private function cards(CreditLedger $ledger, string $currency): array
    {
        $cards = [];

        foreach (CreditProduct::cases() as $product) {
            $cards[] = [
                'heading' => $this->heading($product),
                'monthly' => $this->amountLabel(
                    $product,
                    $ledger->balance($product, CreditPool::Monthly),
                    $currency,
                ),
                'bought' => $this->amountLabel(
                    $product,
                    $ledger->balance($product, CreditPool::TopUp),
                    $currency,
                ),
            ];
        }

        return $cards;
    }

    /**
     * Every pack on sale, priced and described.
     *
     * ⚠️ **AN EMPTY LIST IS A REAL STATE AND NOT A THEORETICAL ONE.** Every price
     * and quantity behind this is admin-editable (3415) and `TopUpCatalog` refuses
     * a zero or negative one rather than selling it — so one bad edit takes the
     * whole price list away, and the screen says so instead of drawing a row with
     * no price in it.
     *
     * @return list<array{product: string, tier: string, label: string, price: string}>
     */
    private function packs(TopUpCatalog $catalog, string $currency): array
    {
        try {
            $skus = $catalog->all();
        } catch (CreditPurchaseRefused) {
            return [];
        }

        return array_map(
            fn (TopUpSku $sku): array => [
                'product' => $sku->product->value,
                'tier' => $sku->tier->value,
                'label' => $this->grantLabel($sku->product, $sku->grantSeed, $currency),
                'price' => PlanPricing::format($sku->price),
            ],
            $skus,
        );
    }

    private function skuFor(TopUpCatalog $catalog, string $product, string $tier): ?TopUpSku
    {
        $resolved = CreditProduct::tryFrom($product);
        $resolvedTier = CreditTopUpTier::tryFrom($tier);

        if (! $resolved instanceof CreditProduct || ! $resolvedTier instanceof CreditTopUpTier) {
            return null;
        }

        try {
            return $catalog->sku($resolved, $resolvedTier);
        } catch (CreditPurchaseRefused) {
            Toaster::error('That is not on sale at the moment. Ask us and we will sort it out.');

            return null;
        }
    }

    /**
     * What a tenant calls this product.
     *
     * ⚠️ **NOT `$product->value`, AND NOT THE ENUM'S OWN LANGUAGE.** "Credit
     * product", "pool" and "ledger" are our words for our machinery (`22`); these
     * are the three things the person actually buys.
     */
    private function heading(CreditProduct $product): string
    {
        return match ($product) {
            CreditProduct::Sms => 'Text messages',
            CreditProduct::Email => 'Emails',
            CreditProduct::Ai => 'What the assistant writes',
        };
    }

    /**
     * A balance, in the unit the person understands it in.
     *
     * ⛔ **AI IS MONEY AND THE OTHER TWO ARE NOT** ({@see CreditProduct::unit()}),
     * so there is no shared spelling and no shared number. Printing an AI balance
     * as "2,840" would be hundredths of a cent on a screen.
     */
    private function amountLabel(CreditProduct $product, int $units, string $currency): string
    {
        return match ($product) {
            CreditProduct::Sms => number_format($units).' '.($units === 1 ? 'text message' : 'text messages'),
            CreditProduct::Email => number_format($units).' '.($units === 1 ? 'email' : 'emails'),
            CreditProduct::Ai => PlanPricing::format(Money::of($this->asCents($units), $currency)).' of writing',
        };
    }

    /**
     * What one pack grants, in the person's own units.
     *
     * @param  int  $seed  The registry's own figure: a count of sends for texts
     *                     and emails, integer **cents** for AI (3303).
     */
    private function grantLabel(CreditProduct $product, int $seed, string $currency): string
    {
        return match ($product) {
            CreditProduct::Sms => number_format($seed).' text messages',
            CreditProduct::Email => number_format($seed).' emails',
            CreditProduct::Ai => PlanPricing::format(Money::of($seed, $currency)).' of writing',
        };
    }

    /**
     * The payments this account has made for credit — the receipt half of 3536
     * (4842).
     *
     * ⛔ **`credit_purchases` CARRIES A FULL CONFIRMATION RECORD AND NOTHING HAS
     * EVER SHOWN IT BACK.** `confirmed_amount_cents`, `confirmation_wording` and
     * `confirmed_at` are NOT NULL because *"the question a chargeback asks is what
     * did you show them, and a boolean cannot answer it"* — and until this method
     * the answer existed only in the database. A customer who could not see a
     * payment they had made had to ask us, which is the support surface
     * `CLAUDE.md` says to spend a screen to avoid.
     *
     * ⛔ **IT IS A RECEIPT FOR CREDIT AND IS NEVER CALLED AN INVOICE OR A BILLING
     * HISTORY, BECAUSE NOTHING IN THIS SCHEMA RECORDS A PLAN CHARGE** (4843). The
     * subscription's own payments live at the gateway; there is no row here for
     * them. A list titled "your payments" that quietly omitted every plan charge
     * would be worse than no list — the customer would conclude we had never
     * billed them for the plan — so the heading names credit and only credit.
     *
     * ⚠️ **ONLY WHERE MONEY MOVED** ({@see CreditPurchaseStatus::moneyMoved()},
     * asked of the enum rather than spelled as a list here, so a sixth state
     * cannot inherit an answer nobody chose). A `pending` row is a panel somebody
     * opened and may never have paid, and a `failed` one took nothing — showing
     * either as a payment is telling a customer they were charged when they were
     * not. A `mismatched` row **is** shown, because the card was charged and a
     * charge with no line beside it is the "I do not recognise this" call this
     * screen exists to prevent.
     *
     * ⚠️ **NO REFERENCE AND NO TRANSACTION ID.** Our handle is `b{id}-{random}`
     * and embeds an internal identifier, and `22`'s rule is that a string names
     * what the person controls rather than how the system is built. A date and an
     * amount are what match a card statement.
     *
     * @return array{rows: list<array{
     *     paidOn: string,
     *     amount: string,
     *     bought: string,
     *     status: string,
     * }>, hasOlder: bool}
     */
    private function receipts(): array
    {
        $moved = array_values(array_filter(
            CreditPurchaseStatus::cases(),
            static fn (CreditPurchaseStatus $status): bool => $status->moneyMoved(),
        ));

        // One more than is shown, so "is there anything older" is answered by the
        // query rather than by a second COUNT over the same rows.
        $purchases = CreditPurchase::query()
            ->whereIn('status', array_map(
                static fn (CreditPurchaseStatus $status): string => $status->value,
                $moved,
            ))
            ->orderByDesc('id')
            ->limit(self::RECEIPTS_SHOWN + 1)
            ->get();

        $hasOlder = $purchases->count() > self::RECEIPTS_SHOWN;

        return [
            // `array_values()` rather than the collection's own `values()`: this
            // is a list to whoever reads the shape above, and a collection cannot
            // promise that.
            'rows' => array_values($purchases
                ->take(self::RECEIPTS_SHOWN)
                ->map(fn (CreditPurchase $purchase): array => [
                    // ⚠️ WHEN THE MONEY MOVED, FALLING BACK TO WHEN THEY AGREED TO
                    // IT. `authorized_at` is null on nothing in this list today —
                    // the CHECK requires a transaction id on every charged row —
                    // but a null date rendered as an empty cell on a receipt is
                    // the plausible blank this project keeps meeting.
                    'paidOn' => ($purchase->authorized_at ?? $purchase->confirmed_at)
                        ->toFormattedDayDateString(),

                    // ⛔ THE PURCHASE'S OWN CURRENCY AND NOT THIS SCREEN'S. The two
                    // are the same today and multi-currency is in scope (2058); a
                    // receipt relabelled into the platform's currency would be a
                    // number the customer's bank never saw.
                    'amount' => PlanPricing::format($purchase->price()),

                    'bought' => $this->grantLabel(
                        $purchase->product,
                        $purchase->grant_seed,
                        $purchase->price()->currency,
                    ),

                    'status' => $purchase->status->value,
                ])
                ->all()),
            'hasOlder' => $hasOlder,
        ];
    }

    /**
     * An AI balance, in integer cents, for display and for nothing else.
     *
     * ⛔ **THIS IS A SECOND PLACE THAT DIVIDES BY A HUNDRED AND 3419 ASKS FOR
     * THERE TO BE ONE.** {@see CreditUnit::fromCents()} converts the
     * other way and has no inverse, so a screen that must print a hundredths
     * balance as money has nowhere to send the question. **The missing piece is
     * `CreditUnit::toCents()`**, and it belongs on that enum beside its opposite;
     * it is named in this slice's decision block rather than added here, because
     * this lane does not own `app/Enums`.
     *
     * ⚠️ **IT TRUNCATES, WHICH IS THE SAFE DIRECTION FOR A BALANCE.** Rounding up
     * would show a tenant a cent they cannot spend, and nothing on this screen is
     * arithmetic anything else reads.
     */
    private function asCents(int $hundredths): int
    {
        return intdiv($hundredths, 100);
    }

    /**
     * Whether a top-up can be charged to a card we already hold.
     *
     * ⚠️ **BOTH IDS OR NEITHER** — `CreditTopUps::chargeStoredCard()` refuses on
     * either being absent (3487), so a screen that promised the card on file
     * because one of them existed would offer a button that always fails.
     */
    private function hasStoredCard(?Subscription $subscription): bool
    {
        return $subscription instanceof Subscription
            && $subscription->authorize_net_customer_profile_id !== null
            && $subscription->authorize_net_payment_profile_id !== null;
    }

    /**
     * ⚠️ **`PlanPricing`'s FALLBACK, DELIBERATELY, AND NOT `TopUpCatalog`'s.**
     * That class refuses without a currency because it is about to charge a card;
     * this one is printing a balance, and a screen that 500s because a platform
     * setting is unset tells a tenant nothing about their own money. The charge
     * still refuses, one layer down, where it matters.
     */
    private function currency(DefaultsRegistry $registry): string
    {
        $stored = $registry->value('billing.currency');

        return is_string($stored) && $stored !== '' ? $stored : 'USD';
    }

    /**
     * Who did this, in `AuditService`'s vocabulary.
     *
     * ⚠️ **ONE SPELLING, BECAUSE IT REACHES THREE RECORDS THAT ARE READ TOGETHER**
     * — the purchase's `confirmed_actor`, the arrangement's `agreed_actor`, and
     * the audit entry for turning one off. `user:14` and `14` in two of them is a
     * join nobody can make later.
     */
    private function actor(): string
    {
        return 'user:'.(auth()->id() ?? 'unknown');
    }

    /**
     * ⚠️ 403 RATHER THAN LETTING `Tenancy::idOrFail()` THROW — `Account\Plan`'s
     * reasoning. Internal staff belong to no business, so a signed-in support
     * agent typing this URL is the ordinary way to arrive with nothing resolved,
     * and a 500 reads as our page being broken.
     */
    private function business(): Business
    {
        $id = Tenancy::id();

        abort_if($id === null, 403);

        $business = Business::query()->find($id);

        abort_if(! $business instanceof Business, 404);

        return $business;
    }
}
