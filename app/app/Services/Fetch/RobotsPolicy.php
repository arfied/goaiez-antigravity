<?php

declare(strict_types=1);

namespace App\Services\Fetch;

use App\Enums\RobotsVerdict;
use App\Services\Config\DefaultsRegistry;
use App\Services\Content\AuthorByline;
use App\Services\Content\BylineCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Whether robots.txt permits us to fetch a URL.
 *
 * `40` §6.2: "robots.txt is respected at every tier, F2 included." Not a
 * courtesy and not configurable — the `fetch_sources.robots_respect` column
 * carries a CHECK constraint pinning it true, because the difference between
 * "we model this" and "this is negotiable" is exactly what erodes.
 *
 * HAND-ROLLED RATHER THAN A PACKAGE, and it is worth saying why: `40` §6.1 names
 * spatie/crawler for F0, which would bring a robots parser with it. CLAUDE.md
 * requires approval before adding a dependency, and F0 here is one HTTP request
 * — a crawler package would be a large dependency for its robots parser alone.
 * If F1 arrives and Browsershot comes with it, revisit.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO. No wildcard globbing beyond `*` and `$`,
 * no crawl-delay, no sitemap discovery. The subset implemented is the subset
 * that decides "may we fetch this URL", and anything unparsed **fails closed**:
 * a robots.txt we cannot understand is treated as a disallow, not as silence.
 * That is the opposite of most parsers, and it is the right way round for a
 * rule we are promising to honour rather than trying to satisfy minimally.
 *
 * ## Failing closed is not the same as knowing why, and this class conflated
 * them until 2026-08-25
 *
 * ⛔ **IT ANSWERED ONE `false` FOR *"THEY SAY NO"* AND *"WE COULD NOT ASK"*, AND
 * THE SECOND WAS THEN QUOTED TO AN OWNER AS THE FIRST** (9480–9499). A timeout,
 * a DNS failure, a 500, a WAF's 403 or a robots.txt over
 * {@see self::MAX_BYTES} all produced a blanket disallow indistinguishable from
 * a real `Disallow: /` — and the live reader of that distinction is
 * {@see BylineCheck::$ownRobotsRefused}, which decides
 * whether {@see AuthorByline::unreachableAboutPageSentence()}
 * tells a small business *"Your website is telling us not to read your About
 * page … The setting is in a file called robots.txt on your website. Whoever
 * looks after your site can let us in"*, and files it as an owner action item.
 * **On a five-second outage at their host, every clause of that sentence is
 * false and it sends them to their web developer to edit a rule that does not
 * exist.** {@see self::verdict()} is what tells the two apart.
 *
 * ⚠️ **THE EVIDENCE THAT IT FIRES WAS ALREADY IN THE TREE, THREE TIMES.**
 * `AppServiceProvider::forbidLiveVendorCallsInTests()` records that of three
 * stray requests measured across the whole suite, *"`RobotsPolicy`'s
 * `catch (Throwable)` swallows the other two into a fail-closed robots
 * answer"*; and `PublishingTest`, `AuthorBylineTest` and
 * `tests/Support/actuation_helpers.php` each carry a comment warning the next
 * author that a missing robots stub *"arrives at the pipeline as
 * `NoAuthorByline` and reads exactly like the gate working"*. Three authors
 * were bitten by this before it was ever a finding.
 */
final class RobotsPolicy
{
    /**
     * `40` §6.1's honest identity. A real URL, so anyone reading their logs can
     * find out who we are and how to stop us.
     *
     * ⚠️ THE `+URL` IS A PROMISE WITH A PAGE BEHIND IT, and it was a promise
     * with nothing behind it for two days. `BotController` serves it and
     * `BotPageTest` parses this constant, extracts the URL, and asserts the path
     * resolves — so editing this string to point somewhere that does not exist
     * fails the build instead of quietly telling every webmaster we fetch from
     * to go and read a 404.
     */
    public const string USER_AGENT = 'GoAiEzBot/1.0 (+https://goaiez.com/bot)';

    /**
     * The token we match ourselves against in robots.txt.
     *
     * ⚠️ PUBLIC BECAUSE IT IS THE ONE THING A WEBMASTER COPIES. `/bot` tells
     * people what to write in their `User-agent:` line, and a page that names a
     * different token than the parser matches hands out a disallow rule that
     * silently applies to nobody. `tests/Feature/BotPageTest.php`'s *"the token
     * the page prints is the token the parser matches, case aside"* pins this
     * against `userAgentToken()`, which is derived from `USER_AGENT` — so
     * renaming the product in one place and not the other reddens rather than
     * producing a bot that ignores its own group. ⛔ **THIS CITED a
     * `RobotsPolicyTest` AND NO FILE OF THAT NAME HAS EVER EXISTED — CORRECTED
     * 2026-08-25 (9663).**
     */
    public const string UA_TOKEN = 'goaiezbot';

