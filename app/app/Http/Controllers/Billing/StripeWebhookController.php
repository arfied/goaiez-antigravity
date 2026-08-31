<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\GatewayEventOutcome;
use App\Enums\PlatformHealthSignal;
use App\Http\Controllers\Controller;
use App\Services\Billing\StripeWebhooks;
use App\Services\Ops\PlatformHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * `POST /webhooks/stripe` — the only endpoint that may move a subscription.
 *
 * ⚠️ **THE PATH IS OURS AND CASHIER'S IS TURNED OFF** (decision 683).
 * `CashierServiceProvider` registers `POST stripe/webhook` unless
 * `Cashier::ignoreRoutes()` is called, and nothing had ever called it — so that
 * route has been live and unauthenticated on this application since Stage 0,
 * running a handler nobody in this repository wrote. `AppServiceProvider` now
 * turns it off, and a test asserts it is gone.
 *
 * ## Why this is thin, and stays thin
 *
 * Verification, deduplication and the projection are all {@see StripeWebhooks}'.
 * What lives here is the HTTP shape, and every branch of it is a decision about
 * **whether Stripe should try again**:
 *
 *   400  the signature did not verify, or the body was not an event. Retrying
 *        will not fix either, and the request may not be from Stripe at all.
 *   500  our fault — a database error, an unreachable dependency. Stripe retries
 *        for three days, which is the behaviour we want.
 *   200  handled, ignored, unlinked, superseded, unmodelled, or a duplicate. All
 *        six are *finished*, and none of them is improved by redelivery.
 *
 * ⚠️ **A MISSING WEBHOOK SECRET LANDS IN THE 400 BRANCH AND LOOKS LIKE NOTHING.**
 * That is deliberate — an endpoint that accepts unverified events is one anybody
 * can post a paid subscription to — but the symptom is subscriptions that never
 * leave `pending_checkout` while Checkout itself works perfectly.
 * `CredentialManifest` states it on the Ops board for exactly that reason.
 */
final class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeWebhooks $webhooks,
        PlatformHealth $health,
    ): JsonResponse {
        try {
            // getContent(), never $request->all(): the signature covers the
            // exact bytes Stripe sent, so a JSON round trip through the
            // framework invalidates a signature that was perfectly good.
            $event = $webhooks->verify(
                $request->getContent(),
                $request->header('Stripe-Signature'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException|RuntimeException) {
            // ⚠️ NOTHING FROM THE EXCEPTION REACHES THE RESPONSE OR THE LOG LINE
            // HERE. A verification failure message can quote the signature
            // header, and a RuntimeException on this path is
            // PlatformCredentials saying which key is missing — neither is
            // something to hand to an unauthenticated caller who may not be
            // Stripe at all.
            //
            // ⚠️ **COUNTED, AND THE COUNTER IS WHY THE PARAGRAPH ABOVE STOPPED
            // BEING ONLY A COMMENT** (T176 P23). A missing webhook secret lands
            // here and looks like nothing at all: Checkout works, and no
            // subscription ever leaves `pending_checkout`. Nothing but a count
            // over time can tell that apart from an endpoint nobody is posting
            // to. **The name is ours and nothing from the request goes with
            // it** — the rule this branch already keeps for the response body.
            $health->recordFailure(PlatformHealthSignal::WebhookSignature, 'stripe');

            return response()->json(['error' => 'signature verification failed'], 400);
        }

        try {
            $outcome = $webhooks->process($event);
        } catch (UnexpectedValueException) {
            return response()->json(['error' => 'unrecognised event shape'], 400);
        }

        // A duplicate is a null, and it is a 200: Stripe did its job and we had
        // already done ours.
        return response()->json([
            'handled' => $outcome instanceof GatewayEventOutcome ? $outcome->value : 'duplicate',
        ]);
    }
}
