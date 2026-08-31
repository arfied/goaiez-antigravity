<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use App\Support\HashedIp;
use Illuminate\Http\Request;

/**
 * §11 row 8, and the one function in `App\Services\Pixel` permitted to read a
 * raw address — decision 5000s.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 8: *"Enrich: GeoLite2 geo/ASN, UA +
 * client hints, `ip_hash`, discard raw IP in the same function scope."* This
 * class is the whole of that step. `PixelCollector` calls
 * {@see self::fromRequest()} once per received batch and carries only its
 * result forward — never the request, never the address.
 *
 * ---------------------------------------------------------------------------
 * ⛔ "DISCARD THE RAW IP IN THE SAME FUNCTION SCOPE", TAKEN LITERALLY
 * ---------------------------------------------------------------------------
 * `$request->ip()` is read exactly once, inside {@see self::fromRequest()},
 * into a local variable that is hashed on the next line and never returned,
 * stored on this object, or passed to anything else. `App\Support\HashedIp` is
 * reused rather than reinvented — its own docblock names this exact use case:
 * *"a keyed hash answers 'is this the same visitor as a moment ago?' and
 * answers nothing else"*. **`public_audits.ip_hash` and
 * `magic_link_tokens.requested_ip_hash`** already store this construction for
 * longer than L1's 400-day retention, so nothing here is a new class of risk.
 *
 * ⛔ **THAT SECOND COLUMN NAME WAS `magic_link_tokens.ip_hash` UNTIL
 * 2026-08-22 AND NO SUCH COLUMN HAS EVER EXISTED** — the creating migration
 * writes `requested_ip_hash`, and `ip_hash` is `public_audits`'. Harmless to
 * the argument and not harmless to a reader: **this is the sentence somebody
 * greps to find out whether the two tables' hashes are joinable**, and they
 * are, because both are `HashedIp::hash()`. ⚠️ **`App\Support\HashedIp`'s own
 * docblock carries the same slip** — *"the `ip_hash` on `public_audits` and on
 * `magic_link_tokens`"* — and is another lane's file; it is reported rather
 * than edited, which is also why the quotation above now stops before it.
 *
 * ⚠️ **TWO LINTS IN `Architecture/PixelTest` BOUND THIS FILE, AND THE SECOND
 * ONE EXISTS BECAUSE THIS PARAGRAPH ONCE CLAIMED IT DID WHEN IT DID NOT.**
 * *"The collector never touches a raw address"* keeps its five ingest files
 * (`PixelCollector`, `PixelKeys`, `ArchivePixelBatchJob`,
 * `PixelIngestController`, `StorePixelBatchRequest`) unable to read one at all.
 * *"`PixelEnrichment` is the only file in the pixel service that reads an
 * address, and it discards it in the same scope"* covers this one: it is the
 * sole exception in `App\Services\Pixel`, `$request->ip()` appears **exactly
 * once**, the value is never assigned to a property and never returned. ⛔ **The
 * earlier wording here described the exception as already pinned while the lint
 * had never heard of this file** — `CLAUDE.md` 314–316, caught inside the slice
 * that wrote it, which is the outcome that paragraph predicts.
 *
 * ---------------------------------------------------------------------------
 * UA CLASSIFICATION — A CLOSED VOCABULARY, NEVER THE RAW STRING
 * ---------------------------------------------------------------------------
 * §5.3 types `browser`/`os` `LowCardinality(String)` — a small, closed-ish
 * vocabulary, not a place for a raw `User-Agent` header to land. Storing the
 * raw string would be a step toward the fingerprinting surface `29` §2
 * forbids on the client; the classified family is not one, on the same
 * reasoning `pixel.js`'s device signals rely on — informative in aggregate,
 * never assembled into an identifier, and this class assembles nothing: each
 * classifier returns one bucketed string and nothing joins it to anything
 * else.
 *
 * ⚠️ **NO NEW DEPENDENCY.** A User-Agent parsing library was deliberately not
 * added — `CLAUDE.md`'s *"Don't add dependencies or new base folders without
 * approval"* rule — because the vocabulary
 * this needs is small and the browsers/operating systems that matter for
 * device-class bucketing are a handful of well-known substrings. This is not
 * a general-purpose UA parser and does not try to be one.
 */
