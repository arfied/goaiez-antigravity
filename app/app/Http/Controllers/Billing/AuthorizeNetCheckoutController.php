<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Exceptions\AuthorizeNetRequestFailed;
use App\Exceptions\ImpersonationRefused;
use App\Exceptions\QuotedPriceChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\AuthorizeNetCheckoutRequest;
use App\Http\Requests\Billing\BillingTermRequest;
use App\Models\Business;
use App\Services\Billing\AuthorizeNetApi;
use App\Services\Billing\AuthorizeNetGateway;
use App\Services\Billing\AutoRenewalAcknowledgements;
use App\Services\Billing\AutoRenewalDisclosure;
use App\Services\Billing\GatewayRefusals;
use App\Services\Billing\PlanCharges;
use App\Services\Config\DefaultsRegistry;
use App\Support\HashedIp;
use App\Support\Instalments;
use App\Support\Money;
use App\Support\PlanPricing;
use App\Support\PlanSelection;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Accept.js card form, and the exchange behind it (T137 SL-11).
 *
 * ⚠️ **THE POST RUNS SYNCHRONOUSLY AND MUST, WHICH IS THE OPPOSITE OF THIS
 * CODEBASE'S DEFAULT FOR ANYTHING TOUCHING A VENDOR.** An Accept.js nonce is
 * valid for **15 minutes** (verified against the vendor's live documentation,
 * 2026-08-11). Queuing the exchange would turn a working integration into one
 * that fails whenever the queue is busy — intermittently, at signup, with no
 * pattern anybody can see. The trade is that the gateway's availability is on
 * the request path, and the refusals below are what make that survivable.
 *
 * ⛔ **NOTHING HERE READS A CARD FIELD, AND NOTHING CAN.** The request object
 * refuses any field outside its allowlist, so a form that starts posting a PAN
 * fails validation rather than reaching a controller that would have ignored it.
 */
