<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\OutreachChannel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use JsonException;

/**
 * Mint and open the one-click unsubscribe token — T176 P21, RFC 8058.
 *
 * ## Why the token is sealed rather than stored
 *
 * The endpoint is reached by a mail client posting unattended, with no session,
 * no cookie and no tenant — `ShortLinkController`'s situation, and the same
 * circularity 318 records: the thing presented is what has to establish the
 * tenant. Three shapes were available and two were refused.
 *
 *   - **A row in a new table.** It works, and it is what `short_links` does. It
 *     costs a migration, an RLS `public_read` policy, a per-send insert on the
 *     send path, and — the part that decided it — **a second copy of the
 *     recipient's email address at rest**, because `ConsentService::suppress()`
 *     needs the raw identifier and a hash cannot be reversed into one.
 *     `CLAUDE.md`'s tie-breaker is *less stored PII*.
 *   - **Reusing `short_links`.** Tempting: 71 bits, per send, `public_read`,
 *     already carries a `customer_id`. ⛔ **Refused because it carries a contact
 *     rather than an address.** Resolving the contact and reading
 *     `$customer->email` back is exactly what {@see UnsubscribeClaim} exists to
 *     prevent. `short_links` is also a redirect table whose whole contract is a
 *     `target_url` and a 302, and a POST endpoint is neither.
 *   - **A signed route over a customer id.** `customers` is tenant-owned and
 *     RLS-`FORCE`d, so an unauthenticated read returns nothing whatever the
 *     signature says — the same wall `/m/{business}/{recipient}` had to route
 *     around by putting the tenant *inside* the signature.
 *
 * So the token is the claim, encrypted. It is unguessable because forging one
 * needs `APP_KEY`; it leaks no address because the payload is ciphertext; it
 * needs no storage, no migration and no cleanup; and it establishes its own
 * tenant. **A leaked `APP_KEY` forges these** — and also every signed URL, every
 * session and every credential in the vault, so it is not the threat this design
 * is chosen against.
 *
 * ⚠️ **WHAT A HOLDER OF A TOKEN CAN DO IS SUPPRESS ONE ADDRESS AND NOTHING
 * ELSE.** It grants no read: the response says the same thing whether or not
 * anything was suppressed, so a token is not a way to ask whether an address is
 * on a tenant's list. That property is what makes the fail-safe direction here
 * *act* rather than *refuse* — the worst a replayed token achieves is a second
 * `firstOrCreate` that writes nothing.
 */
final class UnsubscribeLinks
{
    /**
     * How long a token stays live, in days.
     *
     * ⛔ **THE STATUTORY FLOOR IS 30 DAYS AND THIS IS DELIBERATELY MILES ABOVE
     * IT.** 15 U.S.C. §7704(a)(4)(A)(i) requires the opt-out mechanism to remain
     * capable of receiving a request *"for at least 30 days after the
     * transmission of the original message"*. 400 days means a person who finds
     * the message a year later still opts out with one click instead of
     * discovering that the sender stopped listening — and an expired unsubscribe
     * link is a compliance failure the recipient experiences as being ignored.
     *
     * ⚠️ **A CONSTANT RATHER THAN A REGISTRY SEED, WHICH IS THE OPPOSITE OF
     * `mail.sending_domain`'s CALL AND FOR A STATED REASON.** An Ops-editable
     * lifetime is a support surface offering to set a legally-required window
     * below its floor, at 11pm, with no test in the way. `CLAUDE.md`'s rule is
     * that a toggle is a future support ticket; this one would be a future
     * violation.
     */
    public const int LIFETIME_DAYS = 400;

    /**
     * The longest token this will even attempt to decrypt.
     *
     * Refused before the cipher rather than after, so a megabyte of base64
     * cannot become work on a public endpoint — `ShortLinks::resolve()`'s own
     * guard, for the same reason.
     */
    private const int MAX_TOKEN_LENGTH = 4096;

