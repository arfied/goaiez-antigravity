<?php

declare(strict_types=1);

namespace App\Services\Indexing;

use App\Contracts\FetchGateway;
use App\Enums\FetchRefusalReason;
use App\Enums\IndexingEngine;
use App\Enums\IndexingMethod;
use App\Enums\IndexingRefusal;
use App\Models\Location;
use App\Services\Actuation\SiteProbe;
use App\Services\Content\AuthorByline;
use App\Services\Fetch\FetchResult;

/**
 * Google's sitemap route, checked rather than claimed: does this site's
 * `robots.txt` name a sitemap?
 *
 * ## Why `robots.txt` is the submission and not a workaround
 *
 * ⛔ **GOOGLE PUBLISHES THREE WAYS TO SUBMIT A SITEMAP AND THIS IS ONE OF
 * THEM** — the Search Console report, the Search Console API, and *"Insert the
 * following line anywhere in your robots.txt file, specifying the path to your
 * sitemap. We will find it the next time we crawl your robots.txt file:
 * `Sitemap: https://example.com/my_sitemap.xml`"*
 * (`developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap`,
 * fetched 2026-08-20; §2.11.5 conflict 2 read the same page on 2026-08-19).
 * ⛔ **THE SECOND OF THE THREE IS THE ONE THIS APPLICATION MAY NOT USE**:
 * decision 1083 scoped `GoogleSearchConsoleClient` to the read-only grant
 * *because* the write scope additionally permits deleting a tenant's property,
 * and 5480 says no future slice should look for the submission code path. It is
 * not looked for here.
 *
 * ⚠️ **AND GOOGLE ITSELF CALLS THE WHOLE THING A HINT**: *"submitting a sitemap
 * is merely a hint: it doesn't guarantee that Google will download the sitemap
 * or use the sitemap for crawling URLs on the site"*. Nothing this class writes
 * may be reported as *indexed*.
 *
 * ## The finding that makes this half of slice E live rather than dark
 *
 * ✅ **WORDPRESS ALREADY DOES IT, AND `BUILD-PLAN` §2.11.3 DID NOT KNOW THAT**
 * (5683). The plan's E row says *"sitemap handling on T1 = ensure WP's native
 * sitemap is named in `robots.txt` via the plugin"*, which reads as work owed on
 * every WordPress site. Core has shipped it since 5.5: *"WordPress will expose a
 * sitemap index at `/wp-sitemap.xml`"* and *"The robots.txt file exposed by
 * WordPress will reference the sitemap index so that i can be easily discovered
 * by search engines"* — the typo is the source's
 * (`make.wordpress.org/core/2020/07/22/new-xml-sitemaps-functionality-in-wordpress-5-5/`,
 * published 2020-07-22, fetched 2026-08-20). So on an ordinary WordPress site
 * the submission route is **already in place**, and what F2 owes is the
 * exception — a site whose owner discouraged indexing, or whose SEO plugin
 * replaced the line — rather than the rule.
 *
 * ⛔ **WHICH IS WHY THIS CLASS LOOKS INSTEAD OF ASSUMING.** Recording
 * *"refused, needs the plugin"* on a site that already announces its sitemap
 * would be a false refusal, and it would send F2 to build for a case that mostly
 * does not exist.
 *
 * ## The fetch
 *
 * ⚠️ **THROUGH THE GATEWAY, ON SLICE B's SOURCE.** `40` Part 6's rule — no
 * module fetches on its own — is held by a lint, and going through
 * {@see FetchGateway} is also what buys robots compliance, the per-source rate
 * budget, the kill switch and a `fetch_attempts` row for every one of these.
 * ⚠️ **A SITE WHOSE `robots.txt` DISALLOWS US CANNOT HAVE ITS `robots.txt` READ,
 * AND THAT IS THE RIGHT ANSWER** rather than an edge case to special-case: the
 * refusal says *unknown*, and never *absent*.
 * ⛔ **THAT SENTENCE NAMED {@see IndexingRefusal::RobotsUnreadable} UNTIL
 * 2026-08-25 AND THE ANSWER IS NOW {@see IndexingRefusal::RobotsDisallowedUs}**
 * (9740–9759). *Unknown* was true and it was all this class could say: **it
 * recorded the same refusal for our own kill switch, our own cool-down and our
 * own spent rate budget**, under a staff sentence claiming we could not read the
 * tenant's file — our brake, reported as their file, with the operator's next
 * action being to go and look at a `robots.txt` that was fine. That was raised
 * as 6058(h) on 2026-08-20 and left standing for five days.
 * {@see self::whyItWasNotRead()} is the three-way answer.
 */
final class SitemapAnnouncement
{
    public function __construct(private readonly FetchGateway $gateway) {}

