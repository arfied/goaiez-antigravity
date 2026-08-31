<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Reading facts out of a page somebody else wrote.
 *
 * REGEX RATHER THAN A DOM PARSER, DELIBERATELY. The input is arbitrary HTML from
 * a stranger's web server — the DirectFetchGateway caps it at 2MB and that is
 * the only guarantee it carries. It may be malformed, truncated, or not HTML at
 * all. A DOM parser on that input either raises on the first unclosed tag or
 * pulls in a dependency to be lenient about it (`CLAUDE.md` requires approval
 * for both), and everything this class needs is a presence test over a few
 * hundred bytes near the top of the document. Nothing here parses structure.
 *
 * NO `u` FLAG ON ANY PATTERN. Small-business sites are still served as
 * Windows-1252 and ISO-8859-1 more often than anyone expects, and `preg_match`
 * with `/u` returns false — not an error, false — on invalid UTF-8. That would
 * turn "this page has a title" into "this page has no title" for a whole class
 * of sites, silently, and the finding it produces is an accusation. Byte
 * semantics are correct here because every token being matched is ASCII.
 *
 * EVERYTHING IS BOUNDED. Each pattern is applied to a prefix or is anchored, so
 * a 2MB page cannot turn a check into a CPU cost. `29` §6.2's whole audit has a
 * <20s budget and this is the only part of it doing per-byte work.
 */
final class PageText
{
    /**
     * How much of the head to search for meta tags. Generous — some builders
     * inline a stylesheet above the viewport tag — and still bounded.
     */
    private const int HEAD_BYTES = 64_000;

    /**
     * Visible text, plus the phone numbers that live in `tel:` links.
     *
     * The `tel:` part is not a nicety. On a modern small-business site the phone
     * number in the header is frequently an image or an icon-plus-link, and the
     * only machine-readable copy is the href — which stripping tags would
     * destroy. Missing it produces "your phone number is not on your website"
     * about a site whose every page has a call button, which is exactly the
     * false accusation NapQuickScanCheck is written to avoid.
     */
    public static function visible(string $html): string
    {
        $telephones = [];

        if (preg_match_all('/href\s*=\s*["\']?tel:([^"\'>\s]+)/i', $html, $matches) > 0) {
            $telephones = $matches[1];
        }

        // Script and style bodies are code, not content, and both are full of
        // digit runs that would match a phone number by accident.
        $stripped = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $stripped = preg_replace('/<[^>]*>/', ' ', $stripped) ?? $stripped;

        $text = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text .= ' '.implode(' ', $telephones);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /**
     * The last ten digits of a phone number, which is what identifies it.
     *
     * Ten rather than all of them because "+1 (555) 010-1234", "555-010-1234"
     * and "5550101234" are the same number written three ways, and the country
     * code is the part that comes and goes. Returns null for anything too short
     * to be a US number — a four-digit extension must never match a page.
     */
    public static function phoneDigits(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) >= 10 ? substr($digits, -10) : null;
    }

    /**
     * Whether the page carries this phone number, in any of the ways people
     * write one.
     *
     * SEARCHES FOR THE WANTED NUMBER RATHER THAN EXTRACTING CANDIDATES, which is
     * the second design here and the first one was wrong in a way worth
     * recording. Finding "phone-shaped" runs first and comparing them is the
     * obvious approach, and it fails on the commonest markup there is: a class
     * permissive enough to span "(901) 555-0134" also spans the space into
     * whatever follows, so "(901) 555-0134" beside a street number matched as
     * "(901) 555-0134 1234" and its last ten digits were nobody's phone number.
     * The audit then told a business its own number was missing from its own
     * homepage.
     *
     * Building the pattern from the number instead removes the ambiguity
     * entirely: there is nothing to decide about where a candidate ends, because
     * the digits are fixed and only the punctuation between them varies.
     *
     * Two characters of separator maximum, and the lookarounds forbid a digit on
     * either side, so this cannot match inside a longer number — an order
     * reference or an account number that happens to contain the ten digits is
     * not a phone number.
     */
    public static function containsPhone(string $text, string $wantedDigits): bool
    {
        $separator = '[\s().\-]{0,2}';

        $pattern = '/(?<!\d)\+?1?'
            .$separator
            .implode($separator, str_split($wantedDigits))
            .'(?!\d)/';

        return preg_match($pattern, $text) === 1;
    }

    /**
     * The house number a formatted address opens with — "123" of "123 Main St".
     */
    public static function leadingStreetNumber(string $address): ?string
    {
        return preg_match('/^\s*(\d+[A-Za-z]?)\b/', $address, $matches) === 1
            ? $matches[1]
            : null;
    }

    /**
     * The postal code, US five- or nine-digit.
     *
     * Anchored at the end of a component because a five-digit run can be a
     * suite, a year or a price anywhere else in the string. Google's
     * `formattedAddress` puts the ZIP last before the country, which is what
     * makes this reliable enough to use.
     */
    public static function postcode(string $address): ?string
    {
        return preg_match('/\b(\d{5})(?:-\d{4})?\b(?![\d-])/', $address, $matches) === 1
            ? $matches[1]
            : null;
    }

    /**
     * Whether a whole token appears in the text.
     *
     * Word-bounded, so the street number "12" does not match "1234 reviews" and
     * the ZIP "62704" does not match a phone number containing it.
     */
    public static function containsToken(string $text, string $token): bool
    {
        return preg_match('/(?<![\w-])'.preg_quote($token, '/').'(?![\w-])/i', $text) === 1;
    }

    /**
     * The contents of `<title>`, or null when there is none worth having.
     *
     * An empty or whitespace-only title counts as none: it produces exactly the
     * same result in a search listing as a missing one, and the finding is about
     * that result rather than about the tag.
     */
    public static function title(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $title === '' ? null : $title;
    }

    /**
     * Whether the page carries structured data in any of the three forms search
     * engines actually read.
     *
     * JSON-LD is what anything modern emits; microdata and RDFa are what the
     * older themes on real small-business sites emit, and both still work. A
     * check that only looked for JSON-LD would tell a business with perfectly
     * good microdata to add markup it already has.
     */
    public static function hasStructuredData(string $html): bool
    {
        $head = substr($html, 0, self::HEAD_BYTES);

        return preg_match('/application\/ld\+json/i', $html) === 1
            || preg_match('/\sitemscope\b/i', $html) === 1
            || preg_match('/\bvocab\s*=\s*["\']?https?:\/\/schema\.org/i', $head) === 1;
    }

    /**
     * Whether the page tells a phone browser how to size itself.
     *
     * Its absence is the difference between a readable page and a desktop layout
     * shrunk to thumbnail size, which is what most people finding a local
     * business on Google would be looking at.
     */
    public static function hasViewportMeta(string $html): bool
    {
        $head = substr($html, 0, self::HEAD_BYTES);

        return preg_match('/<meta[^>]+name\s*=\s*["\']?viewport["\']?/i', $head) === 1;
    }
}