final class AuthorizeNetCheckoutController extends Controller
{
    /**
     * The card form.
     *
     * ⚠️ **TWO CREDENTIALS ARE RENDERED INTO THIS PAGE AND THAT IS CORRECT.**
     * Accept.js needs the API login id and the **public** client key in the
     * browser; the vendor's own documentation says the public client key may
     * safely live in a website. ⛔ The **transaction key** must never appear
     * here, and there is no code path that would put it there — this method
     * names the two keys it wants rather than passing a credential bag.
     */
    public function create(
        BillingTermRequest $request,
        DefaultsRegistry $registry,
        PlanCharges $charges,
    ): View|RedirectResponse {
        $this->business();

        // ⛔ **THE GUARD READ TWO OF THE FOUR KEYS THIS PATH NEEDS, AND THE
        // ONE IT MISSED IS THE ONE THAT MOVES MONEY** (9295). 9144 replaced a
        // `get()` here with two `has()` calls — the API login id and the public
        // client key, which is everything Accept.js needs *in the browser* and
        // not what {@see AuthorizeNetApi::send()} signs with. With those two
        // pasted and the transaction key missed — one paste apart in Ops — this
        // page rendered, Accept.js tokenised a **real card**, `store()` wrote
        // the Automatic Renewal Law acknowledgement, and the person was bounced
        // back to `/billing` **with no message at all**.
        //
        // ⚠️ **THE FIX IS A DERIVED QUESTION AND NOT A THIRD LITERAL.**
        // {@see AuthorizeNetApi::checkoutIsConfigured()} calls
        // `isConfigured()`, so a door's key set can no longer be a subset of the
        // call's — `CLAUDE.md`'s *door guard reading a proxy for the column the
        // constraint reads*, closed at the derivation rather than at the site.
        //
        // ⛔ **AND THE SENTENCE NO LONGER CLAIMS A NOTIFICATION** (9297).
        // Nothing on either gateway writes `PlatformHealthSignal::VendorCall`,
        // which is what `OperatorAlertKind::VendorErrorRate` reads, so *"our
        // team has been notified"* was false for every billing failure and was
        // said to a customer who could not pay us.
        if (! AuthorizeNetApi::checkoutIsConfigured()) {
            Log::warning('authorize.net card form withheld: gateway credentials are not configured');

            return redirect()
                ->route('billing.index')
                ->with('billing.error', GatewayRefusals::cannotTakePayment());
        }

        $selection = $request->selection();

        // ⚠️ **THE SCHEDULE IS BUILT ONCE AND READ TWICE**, because the sentence
        // under it is a claim *about* it (4643). Deriving the payments here and
        // the remainder sentence from a second call would be two derivations of
        // one fact, and the day they disagree the page states something about
        // amounts it is not showing.
        $schedule = $selection->inInstalments
            ? $charges->scheduleFor($selection, $charges->firstChargeOn())
            : [];

        // ⛔ **THE TOTAL IS RESOLVED ONCE AND BOTH THE SENTENCE AND THE HIDDEN
        // FIELD COME OFF IT** (4640). The field is what the submit is checked
        // against, so it has to be the *same* figure the person read — deriving
        // it from a second `priceFor()` call would let the page display one
        // number and vouch for another, which is the defect this guard exists
        // for reproduced inside the guard.
        $total = $charges->priceFor($selection);

        return view('billing.authorize-net', [
            'apiLoginId' => PlatformCredentials::get(AuthorizeNetApi::API_LOGIN_ID),
            'clientKey' => PlatformCredentials::get(AuthorizeNetApi::PUBLIC_CLIENT_KEY),

            // ⚠️ SANDBOX AND PRODUCTION ARE DIFFERENT SCRIPT HOSTS ON THIS
            // VENDOR — `jstest.authorize.net` against `js.authorize.net` — where
            // Stripe distinguishes modes by the key alone. Loading the
            // production script with sandbox credentials fails in the browser
            // with a message about authentication, which reads as a bad key.
            'acceptJsUrl' => config('services.authorizenet.environment') === 'production'
                ? 'https://js.authorize.net/v1/Accept.js'
                : 'https://jstest.authorize.net/v1/Accept.js',

            // The price, formatted by the one formatter (512). ⚠️ Read rather
            // than written here, and **read through `PlanCharges`, which consults
            // the live offer first** — so this page quotes the founder rate while
            // the founder window is open and the retail schedule after the window closes it.
            //
            // ⛔ THIS COMMENT SAID THE OPPOSITE UNTIL 4522, AND IT SAID IT ON THE
            // PAGE CARRYING CALIFORNIA'S ARL DISCLOSURE. It read "decision 2090
            // keeps them out of the manifest until `plan_offers` exists. This
            // page therefore quotes the retail schedule, which is what is
            // actually charged" — both clauses true when written and both false
            // since P1 landed `plan_offers`. A stale comment on a statutory
            // disclosure is the one that tells the next reader not to check
            // whether the number on the page is the number on the card.
            'price' => PlanPricing::format($total),
            'trialDays' => $registry->value('billing.trial_days'),

            // ⛔ **WHAT THIS PAGE QUOTED, POSTED BACK SO THE SUBMIT CAN BE
            // REFUSED IF IT NO LONGER HOLDS** (4640). A founder window closing
            // between this render and the button charged the retail price
            // against a screen — and an Automatic Renewal Law acknowledgment —
            // that said founder. ⚠️ **It is a check and never a price**: nothing
            // spends it, and see `QuotedPriceChanged` for why that makes a
            // signature unnecessary.
            'quotedTotalCents' => $total->minorUnits,

            // ⚠️ **THE SCHEDULE IS ON THE PAGE BECAUSE THIS BUTTON IS WHERE THE
            // CONFIRMATION HAPPENS** (decision 2680). `CLAUDE.md` reserves
            // CONFIRM for "anything that spends money", and on an instalment plan
            // money moves three times from one press — so the press only counts
            // as a confirmation if the page names every amount and every date
            // before it. Empty for anything bought in one payment, which is what
            // `$price` already says.
            'instalments' => array_map(
                static fn (array $payment): array => [
                    'on' => $payment['dueOn']->toFormattedDateString(),
                    'amount' => PlanPricing::format($payment['amount']),
                ],
                $schedule,
            ),

            // ⛔ **WHETHER THE FINAL PAYMENT CARRIES THE ODD CENTS, DERIVED
            // RATHER THAN ASSERTED IN THE COPY** (4643). The page said *"the last
            // payment is a cent larger so the **three** add up to the yearly price
            // exactly"* above a loop over however many payments there are — and
            // both halves of that sentence were wrong on a purchase this
            // application can sell. **The founder annual is TWO payments and the
            // retail annual is THREE, and neither is a bug** (2092, 2754, 4315);
            // the founder add-on halves evenly, so *no* payment is larger there.
            // And on a multi-location instalment plan the split is per SKU
            // (`PlanCharges`' own docblock), so the remainder is one cent per SKU
            // and "a cent" understates it.
            'finalInstalmentCarriesRemainder' => self::finalPaymentCarriesRemainder($schedule),

            'term' => $selection->term,
            'buyingInstalments' => $selection->inInstalments,

            // ⚠️ **THE TWO RATES SEPARATELY, BECAUSE `$price` ABOVE IS THE
            // TOTAL** (2753's SKU, now buyable). A page quoting only the total
            // cannot be a confirmation of a multi-location order: the buyer would
            // agree to a number they have never seen decomposed, and a wrong
            // count would look exactly like a correct one. Both come from the
            // same `PlanCharges` call the charge is built from, formatted by the
            // one formatter (512).
            'additionalLocations' => $selection->additionalLocations,
            'basePrice' => PlanPricing::format($charges->unitPriceFor($selection)),
            'addOnPrice' => PlanPricing::format($charges->additionalLocationPriceFor($selection)),

            // ⚠️ **CALIFORNIA'S AUTOMATIC RENEWAL LAW WANTS THE RENEWAL TERMS
            // ACKNOWLEDGED SEPARATELY FROM THE PURCHASE** (2980–2999), so the
            // words and the box are rendered rather than folded into the "you
            // can cancel any time" line above — which, until this slice, was a
            // promise this application could not perform at all.
            //
            // ⚠️ **AN INSTALMENT PLAN DOES NOT RENEW (2749), SO IT GETS
            // DIFFERENT WORDS AND A DIFFERENT VERSION.** Telling that buyer
            // "this renews automatically until you cancel" would be the exact
            // misrepresentation the statute exists to prevent, made inside the
            // disclosure written to comply with it.
            'renewalDisclosure' => AutoRenewalDisclosure::textFor(
                ! $selection->inInstalments,
                PlanPricing::format($total),
                $selection->term,
            ),
            'renewalLabel' => AutoRenewalDisclosure::labelFor(! $selection->inInstalments),
        ]);
    }

