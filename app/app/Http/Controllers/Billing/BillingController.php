<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Exceptions\StripeRequestFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\BillingTermRequest;
use App\Models\Business;
use App\Services\Billing\AuthorizeNetApi;
use App\Services\Billing\BillingCheckout;
use App\Services\Billing\GatewayRefusals;
use App\Services\Billing\Subscriptions;
use App\Support\Tenancy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two authenticated billing screens: start Checkout, and say where we are.
 *
 * `GET /billing/checkout` is a redirect and holds no state; `GET /billing` is
 * what a person is returned to, and what they land on if anything goes wrong.
 */
final class BillingController extends Controller
{
    /**
     * Send this business to Stripe's hosted Checkout.
     *
     * ⚠️ **A GET THAT MAKES AN OUTBOUND CALL, AND THE ALTERNATIVE IS WORSE.**
     * Registration redirects here, and a redirect cannot POST — so making this a
     * POST would mean an interstitial page whose only content is a button, on
     * the step where drop-off is most expensive. The call is not a mutation of
     * ours: it opens a Checkout Session, which expires unused in 24 hours, and
     * {@see BillingCheckout} refuses outright once a subscription exists, so a
     * refresh or a prefetch cannot produce a second subscription. Decision 387
     * settled the same trade for `/f/{slug}/to/{destination}`.
     */
    public function checkout(BillingTermRequest $request, BillingCheckout $checkout): RedirectResponse
    {
        $business = $this->business();

        try {
            $url = $checkout->sessionUrlFor(
                $business,
                route('billing.index', ['checkout' => 'complete']),
                route('billing.index', ['checkout' => 'cancelled']),

                // ⚠️ `?term=annual` — AND THE INSTALMENT PLAN IS REFUSED BEHIND
                // THIS, BY STRIPE'S SHAPE RATHER THAN BY OURS. See
                // {@see BillingCheckout}: a Checkout Session cannot stop after a
                // fixed number of payments and cannot vary the amount between
                // them. The refusal lands in the `RuntimeException` arm below,
                // which is the same place "already subscribed" lands.
                $request->selection(),
            );
        } catch (StripeRequestFailed $e) {
            // The classified reason, never the vendor's message — see
            // StripeRequestFailed. `configuration` separates "an operator must
            // paste a key" from "Stripe is having a bad afternoon", and only the
            // second is worth telling somebody to try again about.
            Log::warning('checkout session could not be opened', [
                'business_id' => $business->id,
                'reason' => $e->reason,
                'configuration' => $e->configuration,
            ]);

            return redirect()
                ->route('billing.index')
                ->with('billing.error', $e->retryable
                    ? GatewayRefusals::cannotReachGateway()
                    // ⛔ **THIS SAID *"Our team has been notified"* AND NOBODY
                    // WAS — 9297.** Nothing on either gateway writes
                    // `PlatformHealthSignal::VendorCall`, which is what
                    // `OperatorAlertKind::VendorErrorRate` reads (9235), so no
                    // billing failure of any kind rings a bell. It was a false
                    // statement made directly to a customer, in the state in
                    // which it was false.
                    : GatewayRefusals::cannotTakePayment());
        } catch (RuntimeException $e) {
            // Already subscribed, or a shape Stripe does not document. Neither
            // is something the person can act on, and both are already true —
            // so this lands them on the page that tells them what we hold.
            //
            // ⛔ **THIS ARM REDIRECTED WITH NO MESSAGE AT ALL, AND IT WAS THE
            // ARM AN UNSET `stripe_secret` LANDED ON** (9294). `StripeApi` read
            // the key inside the closure `VendorLog::timed()` invokes, under a
            // `try` that catches `ConnectionException` alone, so the fault fell
            // past the typed arm above to here and the person met a silent
            // bounce. It is classified now and lands above; the silence is
            // fixed here because a bounce with no sentence is indistinguishable
            // from a button that did nothing.
            Log::warning('checkout session refused', [
                'business_id' => $business->id,
                'reason' => $e->getMessage(),
            ]);

            return redirect()
                ->route('billing.index')
                ->with('billing.error', 'We could not start that. Nothing has been charged — the page below shows what we hold for you.');
        }

        return redirect()->away($url);
    }

    /**
     * What this application actually knows about the subscription.
     *
     * ⚠️ **IT READS OUR OWN ROW AND NEVER THE `?checkout=` PARAMETER.** The
     * query string is attacker-controlled and, more to the point, *wrong* even
     * when it is honest: a customer reaching the success URL may still have a
     * failed payment, and one who closed the tab may be fully subscribed
     * (`cashier-billing`: "webhooks are the source of truth, never the
     * post-Checkout redirect"). The parameter chooses a sentence about what just
     * happened; the state on the page comes from the row a verified webhook
     * wrote.
     */
    public function index(Subscriptions $subscriptions): View
    {
        $business = $this->business();

        return view('billing.index', [
            'subscription' => $subscriptions->for($business),

            // ⛔ **A CONTROL THAT CANNOT SUCCEED AND IS NEVER WITHDRAWN** —
            // 9296. `Add a card` is the only affordance this page has, and with
            // no gateway credential set every press bounced back here and
            // redrew the same button beside the same failure: press, bounce,
            // press, bounce, from the one control on the screen. **Every
            // deployment of this application has been in that state.**
            //
            // ⚠️ **ABSENT RATHER THAN DISABLED** (1220), and
            // `Account\Credit`'s own panel takes the same shape for the same
            // reason. ⛔ **AND THE MARKUP IS NOT THE GATE** (398):
            // `AuthorizeNetCheckoutController::create()` refuses the route on
            // its own and is what the test drives.
            'cardMayBeAdded' => AuthorizeNetApi::checkoutIsConfigured(),

            // Deliberately *not* passed to the view as a state. It selects a
            // one-line note and nothing else — the view decides nothing from it.
            'returned' => request()->query('checkout'),
        ]);
    }

    /**
     * The tenant both screens act on.
     *
     * ⛔ **`idOrFail()` DID NOT REFUSE THIS REQUEST, IT CRASHED IT — 9148.** The
     * comment below used to read *"idOrFail() has already refused a request with
     * no tenant"*. `TenantNotResolved` is a plain `RuntimeException` and
     * `bootstrap/app.php`'s `withExceptions` block registers no renderer for it,
     * so both billing screens answered a signed-in reader with no tenant with a
     * 500. **"Fails closed" and "is refused" are two different user experiences
     * and this codebase spells them the same way** — `ResolveTenant`'s docblock
     * is where that phrase comes from, and it means the first.
     *
     * ⚠️ **403 RATHER THAN A REDIRECT, AND `/billing` IS THE ROUTE WHERE THAT IS
     * WORTH ARGUING** (9149): a person who has just registered looks like the
     * obvious second population, and **there is no such person**. Both
     * registration doors — `Fortify\CreateNewUser::create()` and
     * `Auth\OauthLoginController` — call `TenantProvisioner::provision()` inside
     * the same `DB::transaction` that writes the `users` row, and
     * `CreateNewUser` says so in as many words: *"Either both rows exist or
     * neither does."* The two populations that do exist are platform staff and a
     * former customer whose login survived a statutory erasure
     * (`Tenant\TenantDeletion`'s section 3, decision 746's wall), and neither
     * has anywhere a redirect could send them that would not refuse them again.
     *
     * ⚠️ **The `abort_if` beneath answers a different question and is
     * unchanged**: a tenant that IS resolved whose row is gone is a 404.
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
