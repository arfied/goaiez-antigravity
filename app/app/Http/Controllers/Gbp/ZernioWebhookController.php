<?php

declare(strict_types=1);

namespace App\Http\Controllers\Gbp;

use App\Enums\PlatformHealthSignal;
use App\Http\Controllers\Controller;
use App\Services\Gbp\ZernioWebhooks;
use App\Services\Gbp\ZernioWebhookVerifier;
use App\Services\Ops\PlatformHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /webhooks/zernio` — review and account lifecycle from Zernio.
 *
 * Thin HTTP surface. Verification is {@see ZernioWebhookVerifier}; everything
 * else is {@see ZernioWebhooks}. Same status vocabulary as Infobip inbound:
 * 401 unverified, 422 unreadable, 200 handled/duplicate/ignored.
 */
final class ZernioWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        ZernioWebhookVerifier $verifier,
        ZernioWebhooks $webhooks,
        PlatformHealth $health,
    ): JsonResponse {
        if (! $verifier->verify($request)) {
            // Counted (T176 P23) — see `PlatformHealthSignal::WebhookSignature`.
            $health->recordFailure(PlatformHealthSignal::WebhookSignature, 'zernio');

            return response()->json(['error' => 'unverified'], 401);
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            return response()->json(['error' => 'unreadable payload'], 422);
        }

        $outcome = $webhooks->handle($payload);

        return response()->json(['ok' => true, 'outcome' => $outcome]);
    }
}