    /**
     * robots.txt rarely changes and re-fetching it per URL would double every
     * crawl. An hour is short enough to honour a newly-added disallow quickly.
     *
     * ⚠️ **THIS IS THE TTL OF A JUDGEMENT AND OF NOTHING ELSE.** See
     * {@see self::UNAVAILABLE_CACHE_SECONDS} for the other one.
     */
    public const int CACHE_SECONDS = 3600;

    /**
     * How long a **non**-judgement is remembered (9480–9499).
     *
     * ⛔ **CACHING "WE COULD NOT ASK" FOR AN HOUR TURNS A FIVE-SECOND BLIP INTO
     * AN HOUR IN WHICH A WHOLE ORIGIN IS REFUSED**, and every consequence of
     * that hour — a publish that does not happen, an owner action item naming
     * their robots.txt — outlives the fault by three orders of magnitude. An
     * hour is the right window for a **rule**, because rules rarely change; it
     * is the wrong window for an **outage**, because outages end.
     *
     * ⚠️ **THE FIGURE IS DERIVED FROM THE POLITENESS BUDGET THAT ALREADY EXISTS
     * RATHER THAN CHOSEN.** The floor on how often we may re-ask a struggling
     * origin is set by `fetch_sources.rate_budget`, seeded at six fetches a
     * minute for `subject_website` and four for `tenant_website` — and that
     * budget is per *source*, shared across every origin it covers, so one
     * robots request per origin per minute cannot be the thing that overwhelms
     * anybody. What the property must be, and what
     * `tests/Feature/FetchGatewayTest.php`'s *"a robots.txt outage is remembered
     * for minutes where a rule is remembered for an hour"* pins, is that this is
     * **shorter than {@see self::CACHE_SECONDS}**; the number itself is an
     * engineering figure and not a ruling.
     *
     * ⛔ **THIS WAS A `{@see FetchGatewayTest}` AND THE CLASS WAS IMPORTED AT THE
     * TOP OF THIS FILE TO MAKE IT RESOLVE — REMOVED 2026-08-25 (9663).**
     * `Tests\` is autoloaded under `autoload-dev` and the production checkout
     * installs `--no-dev`, so the one `use Tests\…` statement in `app/` named a
     * class that does not exist where this code runs. It was never dereferenced,
     * so nothing broke; a docblock is not worth the import either way.
     */
    public const int UNAVAILABLE_CACHE_SECONDS = 60;

    private function cacheSeconds(): int
    {
        return app(DefaultsRegistry::class)->int('fetch.robots.cache_seconds');
    }

    private function unavailableCacheSeconds(): int
    {
        return app(DefaultsRegistry::class)->int('fetch.robots.unavailable_cache_seconds');
    }

    /**
     * A robots.txt larger than this is not parsed, and therefore disallows.
     * Fails closed, per the class docblock.
     */
    private const int MAX_BYTES = 512_000;

    /**
     * The product token as a webmaster should write it, derived from
     * `USER_AGENT` rather than typed a second time.
     *
     * `GoAiEzBot/1.0 (+…)` → `GoAiEzBot`. RFC 9110 gives a User-Agent header a
     * leading product token before the first `/`, which is also what every
     * robots.txt matcher keys on, so this is the header's own structure rather
     * than a convention of ours.
     */
    public static function userAgentToken(): string
    {
        $product = strstr(self::USER_AGENT, '/', true);

        return $product === false ? self::USER_AGENT : $product;
    }

