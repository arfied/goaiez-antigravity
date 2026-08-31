<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

/**
 * A Google Places request that did not come back usable.
 *
 * Mirrors ProviderRequestFailed's shape without inheriting it: that class is
 * built around an OAuth provider and a Business, and Places has neither — one
 * platform API key, no tenant, on a path that runs before signup.
 *
 * CARRIES A SHORT REASON CODE, NEVER THE VENDOR BODY. An error body can echo the
 * request, and a Places request can carry a business name a visitor typed. The
 * same discipline VendorLog applies to logging applies here to exceptions,
 * because an exception message ends up in the same places a log line does.
 */
final class PlacesRequestFailed extends Exception
{
    private function __construct(
        public readonly string $reason,
        public readonly ?int $status,
        public readonly bool $quota,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function from(Response $response): self
    {
        $status = $response->status();

        // 429 is rate limiting; Places also returns 403 with RESOURCE_EXHAUSTED
        // when a project's quota is spent, which reads as an auth failure and
        // is not one.
        $quota = $status === 429
            || ($status === 403 && str_contains((string) $response->json('error.status'), 'RESOURCE_EXHAUSTED'));

        $reason = match (true) {
            $quota => 'quota',
            $status === 401, $status === 403 => 'unauthorized',
            $status === 400 => 'bad_request',
            $status >= 500 => 'server_error',
            default => 'http_'.$status,
        };

        return new self($reason, $status, $quota, "Places request failed ({$reason}).");
    }

    public static function unreachable(): self
    {
        return new self('unreachable', null, false, 'Places request failed (unreachable).');
    }

    /**
     * No platform API key, so no request was made.
     *
     * ⛔ **A DEPLOYMENT FAULT ARRIVING AS THE FAILURE THE CALLERS ALREADY
     * HANDLE, WHICH IS THE WHOLE POINT** (9144). `PlatformCredentials::get()`
     * raises a bare `RuntimeException`, and every caller of this client catches
     * `PlacesBudgetExhausted` and `PlacesRequestFailed` and nothing else — so
     * an unset `google_places_key` was a 500 on the marketing home, on the
     * public audit and on wizard step 2, for as long as the key was unset.
     *
     * ⚠️ **THE REASON CODE REACHES A PUBLIC PAGE AND IS WORDED FOR THAT.**
     * `AuditContextBuilder` stamps `places_{reason}` onto a check result and
     * `PublicAuditResource` ships that field to any visitor, so this may not
     * say `api_key_missing`. `unconfigured` is what the operator needs to tell
     * it from a vendor outage in a log line and is not an instruction to
     * anybody outside.
     *
     * ⚠️ Reachable only in a race: the audit cannot *start* without a
     * successful text search, so this arm is a key cleared between the search
     * and the checks. It exists because the alternative on that race is the
     * 500 this closes.
     */
    public static function unconfigured(): self
    {
        return new self('unconfigured', null, false, 'Places request failed (unconfigured).');
    }

    /**
     * Whether retrying later could plausibly succeed.
     */
    public function isRetryable(): bool
    {
        return $this->quota
            || $this->reason === 'unreachable'
            || $this->reason === 'server_error';
    }
}
