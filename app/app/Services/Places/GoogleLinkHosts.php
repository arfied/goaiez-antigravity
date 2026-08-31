<?php

declare(strict_types=1);

namespace App\Services\Places;

/**
 * The hostname allowlist for anything pasted as a "Google link".
 *
 * `24` §1.2.2: "Hostname allowlist only — `maps.app.goo.gl`, `goo.gl`,
 * `google.com` and its ccTLDs, `maps.google.com`, `search.google.com`. Anything
 * else is rejected before a socket opens."
 *
 * ONE LIST, ONE CODE PATH. `BUILD-PLAN` §4.3 exists because rows 2 and 3 resolve
 * the same thing from the same inputs, and §2.5.5 decision 197 settles that the
 * resolver ships whole in row 2 rather than being split: "The SSRF host
 * allowlist and the confirm-then-persist contract are the security-critical
 * halves, and splitting them across two rows is exactly how the second version
 * appears." This class is the allowlist that must never have a second version.
 *
 * MATCHED AS A SUFFIX, NOT A SUBSTRING. `notgoogle.com` and
 * `google.com.evil.test` both contain "google.com"; neither is Google. Every
 * check here is an exact host match or a dot-anchored suffix, which is the
 * difference between an allowlist and a hope.
 */
final class GoogleLinkHosts
{
    /**
     * The hosts `ShortLinkResolver` actually fetches.
     *
     * A SUBSET OF `EXACT`, AND THE DISTINCTION IS LOAD-BEARING. `EXACT` is an
     * *input* allowlist — which pasted URLs we accept from an owner. This is an
     * *outbound* list — which servers we open a socket to. The other four hosts
     * in `EXACT` are parsed and never contacted.
     *
     * Split out because the outbound-host lint scans this constant to build the
     * subprocessor inventory's Google short-link row (decision 430). Scanning
     * `EXACT` instead would name four vendors we never contact, which is the
     * over-claiming `SUBPROCESSOR-INVENTORY.md` §0 warns against.
     *
     * @var list<string>
     */
    public const array SHORT_LINK = [
        'maps.app.goo.gl',
        'goo.gl',
    ];

    /**
     * Exact hosts, matched case-insensitively.
     *
     * @var list<string>
     */
    private const array EXACT = [
        'maps.app.goo.gl',
        'goo.gl',
        'maps.google.com',
        'search.google.com',
        'www.google.com',
        'google.com',
    ];

    /**
     * Google's country domains — `google.co.uk`, `google.de`, `google.com.au`.
     *
     * Expressed as a pattern rather than a list of 190 strings, and deliberately
     * tight: two or three labels, letters only, so `google.com.evil.test` cannot
     * pass as a ccTLD.
     */
    private const string CCTLD_PATTERN = '/^(?:www\.|maps\.)?google\.[a-z]{2,3}(?:\.[a-z]{2})?$/i';

    public static function allows(string $host): bool
    {
        $host = strtolower(rtrim(trim($host), '.'));

        if ($host === '') {
            return false;
        }

        if (in_array($host, self::EXACT, true)) {
            return true;
        }

        return (bool) preg_match(self::CCTLD_PATTERN, $host);
    }

    /**
     * Whether a URL's host is allowed, with the URL parsed rather than matched.
     *
     * parse_url first, always. Matching a pattern against the whole URL string
     * is how `https://evil.test/?x=maps.app.goo.gl` gets through.
     */
    public static function allowsUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && self::allows($host);
    }

    /**
     * Whether this host is one of the short-link domains that needs resolving
     * (`24` §1.2.1 row 2).
     */
    public static function isShortLink(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host)) {
            return false;
        }

        $host = strtolower($host);

        if (! in_array($host, self::SHORT_LINK, true)) {
            return false;
        }

        if ($host === 'maps.app.goo.gl') {
            return true;
        }

        // goo.gl only counts as a Maps short link on the /maps path — the bare
        // domain shortened everything Google once had.
        return str_starts_with((string) parse_url($url, PHP_URL_PATH), '/maps');
    }
}
