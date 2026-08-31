<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Indexing\Indexing;
use App\Services\Indexing\IndexingApi;

/**
 * Why this application declined to announce a URL — `indexing_submissions.reason`.
 *
 * ⛔ **OURS ONLY, NEVER THE ENGINE'S.** A vendor saying no is
 * {@see IndexingStatus::Rejected} with its own answer in `response`; this column
 * records a decision made on this side, before any request. Mixing the two would
 * produce a report in which *"we did not send this"* and *"Bing turned it down"*
 * render from the same field, and the second is the one that would get
 * investigated.
 *
 * ⚠️ **THIS ENUM IS THE POINT OF SLICE E RATHER THAN A CONSOLATION FOR IT.** Two
 * of the three delivery paths cannot transmit anything until the WordPress
 * plugin exists (5581), and the instruction that governs this slice is that such
 * an attempt is *"refused with a reason that is recorded, never dropped"*. A
 * silent skip and a working submitter are indistinguishable from the outside;
 * a row saying which one happened is the whole difference.
 *
 * Every case carries {@see self::staff()} — a sentence for the operator screen.
 * ⚠️ **NOT AN OWNER SENTENCE**: most of these name unbuilt machinery of ours,
 * and putting *"your site cannot host our key file"* in front of a business
 * owner is a support ticket about a thing they cannot act on.
 */
enum IndexingRefusal: string
{
    /**
     * No confirmed website, so there is no host to announce anything about.
     * Slice B's paste-and-confirm is what clears this.
     */
    case WebsiteNotConfirmed = 'website_not_confirmed';

    /**
     * There is no way to put a file on that server at all.
     *
     * ⛔ **THIS IS THE T3 ANSWER AND IT IS PERMANENT FOR T3.** The pixel runs
     * inside a visitor's browser; it cannot serve `/{key}.txt` from the tenant's
     * origin, and IndexNow verifies ownership by fetching exactly that. So a T3
     * or T4 location is not "waiting for the plugin" — it is waiting for a
     * WordPress connection, which is a different sentence.
     */
    case NoWriteAccessToTheHost = 'no_write_access_to_the_host';

    /**
     * A live WordPress connection exists and the key file still cannot be
     * served.
     *
     * ⛔ **DECISION 5581, VERIFIED AGAIN FOR THIS SLICE.** A REST media upload
     * lands under `/wp-content/uploads/`, and IndexNow binds a key to its own
     * path: *"A key file located at `http://example.com/catalog/key12457EDd.txt`
     * can include any URLs starting with `http://example.com/catalog/` but
     * cannot include URLs starting with `http://example.com/help/`"*
     * (`indexnow.org/documentation`, fetched 2026-08-20). A key in the uploads
     * directory can submit uploads and nothing else, which is why the vendor
     * *"strongly recommended"* the root. The root is F2's.
     */
    case KeyFileUnavailable = 'key_file_unavailable';

    /**
     * A key was provided and it is not a key the protocol accepts.
     *
     * ⛔ **REACHABLE ONLY FROM A PROVIDER THAT IS NOT BUILT YET, WHICH IS
     * PRECISELY WHY IT IS A CASE.** IndexNow answers a bad key with a 403
     * meaning *"key not valid"* — the same answer it gives when the key file is
     * not being served — so without this refusal, F2 shipping a malformed key
     * would look exactly like F2 not shipping a key file at all.
     */
    case MalformedKey = 'malformed_key';

    /**
     * The same URL was announced a moment ago.
     *
     * IndexNow's FAQ: *"Avoid submitting the same URL many times a day unless
     * there are meaningful content changes"*, and for frequently updated content
     * *"wait at least 5 minutes between updates before resubmitting"*. The
     * window is the vendor's own figure — see {@see Indexing::COOLDOWN_MINUTES}.
     */
    case RecentlyAnnounced = 'recently_announced';

    /**
     * We asked for the origin's `robots.txt` and got no usable answer, so
     * whether a sitemap is announced there is unknown.
     *
     * ⚠️ **UNKNOWN IS NOT ABSENT** (229). Reporting a fetch failure as *"no
     * sitemap"* would send an operator to fix something that may already be
     * fine, and would let a plugin later "add" a line that was always there.
     *
     * ⛔ **AND THIS WAS ALSO WHAT WE SAID WHEN *WE* WERE WHAT STOPPED US, WHICH
     * WAS A FACT ABOUT US RENDERED AS A FACT ABOUT SOMEBODY ELSE'S WEBSITE —
     * SPLIT 2026-08-25 (9740–9759, and raised unfixed at 6058(h)).** A kill
     * switch, a cool-down or a spent four-a-minute rate budget on the
     * `tenant_website` fetch source all arrived here, and this sentence sent an
     * operator to go and look at a `robots.txt` that was fine. Those are
     * {@see self::FetchNotAttempted} now, and a `Disallow` rule the origin
     * genuinely published is {@see self::RobotsDisallowedUs}. **What is left
     * here is the population the sentence was always true of**: a 5xx, a
     * timeout, a challenge page, a body too large to parse, a connection that
     * never opened.
     */
    case RobotsUnreadable = 'robots_unreadable';