    /**
     * What this origin's `robots.txt` establishes about one URL.
     *
     * ⛔ **THREE ANSWERS AND NOT TWO** — see {@see RobotsVerdict} and this
     * class's own docblock. {@see RobotsVerdict::Unavailable} still refuses the
     * fetch; it refuses it **without attributing the refusal to the origin**,
     * which is the whole of the change and the only thing downstream may read
     * differently.
     */
    public function verdict(string $url): RobotsVerdict
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            // ⚠️ **A URL WE CANNOT TAKE APART IS OURS OR THE ROW'S, NEVER THE
            // ORIGIN'S RULE.** No request was made, so nothing was established
            // — which is exactly what `Unavailable` means. Reporting this as a
            // disallow would tell an owner with a malformed `about_url` that
            // their robots.txt was turning us away.
            return RobotsVerdict::Unavailable;
        }

        $origin = $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '');

        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        $judged = $this->rulesFor($origin);

        if (! $judged['judged']) {
            return RobotsVerdict::Unavailable;
        }

        // A host with no robots.txt permits everything — that is what a 404
        // means, and it is the one place this class opens rather than closes,
        // because the standard says so.
        if ($judged['rules'] === null) {
            return RobotsVerdict::Allowed;
        }

        return $this->pathAllowed($path, $judged['rules'])
            ? RobotsVerdict::Allowed
            : RobotsVerdict::Disallowed;
    }

    /**
     * The cached reading of one origin's `robots.txt`.
     *
     * ⚠️ **`judged` IS A SEPARATE FIELD RATHER THAN A THIRD SHAPE OF `rules`,
     * BECAUSE THE TWO FALSE-LOOKING CASES ARE OPPOSITES.** `rules === null`
     * means *"there is no robots.txt, so everything is permitted"* and
     * `judged === false` means *"we do not know, so nothing is"* — collapsing
     * them into one nullable is how this class came to have the defect above.
     *
     * ⛔ **AND `Cache::remember()` COULD NOT HOLD THIS, WHICH IS WHY IT IS GONE.**
     * It treats a null return as a miss, so the commonest outcome on the open
     * web — *no robots.txt at all* — was **never cached** and was re-fetched for
     * every URL on the origin; and it takes one TTL where these outcomes need
     * two. Both are fixed by storing an array under an explicit `put`.
     *
     * @return array{judged: bool, rules: array{allow: list<string>, disallow: list<string>}|null}
     */
    private function rulesFor(string $origin): array
    {
        $key = 'robots:'.sha1($origin);

        /** @var array{judged: bool, rules: array{allow: list<string>, disallow: list<string>}|null}|null $cached */
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $reading = $this->read($origin);

        Cache::put(
            $key,
            $reading,
            $reading['judged'] ? $this->cacheSeconds() : $this->unavailableCacheSeconds(),
        );

        return $reading;
    }

    /**
     * One request for one origin's `robots.txt`.
     *
     * ⚠️ **EVERY ARM THAT IS NOT A PARSED FILE OR A 404 IS `judged => false`.**
     * A transport failure, a 5xx, a 403 from a bot-blocking proxy and a file
     * over {@see self::MAX_BYTES} are four different faults and none of them is
     * a rule the origin wrote. The gateway refuses on all four exactly as it did
     * before; what it may no longer do is call any of them the origin's doing.
     *
     * @return array{judged: bool, rules: array{allow: list<string>, disallow: list<string>}|null}
     */
    private function read(string $origin): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(5)
                ->get($origin.'/robots.txt');
        } catch (\Throwable) {
            return ['judged' => false, 'rules' => null];
        }

        if ($response->status() === 404 || $response->status() === 410) {
            return ['judged' => true, 'rules' => null];
        }

        if (! $response->successful() || strlen($response->body()) > self::MAX_BYTES) {
            return ['judged' => false, 'rules' => null];
        }

        return ['judged' => true, 'rules' => $this->parse($response->body())];
    }

    /**
     * Parse the groups that apply to us: our own token, then `*`.
     *
     * A group naming us specifically wins outright — the standard says the most
     * specific matching group applies and the others are ignored, so a site that
     * allows `*` and disallows `GoAiEzBot` must not get the union.
     *
     * @return array{allow: list<string>, disallow: list<string>}
     */
    private function parse(string $body): array
    {
        $groups = [];
        $currentAgents = [];
        $lastLineWasAgent = false;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                // Consecutive User-agent lines share one group.
                if (! $lastLineWasAgent) {
                    $currentAgents = [];
                }

                $currentAgents[] = strtolower($value);
                $lastLineWasAgent = true;

                continue;
            }

            $lastLineWasAgent = false;

            if ($field !== 'allow' && $field !== 'disallow') {
                continue;
            }

            foreach ($currentAgents as $agent) {
                $groups[$agent][$field][] = $value;
            }
        }

        $applicable = $groups[self::UA_TOKEN] ?? $groups['*'] ?? [];

        return [
            'allow' => array_values(array_filter($applicable['allow'] ?? [], 'is_string')),
            'disallow' => array_values(array_filter($applicable['disallow'] ?? [], 'is_string')),
        ];
    }

    /**
     * Longest-match wins, and Allow beats Disallow at equal length — the
     * behaviour Google's own parser documents.
     *
     * @param  array{allow: list<string>, disallow: list<string>}  $rules
     */
    private function pathAllowed(string $path, array $rules): bool
    {
        $bestAllow = -1;
        $bestDisallow = -1;

        foreach ($rules['allow'] as $pattern) {
            if ($this->matches($path, $pattern)) {
                $bestAllow = max($bestAllow, strlen($pattern));
            }
        }

        foreach ($rules['disallow'] as $pattern) {
            // "Disallow:" with an empty value means allow everything, and must
            // not be treated as a zero-length match on "/".
            if ($pattern === '') {
                continue;
            }

            if ($this->matches($path, $pattern)) {
                $bestDisallow = max($bestDisallow, strlen($pattern));
            }
        }

        return $bestAllow >= $bestDisallow;
    }

    /**
     * Prefix match, with `*` as any-run and `$` as end-anchor.
     */
    private function matches(string $path, string $pattern): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $body = $anchored ? substr($pattern, 0, -1) : $pattern;

        $regex = '#^'.str_replace('\*', '.*', preg_quote($body, '#')).($anchored ? '$' : '').'#';

        return (bool) preg_match($regex, $path);
    }
}
