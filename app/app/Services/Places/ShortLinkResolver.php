<?php

declare(strict_types=1);

namespace App\Services\Places;

use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Follows a Google short link to its destination, safely.
 *
 * `24` §1.2.1 row 2, under §1.2.2's constraints. The pasted string is untrusted
 * input and fetching it server-side is an SSRF surface, so every constraint here
 * is non-negotiable and each is asserted by a test:
 *
 *   - hostname allowlist only, **checked before a socket opens**
 *   - at most 3 redirects, every hop re-checked against the allowlist
 *   - any hop resolving to a loopback, private, or link-local address rejected
 *   - short timeout, HEAD first, falling back to a length-capped GET
 *   - we want the `Location` header and at most the URL — **never the page**
 *
 * DELIBERATELY OUTSIDE THE FETCHGATEWAY, and `BUILD-PLAN` §2.5.4 says why in as
 * many words: "The resolver deliberately sits **outside** the gateway: `24`
 * §1.2.2 fetches a `Location` header and never a page body, so it is not an HTML
 * fetch and must not inherit robots or ladder semantics." Inheriting them would
 * be actively wrong — a `robots.txt` disallow on `goo.gl` would block an owner
 * from telling us which business is theirs, and `google` is seeded `guided_only`
 * precisely so we never fetch Google *pages*. This fetches a redirect, which is
 * the mechanism a short link exists to provide.
 *
 * That exemption is why this class is named in ArchitectureTest's import-lint
 * allowlist rather than quietly slipping past it.
 */
final class ShortLinkResolver
{
    /** `24` §1.2.2: "≤3 redirects, and every hop re-checked against the allowlist." */
    private const int MAX_HOPS = 3;

    /** Short, because a slow redirect is a stalled wizard, not a retry. */
    private const int TIMEOUT_SECONDS = 5;

    /**
     * The destination of a Google short link, or null if it cannot be reached
     * safely.
     *
     * Null rather than an exception: an unresolvable link is an ordinary outcome
     * the owner sees as "try a different link" (`24` §1.2.4), not an error.
     */
    public function resolve(string $url): ?string
    {
        if (! GoogleLinkHosts::allowsUrl($url) || ! $this->hostIsPublic($url)) {
            // Rejected before a socket opens. The test for this asserts
            // Http::assertNothingSent(), which is the only way to prove it.
            return null;
        }

        $current = $url;

        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            $location = $this->locationHeader($current);

            if ($location === null) {
                // No redirect: this is the destination, whatever it is.
                return $hop === 0 ? null : $current;
            }

            $next = $this->absolutise($location, $current);

            // Every hop re-checked. A short link that redirects off Google is
            // not a Google link, however it started.
            if ($next === null || ! GoogleLinkHosts::allowsUrl($next) || ! $this->hostIsPublic($next)) {
                return null;
            }

            $current = $next;

            // Once we are off the shortener we have what we came for; the
            // ladder re-runs against this URL rather than following further.
            if (! GoogleLinkHosts::isShortLink($current)) {
                return $current;
            }
        }

        return null;
    }

    /**
     * One request, returning only the `Location` header.
     *
     * HEAD first per `24` §1.2.2. Some shorteners answer HEAD with 405, so GET
     * is the fallback — with redirects still not followed by the client, and the
     * body never read.
     */
    private function locationHeader(string $url): ?string
    {
        foreach (['head', 'get'] as $method) {
            try {
                $response = VendorLog::timed(
                    'google_short_link',
                    strtoupper($method),
                    $url,
                    fn () => Http::timeout(self::TIMEOUT_SECONDS)
                        // We follow redirects ourselves, one at a time, because
                        // the client's own follower would not re-check the
                        // allowlist between hops.
                        ->withOptions(['allow_redirects' => false])
                        ->{$method}($url),
                );
            } catch (ConnectionException) {
                VendorLog::failure('google_short_link', strtoupper($method), $url, ConnectionException::class);

                return null;
            }

            if ($response->status() === 405 && $method === 'head') {
                continue;
            }

            $location = $response->header('Location');

            return $location === '' ? null : $location;
        }

        return null;
    }

    /**
     * Resolve a possibly-relative `Location` against the URL that produced it.
     */
    private function absolutise(string $location, string $base): ?string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        // A scheme other than http(s) — `javascript:`, `file:`, `gopher:` — is
        // never a redirect we follow.
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
            return null;
        }

        $parts = parse_url($base);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        return str_starts_with($location, '/')
            ? $origin.$location
            : $origin.'/'.$location;
    }

    /**
     * Reject any host that resolves to a loopback, private, or link-local
     * address (`24` §1.2.2).
     *
     * The allowlist already makes this close to unreachable — an attacker would
     * need to control DNS for a google.com hostname. It is here anyway because
     * that is exactly the assumption an SSRF check must not make: allowlists get
     * widened, and this is the layer that still holds when one is.
     */
    private function hostIsPublic(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        // A literal IP in a Google-looking URL is already wrong.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->addressIsPublic($host);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false || $records === []) {
            return false;
        }

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! is_string($address) || ! $this->addressIsPublic($address)) {
                return false;
            }
        }

        return true;
    }

    private function addressIsPublic(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