    /**
     * Our own fetch policy stopped us before anything was asked of the origin.
     *
     * ⛔ **NOT A FACT ABOUT THE TENANT'S WEBSITE, AND THAT IS THE WHOLE POINT OF
     * THE CASE** (9740–9759). {@see FetchRefusalReason::isThisPlatformsOwnDoing()}
     * is the question that selects it: a kill switch, a `guided_only` ceiling, a
     * tier above the ceiling, a cool-down, or a spent rate budget. Every one of
     * the five is settled from our own configuration and our own ledger with
     * nothing about the origin consulted, so **no sentence carrying one of them
     * may name a file on the tenant's server.**
     *
     * ⚠️ **THE COMMONEST MEMBER IS THE RATE BUDGET AND IT IS PLATFORM-WIDE.**
     * `DirectFetchGateway::overRateBudget()` filters on `source_key` and nothing
     * else, and `SiteProbe`, `AuthorByline` and `SitemapAnnouncement` share one
     * bucket — so a busy minute somewhere else in the platform is what an
     * operator would otherwise have read as this tenant's site being unreadable.
     */
    case FetchNotAttempted = 'fetch_not_attempted';

    /**
     * The origin's own `robots.txt` was read, parsed, and disallows our crawler
     * from reading `robots.txt` itself — so no sitemap directive in it can be
     * seen.
     *
     * ⛔ **THE ONE REFUSAL HERE THAT MAY BE DESCRIBED AS THE SITE'S DOING**, and
     * the permission is {@see FetchRefusalReason::RobotsDisallow}'s own, not
     * this file's: *"THIS IS THE ONLY REASON ANYTHING MAY DESCRIBE TO A PERSON
     * AS 'YOUR WEBSITE IS TELLING US NOT TO'"*. A WAF's 403 is deliberately not
     * in this population — it arrives as
     * {@see FetchRefusalReason::RobotsUnavailable} by 9498(d), because a
     * firewall turning us away is not a robots directive and a sentence naming
     * `robots.txt` would be a false instruction.
     *
     * ⚠️ **IT IS NOT A FAULT AND MUST NOT READ AS ONE.** Nothing is broken, on
     * either side; the site has asked not to be crawled and we are honouring it.
     */
    case RobotsDisallowedUs = 'robots_disallowed_us';

    /**
     * `robots.txt` was read and names no sitemap, and this application cannot
     * write that line.
     *
     * ⛔ **WORDPRESS SERVES `robots.txt` VIRTUALLY THROUGH A FILTER AND NO REST
     * ROUTE WRITES IT** (5581) — so the fix is F2's, exactly as the key file is.
     */
    case SitemapNotAnnounced = 'sitemap_not_announced';

    /**
     * The row-9 gate: this URL carries neither `JobPosting` nor a
     * `BroadcastEvent` embedded in a `VideoObject`.
     *
     * ⛔ **GOOGLE'S RESTRICTION, QUOTED**: *"The Indexing API can only be used to
     * crawl pages with either JobPosting or BroadcastEvent embedded in a
     * VideoObject"*
     * (`developers.google.com/search/apis/indexing-api/v3/using-api`, last
     * updated 2026-07-16 UTC, fetched 2026-08-20). *"Our spam policies apply to
     * content submitted with the Indexing API"* is the sentence beside it, and
     * it is why this is a refusal rather than a best-effort attempt.
     */
    case NotEligibleForIndexingApi = 'not_eligible_for_indexing_api';

    /**
     * No Google service account is configured, so nothing can authenticate.
     * This is what {@see IndexingApi} answers on every deployment that exists.
     */
    case NoServiceAccount = 'no_service_account';

    /**
     * A credential exists and the request-making half does not.
     *
     * ⚠️ **REACHABLE ONLY WITH A PLANTED CREDENTIAL, WHICH IS THE ONLY REASON IT
     * IS A CASE AT ALL**: without it, the eligibility gate above could not be
     * driven with the credential arm green, and a gate whose outer guard always
     * refuses first is unfalsifiable (398).
     */
    case TransportUnbuilt = 'transport_unbuilt';

    /**
     * The line an operator reads.
     */
    public function staff(): string
    {
        return match ($this) {
            self::WebsiteNotConfirmed => 'No confirmed website address for this location.',
            self::NoWriteAccessToTheHost => 'We cannot put a file on this website, so IndexNow cannot verify it. Needs a WordPress connection.',
            self::KeyFileUnavailable => 'WordPress is connected, but nothing serves the IndexNow key file yet. Needs the plugin.',
            self::MalformedKey => 'The IndexNow key we hold for this site is not a key the protocol accepts. That is our defect, not the site\'s.',
            self::RecentlyAnnounced => 'This URL was announced within the last few minutes and was not sent again.',
            self::RobotsUnreadable => 'We asked this site for its robots.txt and got no usable answer, so we cannot say whether a sitemap is announced.',
            self::FetchNotAttempted => 'We did not look at this site at all — our own fetch policy stopped us first (a kill switch, a cool-down, or a spent rate budget). Nothing here is a fact about this website.',
            self::RobotsDisallowedUs => 'This site\'s robots.txt tells our crawler not to read it, so we cannot say whether a sitemap is announced. Nothing is broken; the site asked not to be crawled.',
            self::SitemapNotAnnounced => 'This site\'s robots.txt names no sitemap, and we cannot add the line ourselves. Needs the plugin.',
            self::NotEligibleForIndexingApi => 'Not a job posting or a livestream, so Google\'s Indexing API may not be used for it.',
            self::NoServiceAccount => 'No Google service account is configured for the Indexing API.',
            self::TransportUnbuilt => 'The Indexing API request path is not built.',
        };
    }
}