    /**
     * Whether Google's `robots.txt` route is open for this location's site.
     */
    public function check(Location $location): IndexingAttempt
    {
        $url = $location->website_url;

        if ($url === null || $location->website_confirmed_at === null) {
            return IndexingAttempt::refused(
                IndexingEngine::Google,
                IndexingMethod::Sitemap,
                IndexingRefusal::WebsiteNotConfirmed,
            );
        }

        $robots = $this->robotsUrl($url);

        if ($robots === null) {
            return IndexingAttempt::refused(
                IndexingEngine::Google,
                IndexingMethod::Sitemap,
                IndexingRefusal::RobotsUnreadable,
            );
        }

        $result = $this->gateway->fetch(SiteProbe::SOURCE, $robots);

        if (! $result->successful() || $result->body === null) {
            return IndexingAttempt::refused(
                IndexingEngine::Google,
                IndexingMethod::Sitemap,
                self::whyItWasNotRead($result),
            );
        }

        $sitemaps = self::sitemapsIn($result->body);

        if ($sitemaps === []) {
            return IndexingAttempt::refused(
                IndexingEngine::Google,
                IndexingMethod::Sitemap,
                IndexingRefusal::SitemapNotAnnounced,
            );
        }

        // ⛔ IN PLACE, NOT SUBMITTED. We did not write this line and saying we
        // did would be the feed telling an owner about work nobody performed —
        // `PublishOutcome::handedOff()`'s rule, one table over.
        return IndexingAttempt::inPlace(IndexingEngine::Google, IndexingMethod::Sitemap, [
            'robots_url' => $robots,
            'sitemaps' => $sitemaps,
        ]);
    }

    /**
     * Whose doing it was that this site's `robots.txt` never got read.
     *
     * ⛔ **THREE ANSWERS, BECAUSE THE ENUM ON THE OTHER SIDE HAS THREE AND THIS
     * METHOD USED TO HAVE ONE** (9740–9759, and raised unfixed at 6058(h)).
     * Every failure mode collapsed into {@see IndexingRefusal::RobotsUnreadable},
     * whose sentence — *"We could not read this site's robots.txt"* — is a claim
     * about the tenant's server. Our kill switch said it. Our cool-down said it.
     * Our four-a-minute rate budget, shared platform-wide with `SiteProbe` and
     * `AuthorByline`, said it. **The operator's next action was to go and look at
     * a file that was fine**, which is worse than telling them nothing.
     *
     * ⚠️ **`namesTheOriginsOwnRule()` ALONE DOES NOT TRANSFER FROM
     * {@see AuthorByline::check()}, WHICH IS THE PRECEDENT ONE SERVICE OVER.**
     * That caller is deciding a single yes/no — may we tell an owner their own
     * file turned us away — so a two-way partition is the whole of its question.
     * This one is deciding *whose doing was it*, and a `false` from that method
     * covers our own kill switch **and** an outage at their host. Asking it
     * alone would move the five brakes into the outage bucket and leave the
     * sentence just as wrong. {@see FetchRefusalReason::isThisPlatformsOwnDoing()}
     * is the other half.
     *
     * ⚠️ **A NULL REASON IS A FETCH THAT HAPPENED AND FAILED** — a 5xx, a
     * timeout, a challenge page, an over-size body — and that is the population
     * `RobotsUnreadable` was always honest about, so it keeps it.
     */
    private static function whyItWasNotRead(FetchResult $result): IndexingRefusal
    {
        $reason = $result->refusalReason;

        if ($reason === null) {
            return IndexingRefusal::RobotsUnreadable;
        }

        if ($reason->isThisPlatformsOwnDoing()) {
            return IndexingRefusal::FetchNotAttempted;
        }

        return $reason->namesTheOriginsOwnRule()
            ? IndexingRefusal::RobotsDisallowedUs
            : IndexingRefusal::RobotsUnreadable;
    }

    /**
     * `robots.txt` sits at the root of a host and nowhere else, so a site in a
     * subdirectory still has its file at the origin.
     *
     * ⚠️ **A NULL FROM HERE STAYS {@see IndexingRefusal::RobotsUnreadable} AND
     * IS NOT {@see IndexingRefusal::FetchNotAttempted}** (9740–9759). It is not
     * our fetch policy that stopped us — no gate was reached — it is a stored
     * address we cannot derive an origin from, and `LocationWebsite::normalise()`
     * refuses every shape that produces it, so the supported writer cannot make
     * one. Minting a third sentence for an arm with no reachable population is a
     * case nothing can drive, which is the inverse of the defect above.
     */
    private function robotsUrl(string $websiteUrl): ?string
    {
        $parts = parse_url($websiteUrl);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port.'/robots.txt';
    }

    /**
     * Every sitemap named in a `robots.txt`.
     *
     * ⚠️ **THE DIRECTIVE IS GROUP-INDEPENDENT AND CASE-INSENSITIVE**, which is
     * why this does not reuse `RobotsPolicy`'s parser: that one tracks
     * `User-agent` groups because allow and disallow belong to a group, and
     * `Sitemap` deliberately does not — Google's wording is *"anywhere in your
     * robots.txt file"*. Threading a group-scoped parser through a
     * group-independent directive is how a sitemap under somebody else's
     * `User-agent` block gets missed.
     *
     * @return list<string>
     */
    public static function sitemapsIn(string $robots): array
    {
        $found = [];

        foreach (preg_split('/\R/', $robots) ?: [] as $line) {
            // ⛔ **`explode`, NOT `strtok`.** `strtok()` skips leading
            // delimiters, so a fully commented-out line — `# Sitemap: …`, which
            // is exactly how somebody disables one — comes back as a live
            // directive. Found by the fixture that plants one.
            $line = trim(explode('#', $line, 2)[0]);

            if (preg_match('/^sitemap\s*:\s*(\S+)$/i', $line, $matches) !== 1) {
                continue;
            }

            $found[$matches[1]] = true;
        }

        return array_keys($found);
    }
}