    /**
     * The token for one send.
     *
     * ⚠️ **MINTED AT DELIVERY, NOT AT DISPATCH.** The sealed payload carries the
     * recipient's address, and `DeliverPlatformMail`'s payload is written to
     * `jobs` and survives into `failed_jobs`. Minting here — inside
     * `PlatformMailer::deliverNow()`, from the address it was handed — keeps the
     * blob out of the queue row and stamps the token with the moment the message
     * actually left rather than the moment it was queued, which is what the
     * 30-day floor is measured from.
     */
    public function mint(int $businessId, string $identifier, OutreachChannel $channel): string
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            throw new InvalidArgumentException(
                'Cannot mint an unsubscribe token with no identifier. A token that names nobody '
                .'produces a working-looking link that can never suppress anything, which is worse '
                .'than no link at all: the recipient believes they have opted out.'
            );
        }

        $payload = json_encode([
            'b' => $businessId,
            'i' => $identifier,
            'c' => $channel->value,
            't' => CarbonImmutable::now()->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        return self::toUrlAlphabet(Crypt::encryptString($payload));
    }

    /**
     * What a token was carrying, or null when it carries nothing usable.
     *
     * ⚠️ **ONE ANSWER FOR EVERY FAILURE, AND THAT IS NOT TIDINESS.** Forged,
     * truncated, edited, expired, encrypted under a rotated key and simply
     * nonsense all return null, and the controller answers all of them
     * identically — `ShortLinkController`'s rule, for its reason: a
     * distinguishable response tells whoever is probing which of their guesses
     * got closer.
     */
    public function open(string $token): ?UnsubscribeClaim
    {
        if ($token === '' || mb_strlen($token) > self::MAX_TOKEN_LENGTH) {
            return null;
        }

        try {
            $plain = Crypt::decryptString(self::fromUrlAlphabet($token));
        } catch (DecryptException) {
            // Covers a forgery, an edited character, a truncation and a key
            // rotation alike. The MAC is what makes all four the same event.
            return null;
        }

        try {
            /** @var mixed $payload */
            $payload = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $businessId = $payload['b'] ?? null;
        $identifier = $payload['i'] ?? null;
        $channel = is_string($payload['c'] ?? null) ? OutreachChannel::tryFrom($payload['c']) : null;
        $issuedAt = $payload['t'] ?? null;

        if (! is_int($businessId) || ! is_string($identifier) || trim($identifier) === '') {
            return null;
        }

        if (! $channel instanceof OutreachChannel || ! is_int($issuedAt)) {
            return null;
        }

        $issued = CarbonImmutable::createFromTimestamp($issuedAt);

        // ⚠️ **BOTH ENDS OF THE WINDOW.** A token stamped in the future is not a
        // clock skew this should absorb — it is a payload somebody re-sealed,
        // which means the key is gone and every other token is forgeable too.
        // Refusing it changes nothing about that, and accepting it would make
        // the expiry unbounded for exactly the caller who wanted it to be.
        if ($issued->isFuture() || $issued->addDays(self::LIFETIME_DAYS)->isPast()) {
            return null;
        }

        return new UnsubscribeClaim(
            businessId: $businessId,
            identifier: trim($identifier),
            channel: $channel,
            issuedAt: $issued,
        );
    }

    /**
     * Laravel's ciphertext is base64 text; a URL path segment is not.
     *
     * `+`, `/` and `=` are all reserved or meaningful in a path, so they are
     * mapped onto three unreserved characters (RFC 3986 §2.3) rather than
     * percent-encoded — a percent sequence in a `List-Unsubscribe` header is
     * decoded by some clients and forwarded verbatim by others, and a token that
     * survives one mail client and not another is the failure nobody sees in
     * testing.
     */
    private static function toUrlAlphabet(string $cipher): string
    {
        return strtr($cipher, '+/=', '-_~');
    }

    private static function fromUrlAlphabet(string $token): string
    {
        return strtr($token, '-_~', '+/=');
    }
}