final readonly class PixelEnrichment
{
    /**
     * @param  string|null  $ipHash  A keyed HMAC, or null when the request
     *                               carried no resolvable client address at
     *                               all — a console call or certain proxy
     *                               configurations. Never the address.
     * @param  string  $browser  `chrome`, `safari`, `firefox`, `edge`,
     *                           `opera`, `bot`, or `unknown`.
     * @param  string|null  $browserVersion  The major.minor the User-Agent
     *                                       claims, or null when `$browser`
     *                                       is `bot`/`unknown` or the string
     *                                       carried no parseable version.
     * @param  string  $os  `windows`, `macos`, `linux`, `android`, `ios`,
     *                      `chromeos`, or `unknown`.
     */
    public function __construct(
        public ?string $ipHash,
        public string $browser,
        public ?string $browserVersion,
        public string $os,
    ) {}

    /**
     * Compute enrichment for one HTTP request. See the class docblock for why
     * this is the only place in `App\Services\Pixel` permitted to read
     * `$request->ip()`.
     */
    public static function fromRequest(Request $request): self
    {
        // ⚠️ THE ONE READ, HASHED ON THE NEXT LINE, NEVER RETURNED. `$ip` goes
        // out of scope when this method returns and nothing below reads it a
        // second time.
        $ip = $request->ip();
        $ipHash = $ip === null ? null : HashedIp::hash($ip);

        $ua = (string) $request->userAgent();

        [$browser, $browserVersion] = self::classifyBrowser($ua);

        return new self($ipHash, $browser, $browserVersion, self::classifyOs($ua));
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private static function classifyBrowser(string $ua): array
    {
        if ($ua === '') {
            return ['unknown', null];
        }

        // ⚠️ CHECKED FIRST AND ON PURPOSE. A crawler's own User-Agent routinely
        // *also* contains "Chrome" or "Safari" to get better-formatted pages
        // ("Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/
        // bot.html)" is one of the few that does not), so bot detection has to
        // run before the browser families below or a crawler is misclassified
        // as a visitor.
        //
        // ⚠️ **ONE LINK-PREVIEW FETCHER IS DELIBERATELY NOT DETECTED, AND THE
        // REASON IS AN ARCHITECTURE LINT RATHER THAN AN OVERSIGHT.** The messaging
        // app whose crawler identifies itself only by its own product name cannot
        // be named here: `Architecture/ReviewsTest`'s "never offered on an
        // owner-facing surface" lint sweeps all of `app/` for that name in
        // comment-stripped code, allows exactly three consent-lane files, and its
        // own docblock says *"a fourth entry wanting in is a finding, not a false
        // positive"*. Adding one to bucket a User-Agent would be 511's failure —
        // widening a compliance lint until it stops catching things — so the
        // classifier takes the smaller loss instead: those fetches bucket as
        // `unknown` rather than `bot`. ⚠️ **NOTHING COMPLIANCE-SHAPED RIDES ON
        // THIS FIELD.** §12's bot *scoring* is `L1Derivation`'s and is where a
        // wrong answer would matter; this is an aggregate UA family bucket, and
        // §11 row 6's rule is "flag, never drop" in either case.
        //
        // `telegrambot` is likewise absent because it is redundant — it contains
        // `bot` and the first alternative already matches it.
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $ua) === 1) {
            return ['bot', null];
        }

        // ⚠️ ORDER IS THE WHOLE OF THIS FUNCTION. Edge's UA contains "Chrome"
        // and "Safari"; Chrome's contains "Safari"; Opera's contains "Chrome"
        // and "Safari" too. Each family is checked for its own unique token
        // before the family it is disguised as.
        $families = [
            'edge' => '/Edg(?:A|iOS)?\/([\d.]+)/',
            'opera' => '/(?:OPR|Opera)\/([\d.]+)/',
            'firefox' => '/Firefox\/([\d.]+)/',
            // Chrome on iOS identifies as `CriOS/`, never `Chrome/` — Apple's
            // WebKit policy forbids a non-Safari engine, so every iOS browser
            // is Safari underneath and spells its own name differently.
            'chrome' => '/(?:Chrome|CriOS)\/([\d.]+)/',
            // Real Safari carries both `Safari/` and `Version/`; Chrome and
            // Firefox both carry `Safari/` alone (a legacy compatibility
            // token), so `Version/` is what tells them apart.
            'safari' => '/Version\/([\d.]+).*Safari\//',
        ];

        foreach ($families as $name => $pattern) {
            if (preg_match($pattern, $ua, $match) === 1) {
                return [$name, self::majorMinor($match[1])];
            }
        }

        return ['unknown', null];
    }

    private static function classifyOs(string $ua): string
    {
        if ($ua === '') {
            return 'unknown';
        }

        // ⚠️ ORDER MATTERS HERE TOO. An iPhone's UA contains "like Mac OS X"
        // and an Android UA contains "Linux", so both mobile families are
        // checked before the desktop ones they would otherwise match.
        return match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'ios',
            (bool) preg_match('/Android/', $ua) => 'android',
            (bool) preg_match('/CrOS/', $ua) => 'chromeos',
            (bool) preg_match('/Windows NT/', $ua) => 'windows',
            (bool) preg_match('/Mac OS X/', $ua) => 'macos',
            (bool) preg_match('/Linux/', $ua) => 'linux',
            default => 'unknown',
        };
    }

    /**
     * `128.0.0.0` becomes `128.0` — enough to group by release without
     * carrying a build number nobody aggregates on.
     *
     * ⚠️ **NON-NULL BY CONSTRUCTION, AND THE CALLER IS WHERE `null` COMES FROM.**
     * Every caller passes a `([\d.]+)` capture, which cannot be empty, so this
     * can never answer `null` and a `?string` return would be an arm no input
     * reaches — 256's vacuity wearing a type. `browser_version` is nullable
     * because the `bot` and `unknown` arms of {@see self::classifyBrowser()}
     * return `null` without calling this at all.
     */
    private static function majorMinor(string $version): string
    {
        return implode('.', array_slice(explode('.', $version), 0, 2));
    }
}