    /**
     * Exchange the nonce for a subscription.
     *
     * ⚠️ **THE ACKNOWLEDGMENT IS RECORDED BEFORE THE GATEWAY IS CALLED, AND THE
     * ORDER IS THE REQUIREMENT** (2980–2999). California's Automatic Renewal Law
     * wants the renewal terms acknowledged *before* the consumer is charged, so
     * recording it after a successful subscribe would produce evidence of the
     * wrong thing — a note that somebody agreed after their card was taken. The
     * cost is an acknowledgment row behind a card that was then declined, which
     * is harmless: a row here says what was on the screen, not that a purchase
     * happened.
     *
     * ⛔ **AND THE FIRST THING IT DOES IS CHECK THAT THE PRICE HAS NOT MOVED
     * SINCE THE SCREEN WAS DRAWN** (4640). The `GET` renders the total, the
     * schedule and the disclosure from the offer live at that moment; this
     * request resolves the offer again, minutes later. A founder window closing
     * in between charged the retail figure against a confirmation of the founder
     * one — **and recorded an ARL acknowledgment quoting a price that had never
     * been on any screen**, because it re-quotes here too. 4346 fixed this shape
     * one layer down, between the gateway and its writer, by carrying the numbers
     * on a `PlanQuote`; that cannot reach across two requests, and this is where
     * the two requests meet.
     *
     * ⚠️ **THE CHECK RUNS BEFORE THE ACKNOWLEDGMENT IS RECORDED**, so a refused
     * purchase leaves no evidence of somebody agreeing to terms they were not
     * shown — which is a different and worse artefact than the declined-card row
     * above.
     */
    public function store(
        AuthorizeNetCheckoutRequest $request,
        AuthorizeNetGateway $gateway,
        AutoRenewalAcknowledgements $acknowledgements,
        PlanCharges $charges,
    ): RedirectResponse {
        $business = $this->business();

        $user = $request->user();

        // The route sits behind `auth`, so this cannot happen — and it is
        // asserted rather than defaulted to an empty string, because the vendor
        // stores this on the customer profile and an empty email there is a
        // profile nobody can be reached about. `SUBPROCESSOR-INVENTORY` §1
        // records it as the one field this gateway receives that Stripe's
        // hosted Checkout did not.
        abort_if($user === null, Response::HTTP_FORBIDDEN);

        $email = (string) $user->email;

        $selection = $request->selection();

        $total = $charges->priceFor($selection);

        if ($total->minorUnits !== $request->quotedTotalMinorUnits()) {
            return $this->priceMoved($selection);
        }

        try {
            $acknowledgements->record(
                $selection->term,
                ! $selection->inInstalments,
                PlanPricing::format($total),
                $this->proof($request),
                'user:'.$user->getKey(),
            );
        } catch (ImpersonationRefused $refusal) {
            // `28` §9.4's blocklist as a sentence an agent can act on: an
            // acceptance recorded from a support session would not be one.
            abort(Response::HTTP_FORBIDDEN, $refusal->getMessage());
        }

        try {
            $gateway->subscribe(
                $business,
                $email,
                (string) $request->validated('data_value'),
                // ⛔ **THE NAME ON THE CARD, WITHOUT WHICH THIS CALL HAS NEVER
                // ONCE SUCCEEDED** — every `ARBCreateSubscriptionRequest`
                // against a `billTo`-less payment profile answers `E00014`.
                // Built from `validated()`, so it cannot carry a field the
                // allowlist refused.
                $request->cardholderName(),
                $selection,
                // ⛔ CHECKED AGAIN AT THE LAST POINT BEFORE THE VENDOR, AND NOT
                // BECAUSE THE CHECK ABOVE IS DOUBTED (4640). The gateway prices
                // this purchase from **its own** `PlanCharges`, resolved after
                // the one this controller used — 4346's two-instances shape,
                // here inside a single request rather than across a network round
                // trip. The window is microseconds and the consequence is a card
                // charged at a figure nobody displayed, so the comparand is
                // carried to where the amount is actually built.
                $request->quotedTotalMinorUnits(),
            );
        } catch (QuotedPriceChanged $e) {
            // Unreachable through the check above except in that microsecond, so
            // it is logged at the same level as the vendor refusals rather than
            // shown differently: the person gets the same sentence and the same
            // page, and the log line is what says which of the two layers caught
            // it.
            Log::warning('a card post was refused because the quoted price had moved', [
                'business_id' => $business->id,
                'reason' => $e->getMessage(),
            ]);

            return $this->priceMoved($selection);
        } catch (AuthorizeNetRequestFailed $e) {
            // The classified reason, never the vendor's message — see
            // AuthorizeNetRequestFailed. `configuration` separates "an operator
            // must paste a key" from "the gateway is having a bad afternoon",
            // and only the second is worth telling somebody to try again about.
            Log::warning('authorize.net subscription could not be created', [
                'business_id' => $business->id,
                'reason' => $e->reason,
                'configuration' => $e->configuration,
                // ⚠️ **THE FLAG AN OPERATOR ACTS ON DIFFERENTLY.**
                // `configuration` says paste a key; this says the request we
                // sent is wrong, which no key fixes and no retry survives.
                'malformed_request' => $e->malformedRequest,
            ]);

            return redirect()
                ->route('billing.index')
                ->with('billing.error', self::refusal($e));
        } catch (RuntimeException $e) {
            // Already subscribed, or a shape the vendor does not document.
            // Neither is something the person can act on, and both are already
            // true — so this lands them on the page that tells them what we hold.
            //
            // ⛔ **THIS ARM ANSWERED A MESSAGELESS REDIRECT AND IT WAS THE ARM
            // AN UNSET CREDENTIAL LANDED ON** (9294). `PlatformCredentials::get()`
            // raises a bare `RuntimeException` and `AuthorizeNetApi` read it
            // above its own `try`, so the fault fell past the typed arm to here
            // — after Accept.js had tokenised a card and after the renewal
            // acknowledgement was written. It is classified now and lands
            // above; what is fixed *here* is the silence, because **a bounce
            // with no sentence is indistinguishable from a page that did
            // nothing**, and this arm's other occupants ("already subscribed")
            // are owed a sentence too.
            Log::warning('authorize.net subscription refused', [
                'business_id' => $business->id,
                'reason' => $e->getMessage(),
            ]);

            return redirect()
                ->route('billing.index')
                ->with('billing.error', 'We could not finish that. Nothing has been charged — the page below shows what we hold for you.');
        }

        return redirect()->route('billing.index')->with(
            'billing.notice',
            'Your card is on file. Your plan is active.',
        );
    }

