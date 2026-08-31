<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OauthProvider;
use App\Services\Oauth\TokenService;
use RuntimeException;

/**
 * A token refresh did not produce a usable token.
 *
 * The `permanent` flag is the whole point of this class, and it is the one
 * decision the refresh path cannot get wrong in either direction:
 *
 *   permanent    the grant is dead — revoked, expired beyond recovery, consent
 *                withdrawn. Retrying cannot help. Flip the connection to expired
 *                and ask the owner to reconnect.
 *
 *   transient    the provider was slow or returned 5xx. The grant is probably
 *                fine. Leave the connection alone and let the queue retry with
 *                backoff.
 *
 * Treating a transient failure as permanent nags an owner to reconnect a
 * perfectly good account every time a provider has a bad afternoon. Treating a
 * permanent failure as transient burns the retry budget and leaves the
 * integration quietly dead with nobody told.
 *
 * ## `reachedVendor` — the second flag, and it has exactly one reader
 *
 * ⛔ **A `ConnectionException` USED TO ARRIVE HERE AS `transient(…, 'connection')`
 * AND WAS INDISTINGUISHABLE FROM A 503** (7400). Two frames later
 * {@see TokenService::refresh()} wrote that reason onto the tenant's
 * `provider_health` row, so **our own DNS failure, or an egress rule on our own
 * box, was recorded as `last_error` against somebody else's OAuth grant** — the
 * same shape 7261 removed from `GoogleSearchConsoleClient`, one frame further
 * out, which is why the lint that caught that one could not see this one.
 *
 * The catch is the only frame that still knows, so the distinction is made
 * there and carried rather than re-derived: `unreachable()` says the request
 * never became a response, and every other constructor says the provider
 * answered. `TokenService::refresh()` reads it to decide whether there is any
 * true statement to write about the far end at all — one reader, which is the
 * bar this file's siblings set (*"a field with no reader is the shape this
 * codebase has recorded fourteen times"*).
 *
 * ⚠️ **IT IS NOT A THIRD RETRY OUTCOME.** `unreachable()` is `permanent: false`
 * exactly as `transient()` is, so the queue's ladder is untouched — the flag
 * changes what is *recorded*, never what is *retried*.
 *
 * The message is ours, not the provider's. A provider error body can contain the
 * refresh token that was rejected, the client id, and a correlation id that
 * identifies the tenant — none of which belongs in an exception message that
 * will reach a log or an error tracker.
 */
final class TokenRefreshFailed extends RuntimeException
{
    private function __construct(
        public readonly OauthProvider $provider,
        public readonly bool $permanent,
        public readonly string $reason,
        /**
         * Did the provider answer at all?
         *
         * False only from {@see self::unreachable()}. When it is false the
         * reason describes **our** network and nothing about theirs, so there
         * is no true sentence to write on a row whose every column is about
         * the far end.
         */
        public readonly bool $reachedVendor = true,
    ) {
        parent::__construct("{$provider->value} token refresh failed: {$reason}");
    }

    /**
     * The grant is dead. Reconnection is the only fix.
     *
     * @param  string  $reason  A short, provider-supplied error *code* — never a
     *                          description, and never a response body.
     */
    public static function permanent(OauthProvider $provider, string $reason): self
    {
        return new self($provider, true, $reason);
    }

    /**
     * The provider failed us, not the other way round.
     */
    public static function transient(OauthProvider $provider, string $reason): self
    {
        return new self($provider, false, $reason);
    }

    /**
     * The request never became a response — DNS, a refused connection, a
     * timeout, an egress rule of ours.
     *
     * Transient like {@see self::transient()}, and deliberately not a third
     * retry outcome. What it adds is that the provider said nothing, so the
     * reason is a fact about our own network and must not be filed as one about
     * the tenant's grant.
     *
     * ⚠️ **THE NAME IS LOAD-BEARING.** `tests/Feature/Architecture/VisibilityTest.php`'s
     * *"a transport failure is never constructed as a failure the vendor
     * answered"* requires every `catch (ConnectionException …)` in `app/` that
     * constructs one of our failure types to name a constructor that means
     * this. It matches the spelling already used by `StripeRequestFailed`,
     * `PlacesRequestFailed`, `WordPressRequestFailed`, `AuthorizeNetRequestFailed`,
     * `ProviderRequestFailed` and `SearchConsoleRequestFailed`.
     */
    public static function unreachable(OauthProvider $provider, string $reason): self
    {
        return new self($provider, false, $reason, reachedVendor: false);
    }
}
