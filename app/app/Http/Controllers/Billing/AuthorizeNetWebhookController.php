<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\GatewayEventOutcome;
use App\Enums\PlatformHealthSignal;
use App\Http\Controllers\Controller;
use App\Services\Billing\AuthorizeNetWebhooks;
use App\Services\Ops\PlatformHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use UnexpectedValueException;

/**
 * `POST /webhooks/authorize-net` — the only endpoint that may move an
 * Authorize.Net subscription.
 *
 * ## Why this is thin, and stays thin
 *
 * Verification, deduplication and the projection all live in the service. What
 * lives here is the HTTP shape, and every branch of it is a decision about
 * **whether the vendor should try again**:
 *
 *   400  the signature did not verify, or the body was not a notification.
 *        Retrying will not fix either, and the request may not be from
 *        Authorize.Net at all.
 *   500  our fault — a database error, an unreachable dependency.
 *   200  handled, ignored, unlinked, superseded, unmodelled, or a duplicate.
 *        All six are *finished*.
 *
 * ⚠️ **THE 500 BRANCH IS WORTH LESS HERE THAN IT IS ON THE STRIPE ENDPOINT, AND
 * THAT IS A FACT ABOUT THE VENDOR RATHER THAN ABOUT THIS CODE.** Stripe retries
 * for three days, so a 500 genuinely buys another attempt. **Authorize.Net
 * publishes no retry schedule**, so a notification refused for our own reasons
 * may simply be lost — which is why `ARBGetSubscriptionStatusRequest` exists as
 * a reconciliation read and why dunning consults it before every attempt rather
 * than trusting its own record.
 *
 * ⚠️ **A MISSING SIGNATURE KEY LANDS IN THE 400 BRANCH AND LOOKS LIKE NOTHING.**
 * That is deliberate — an endpoint that accepts unverified events is one anybody
 * can post a paid subscription to — but the symptom is subscriptions that never
 * update while checkout itself works perfectly. `CredentialManifest` states it
 * on the Ops board for exactly that reason.
 */
final class AuthorizeNetWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        AuthorizeNetWebhooks $webhooks,
        PlatformHealth $health,
    ): JsonResponse {
        try {
            // getContent(), never $request->all(): the signature covers the
            // exact bytes the vendor sent, so a JSON round trip through the
            // framework invalidates a signature that was perfectly good.
            $event = $webhooks->verify(
                $request->getContent(),
                $request->header('X-ANET-Signature'),
            );
        } catch (UnexpectedValueException|RuntimeException) {
            // ⚠️ NOTHING FROM THE EXCEPTION REACHES THE RESPONSE OR A LOG LINE
            // HERE. A verification failure message can quote the signature
            // header, and a RuntimeException on this path is PlatformCredentials
            // saying which key is missing — neither is something to hand to an
            // unauthenticated caller who may not be the vendor at all.
            //
            // ⚠️ **COUNTED (T176 P23), AND ON THE GATEWAY THAT MATTERS MOST.**
            // The docblock above already says a missing signature key here looks
            // like nothing while subscriptions silently stop updating — and this
            // vendor publishes no retry schedule, so what is lost during the
            // silence is lost. The count is the difference between noticing in
            // an hour and noticing at the month's reconciliation.
            $health->recordFailure(PlatformHealthSignal::WebhookSignature, 'authorize_net');

            return response()->json(['error' => 'signature verification failed'], 400);
        }

        try {
            $outcome = $webhooks->process($event);
        } catch (UnexpectedValueException) {
            return response()->json(['error' => 'unrecognised event shape'], 400);
        }

        // A duplicate is a null, and it is a 200: the vendor did its job and we
        // had already done ours.
        return response()->json([
            'handled' => $outcome instanceof GatewayEventOutcome ? $outcome->value : 'duplicate',
        ]);
    }
}