    /**
     * The three answers a refused subscribe is owed, and why there are three.
     *
     * ⛔ **`configuration` FIRST, BECAUSE THE OTHER TWO ARE BOTH WRONG FOR IT.**
     * An unset or rejected merchant credential is not a card problem and is not
     * a bad afternoon at the vendor: *"check the details or try another card"*
     * tells somebody their card is at fault when it is ours, and *"try again in
     * a moment"* sends them round a loop nothing they do can break.
     * `Account\Plan::vendorRefusal()` has drawn this distinction for months and
     * this path did not.
     */
    private static function refusal(AuthorizeNetRequestFailed $failure): string
    {
        if ($failure->configuration) {
            return GatewayRefusals::cannotTakePayment();
        }

        // ⛔ **THE THIRD CATEGORY, AND THIS METHOD IS WHERE ITS ABSENCE COST
        // THE MOST.** `E00014` — *"A required field is not present"* — is what
        // this gateway answered every subscription create with while both
        // profile creators sent no `billTo`. It is not a credential fault, so
        // it fell to the last line and told every buyer to *"check the details
        // or try another card"* — **the exact sentence this docblock says must
        // never be shown for a fault of ours**, on the only reachable way to
        // pay for this product, for as long as it has existed.
        //
        // ⚠️ **THE SENTENCE IS THE SAME AS THE CREDENTIAL ONE AND THE
        // CATEGORIES ARE STILL TWO.** From where the buyer stands both are
        // *"nothing you can do, nothing was charged, talk to us"*; what differs
        // is the operator's next move, and that lives in the log line above
        // rather than on the page.
        if ($failure->malformedRequest) {
            return GatewayRefusals::cannotTakePayment();
        }

        return $failure->retryable
            ? GatewayRefusals::cannotReachGateway()
            : 'That card could not be accepted. Please check the details or try another card.';
    }

