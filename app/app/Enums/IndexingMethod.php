<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a URL was announced — `indexing_submissions.method`.
 *
 * ⛔ **THE COLUMN'S COMMENT ANTICIPATED THREE VALUES — `indexing_api|sitemap|manual`
 * — AND THE BUILT SET IS THREE DIFFERENT ONES** (5682). `indexnow` is the one
 * the Stage 0 comment did not foresee, because decision 48 routes four search
 * engines through a protocol that is neither an API of Google's nor a sitemap.
 *
 * ⚠️ **`manual` IS DELIBERATELY NOT DECLARED.** Nothing writes it and nothing
 * would: the manual route is a person pasting a sitemap into Search Console in a
 * browser, which decision 5480 makes *"a browser act by construction"* and which
 * this application cannot observe. A case with no writer is anticipated
 * vocabulary — `CLAUDE.md`'s 272 — and the one thing worse than not having it is
 * a report that renders a state nothing can reach.
 */
enum IndexingMethod: string
{
    /**
     * The IndexNow protocol: a POST to one participating endpoint, shared with
     * all of them. Requires a key file on the tenant's own host, which is why
     * this method is plugin-bound (5581).
     */
    case IndexNow = 'indexnow';

    /**
     * A `Sitemap:` line in the site's `robots.txt` — one of the three ways
     * Google documents for making a sitemap available to it, and the only one
     * reachable without a Search Console write scope (§2.11.5 conflict 2).
     */
    case Sitemap = 'sitemap';

    /**
     * Google's Indexing API. ⛔ **Job postings and livestream videos only** — see
     * {@see PageMarkup} and the guard in `App\Services\Indexing\IndexingApi`.
     */
    case IndexingApi = 'indexing_api';
}
