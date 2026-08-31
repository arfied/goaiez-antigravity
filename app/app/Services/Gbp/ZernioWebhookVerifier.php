<?php

declare(strict_types=1);

namespace App\Services\Gbp;

use App\Contracts\VerifiesWebhookSenders;
use App\Support\PlatformCredentials;
use App\Support\WebhookMaterial;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Whether a Zernio webhook actually came from Zernio.
 *
 * Verified against Zernio's live webhooks overview on 2026-08-10:
 *
 *   - Header `X-Zernio-Signature` (legacy alias `X-Late-Signature`)
 *   - Lowercase hex HMAC-SHA256 of the **raw request body** keyed by the
 *     webhook secret
 *   - Secret is optional on their side — when configured, every delivery is
 *     signed. We require the secret always (fail closed), same shape as
 *     Infobip 1578: an unconfigured endpoint that skipped verification would
 *     accept every forgery while looking healthy.
 */
final class ZernioWebhookVerifier implements VerifiesWebhookSenders
{
    /**
     * The signing secret every Zernio delivery is judged with.
     *
     * ⚠️ Named once so that {@see self::verifyingMaterial()} and
     * {@see self::secret()} cannot come to mean two different keys.
     */
    public const string CREDENTIAL = 'zernio_webhook_secret';

    public const string HEADER = 'X-Zernio-Signature';

    public const string LEGACY_HEADER = 'X-Late-Signature';

    /**
     * ⚠️ **AN ABSENT SECRET HERE IS QUIETER THAN INFOBIP'S AND NOT HARMLESS.**
     * The fifteen-minute `gbp:sync` poll still runs, so reviews still arrive
     * eventually and the endpoint being shut looks like nothing at all — which
     * is precisely why it needs an at-rest reader rather than a delivery.
     */
    public static function verifyingMaterial(): WebhookMaterial
    {
        return WebhookMaterial::credentials([self::CREDENTIAL]);
    }

    public function verify(Request $request): bool
    {
        $secret = $this->secret();

        if ($secret === null) {
            return false;
        }

        $presented = $request->header(self::HEADER)
            ?? $request->header(self::LEGACY_HEADER);

        if (! is_string($presented) || $presented === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $presented);
    }

    private function secret(): ?string
    {
        try {
            return PlatformCredentials::get(self::CREDENTIAL);
        } catch (RuntimeException) {
            return null;
        }
    }
}
