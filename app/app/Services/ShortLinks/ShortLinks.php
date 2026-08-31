<?php

declare(strict_types=1);

namespace App\Services\ShortLinks;

use App\Enums\ShortLinkPurpose;
use App\Models\Customer;
use App\Models\ShortLink;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mint, address and resolve the redirects a message carries — T137 `SL-5`.
 *
 * The only reader and writer of `short_links`; a chokepoint lint holds that, on
 * the reasoning decisions 624 and 1223 give for every other store behind one.
 */
final readonly class ShortLinks
{
    /**
     * Token length, in base62 characters.
     *
     * ⚠️ **THE TRADE IS ENTROPY AGAINST THE SMS BUDGET AND BOTH SIDES ARE
     * REAL.** Twelve base62 characters is about 71 bits — far past guessable,
     * and short enough that `https://goaiez.ai/` plus a token costs 30
     * characters of the 159 a segment allows (2116 records that the domain fits
     * that budget exactly, which is why it is the domain).
     *
     * ⛔ **NOT SEQUENTIAL AND NOT DERIVED FROM ANYTHING.** An incrementing or
     * hashed-id token would let anybody holding one link enumerate every other
     * tenant's, and `public_read` on this table means the database would happily
     * serve them.
     */
    public const int TOKEN_LENGTH = 12;

    public function __construct(private DefaultsRegistry $defaults) {}

    /**
     * Mint a link for one send.
     *
     * ⚠️ **PER SEND, NEVER PER CAMPAIGN.** A shared token answers "somebody
     * clicked"; a per-send one answers "this contact clicked", which is the only
     * version worth putting on a timeline — and the only one that can be revoked
     * without touching anybody else's message.
     */
    public function mint(
        string $targetUrl,
        ShortLinkPurpose $purpose,
        ?Customer $customer = null,
        ?Carbon $expiresAt = null,
    ): ShortLink {
        Tenancy::idOrFail();

        return ShortLink::query()->create([
            'token' => $this->freshToken(),
            'target_url' => $targetUrl,
            'customer_id' => $customer?->id,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * The address to put in a message.
     *
     * Reads the domain from the registry rather than a constant, because 2116
     * settled it as `goaiez.ai` and a domain that moves should cost a seed
     * rather than a deploy — decision 2114 made the identical call for the
     * sending domain, and 30 survived four vendor changes precisely because it
     * was enforced in one place rather than remembered in several.
     */
    public function urlFor(ShortLink $link): string
    {
        $domain = $this->domain();

        return 'https://'.$domain.'/'.$link->token;
    }

    public function domain(): string
    {
        $domain = $this->defaults->value('messaging.short_link_domain');

        if (! is_string($domain) || trim($domain) === '') {
            throw new RuntimeException(
                'messaging.short_link_domain is unset. A short link with no domain would be minted '
                .'into a message as a relative path, which is unclickable and unrecoverable once sent.'
            );
        }

        return trim($domain);
    }

    /**
     * Find a link by its token, with no tenant established.
     *
     * ⚠️ **THE ONE PLACE IN THIS APPLICATION THAT DROPS THIS MODEL'S TENANT
     * SCOPE, AND IT IS AUDITED RATHER THAN GENERAL** — decision 401's answer,
     * taken deliberately over `FeedbackPage`'s. The redirect arrives from
     * somebody's phone with no session and no tenant, and **this row is what
     * establishes one**: 318's circularity exactly. A scoped query here calls
     * `Tenancy::idOrFail()` and throws on the one lookup whose entire job is to
     * answer *which tenant is this*.
     *
     * ⛔ **WHAT KEEPS `public_read` FROM MEANING "PUBLIC TABLE" IS THAT NOTHING
     * LISTS THIS TABLE UNSCOPED.** There are exactly two unscoped reads in this
     * application, both in this class: this one, which takes one token and
     * returns one row, and {@see self::freshToken()}, which returns a boolean
     * and never a row at all. The token is 71 bits of randomness, so holding one
     * is not a capability anybody guesses into. **A third unscoped read has to
     * preserve that property or `public_read` stops meaning what this docblock
     * says it means** — and the lint in `TenancyTest` is what will make somebody
     * read this before adding one.
     */
    public function resolve(string $token): ?ShortLink
    {
        if ($token === '' || mb_strlen($token) > 32) {
            // Refused before the query rather than after, so a long or empty
            // token cannot become a table scan on a public endpoint.
            return null;
        }

        return ShortLink::withoutGlobalScopes()
            ->where('token', $token)
            ->first();
    }

    /**
     * Stop a link resolving, without deleting the evidence that it existed.
     *
     * ⚠️ Revoked rather than deleted so a later fetch is still distinguishable
     * from a token that never existed — one is a real message that got old, the
     * other is somebody guessing, and only the second is interesting.
     */
    public function revoke(ShortLink $link): void
    {
        Tenancy::idOrFail();

        $link->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * A token nothing else holds.
     *
     * ⚠️ **THE UNIQUE INDEX IS THE AUTHORITY AND THIS LOOP IS THE COURTESY.**
     * At 71 bits a collision is not a thing that happens, but the retry costs
     * nothing and the alternative — a unique violation surfacing as a 500 on the
     * send path — is a message that never goes out.
     */
    private function freshToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (ShortLink::withoutGlobalScopes()->where('token', $token)->exists());

        return $token;
    }
}