    /**
     * Back to the card page with today's figures, having charged nothing (4640).
     *
     * ⚠️ **ONE SENTENCE IN ONE PLACE, BECAUSE BOTH LAYERS SAY IT.** The check in
     * this controller and the one at the vendor boundary catch the same event a
     * few milliseconds apart, and two spellings of the same message would be two
     * things to keep in step for no gain.
     *
     * ⚠️ **THE SELECTION IS CARRIED BACK**, so the page redraws the term, the
     * instalment choice and the location count that person had chosen — landing
     * them on the monthly plan because a price moved would be a second surprise
     * on top of the first. `22`'s outcome language: it says what happened and
     * what to do, and never that anything failed. Nothing did.
     */
    private function priceMoved(PlanSelection $selection): RedirectResponse
    {
        return redirect()
            ->route('billing.card', [
                'term' => $selection->term->value,
                'instalments' => $selection->inInstalments ? '1' : '0',
                'locations' => $selection->additionalLocations,
            ])
            ->with(
                'billing.error',
                'The price changed while this page was open, so nothing has been charged. '
                .'Please check the amount below and press Save card again.',
            );
    }

    /**
     * Whether the last payment in a schedule is larger than the ones before it.
     *
     * ⚠️ **A QUESTION ABOUT THE FIGURES ON THE PAGE, ASKED OF THE FIGURES ON THE
     * PAGE.** {@see Instalments::split()} puts the whole remainder on the final
     * payment and `AuthorizeNetGateway::paymentSchedule()` refuses any other
     * shape, so comparing the last against the first answers it — and it keeps
     * answering it if that ordering ever moves (2135 is open and this does not
     * vote on it), where a `% $count` re-derivation here would silently start
     * describing a schedule this page is not rendering.
     *
     * A one-payment schedule has no "payments before it" and reads false, which
     * is what the copy needs: there is nothing to explain.
     *
     * @param  list<array{dueOn: Carbon, amount: Money}>  $schedule
     */
    private static function finalPaymentCarriesRemainder(array $schedule): bool
    {
        if (count($schedule) < 2) {
            return false;
        }

        return end($schedule)['amount']->minorUnits !== $schedule[0]['amount']->minorUnits;
    }

