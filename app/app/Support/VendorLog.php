<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * The one place an outbound vendor call is written to the log.
 *
 * FOUND-03 asks for "log every external API call for debugging". The debugging
 * value is in the shape of the call — who, where, what came back, how long — and
 * none of it is in the payload. Vendor payloads routinely carry personal data,
 * and a token endpoint's request body IS the credential.
 *
 * So this builds its context from an **allowlist**, never by redacting a payload
 * it was handed. A denylist is the wrong shape for this problem: it fails open
 * the first time a provider adds a field, and the failure is invisible because
 * the log looks fine right up until someone reads it.
 *
 * What is deliberately absent, and stays absent:
 *
 *   request body     a refresh grant's body is client_secret + refresh_token
 *   request headers  Authorization is a bearer token
 *   response body    tokens on the way back, customer data everywhere else
 *   query string     Meta puts the token in the query, so even the URL is
 *                    trimmed to scheme + host + path before it is recorded
 *
 * The business id is included and is meant to be: `29` §2.4 permits the tenant
 * id in log context and it is what makes an incident traceable. Customer data
 * is not, ever.
 */
final class VendorLog
{
    /**
     * Record a completed call.
     *
     * @param  array<string, scalar|null>  $extra  Additional non-sensitive
     *                                             context. Anything passed here
     *                                             must already be safe to log —
     *                                             this method cannot tell.
     */
    public static function call(
        string $provider,
        string $method,
        string $url,
        int $status,
        float $durationMs,
        ?int $businessId = null,
        array $extra = [],
    ): void {
        Log::info('vendor call', [
            'provider' => $provider,
            'method' => strtoupper($method),
            'endpoint' => self::endpoint($url),
            'status' => $status,
            'duration_ms' => round($durationMs, 1),
            'business_id' => $businessId,
            ...$extra,
        ]);
    }

    /**
     * The failure reason for an HTTP error response. The vendor's own message is appended ONLY for a
     * request-shape error (400, 422): there it names the fault — a schema the provider rejects — and carries
     * no credential. Every other status logs its code alone: an auth error's message can quote part of the
     * key (OpenAI's 401 does), which `failure()` below must never receive.
     */
    public static function httpReason(int $status, string $vendorMessage): string
    {
        if (in_array($status, [400, 422], true) && $vendorMessage !== '') {
            return 'http_'.$status.': '.mb_substr($vendorMessage, 0, 300);
        }

        return 'http_'.$status;
    }

    /**
     * Record a call that produced no usable answer.
     *
     * Not only a call that never returned: callers also use this for a response
     * that arrived and was unusable — a 5xx, or a 200 whose body refuses. Those
     * are already recorded by `timed()` as the calls they were, and this adds the
     * warning that says the outcome was not the one the caller wanted.
     *
     * `$reason` is a fixed label or an exception *class*, never a vendor message:
     * a Guzzle connection exception's message contains the full request URI, and
     * for Meta that URI contains the access token. The same rule binds anything
     * derived from a response body — see TurnstileVerifier::ERROR_CODES.
     */
    public static function failure(
        string $provider,
        string $method,
        string $url,
        string $reason,
        ?int $businessId = null,
    ): void {
        Log::warning('vendor call failed', [
            'provider' => $provider,
            'method' => strtoupper($method),
            'endpoint' => self::endpoint($url),
            'reason' => $reason,
            'business_id' => $businessId,
        ]);
    }

    /**
     * Time a call and log it, whatever it returns.
     *
     * @param  callable(): Response  $call
     */
    public static function timed(
        string $provider,
        string $method,
        string $url,
        callable $call,
        ?int $businessId = null,
    ): Response {
        $startedAt = microtime(true);

        $response = $call();

        self::call(
            $provider,
            $method,
            $url,
            $response->status(),
            (microtime(true) - $startedAt) * 1000,
            $businessId,
        );

        return $response;
    }

    /**
     * Scheme, host and path. No query string, no fragment, no credentials.
     */
    private static function endpoint(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return '(unparseable)';
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';

        return $scheme.$host.$path;
    }
}