    /**
     * What makes the acknowledgment provable rather than merely recorded.
     *
     * ⚠️ **NEVER `$request->ip()`.** `29` §2 rule 21 forbids storing a raw
     * address, and `ConsentProof` refuses one at any depth — the guard rather
     * than the habit. `ImportCustomers::proof()` builds the same three keys for
     * the other non-consent record, and the shape is deliberately identical.
     *
     * @return array<string, mixed>
     */
    private function proof(AuthorizeNetCheckoutRequest $request): array
    {
        return [
            'url' => $request->url(),
            'ip_hash' => HashedIp::of($request),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
        ];
    }

    /**
     * The tenant the form is drawn for and the charge is made against.
     *
     * ⛔ **`idOrFail()` DID NOT REFUSE THIS REQUEST, IT CRASHED IT — 9148.** The
     * comment below used to read *"idOrFail() has already refused a request with
     * no tenant"*; `TenantNotResolved` is a plain `RuntimeException` with no
     * renderer in `bootstrap/app.php`'s `withExceptions` block, so
     * `GET /billing/card` answered a signed-in reader with no tenant with a 500.
     * `Billing\BillingController::business()` carries the argument for the 403
     * and for why *"a person who has just registered"* is not a population here.
     *
     * ⚠️ **BOTH VERBS ARE COVERED BY THIS ONE LINE AND ONLY ONE OF THEM CAN BE
     * PROVEN AT THE ROUTE** (398). `create()` and `store()` each open with this
     * call, but `AuthorizeNetCheckoutRequest` runs first on the POST, so an
     * empty request is refused by validation before the controller is entered —
     * a route-level test of the POST would pass whether or not this line
     * existed. `TenantlessOwnerRoutesTest` therefore posts a **valid** body.
     */
    private function business(): Business
    {
        $id = Tenancy::id();

        abort_if($id === null, Response::HTTP_FORBIDDEN);

        $business = Business::find($id);

        // A missing row once a tenant is resolved is a business deleted
        // mid-request, which nothing does.
        abort_if(! $business instanceof Business, Response::HTTP_NOT_FOUND);

        return $business;
    }
}
