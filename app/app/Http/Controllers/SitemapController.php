<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Industries\IndustryPages;
use Illuminate\Http\Response;

/**
 * The sitemap index and its two children — CC-3 §4, PIII-72 §A3.
 *
 * ## One column, two renders
 *
 * `industry_pages.index_mode` is read here and by the page's own `noindex` tag,
 * and by nothing else. That is the whole design: *"the flip changes one column;
 * the moment is atomic by construction."* A second flag, a config key or a
 * `where('published', …)` beside it would make the two surfaces capable of
 * disagreeing, which is the failure — a page telling a crawler not to index it
 * while the sitemap asks it to.
 *
 * ## Honest fields only
 *
 * PIII-73 §A3: *"lastmod = the row's real updated date · changefreq 'monthly' ·
 * priority flat. No games — Google ignores gamed values anyway; we don't write
 * fiction into XML."* So `lastmod` is `updated_at` and nothing else, and
 * **`priority` is omitted rather than written flat**: a `<priority>` identical on
 * every URL carries no information at all, and the default when it is absent is
 * the same number. Omitting is the honest spelling of "flat".
 *
 * ⚠️ **THE CORE SITEMAP OMITS `lastmod` ENTIRELY**, for the same reason. Those are
 * Blade templates whose "last modified" date is a deploy timestamp, not a fact
 * about the page — and a `lastmod` that moves on every deploy is precisely the
 * fiction this section refuses.
 *
 * ⚠️ **ALL THREE ROUTES SIT BEHIND `features.industry_pages`.** A sitemap is a
 * request to be crawled, and this application has never served one; publishing
 * the search surface is the same act as publishing the pages, so the flip does
 * both or neither.
 */
final class SitemapController extends Controller
{
    /**
     * The index — the only sitemap a robots.txt needs to name.
     */
    public function index(): Response
    {
        return $this->xml(view('sitemaps.index')->render());
    }

    /**
     * The public marketing pages that exist and may be listed.
     */
    public function core(): Response
    {
        return $this->xml(view('sitemaps.core')->render());
    }

    /**
     * The industry pages whose `index_mode` is true — and only those.
     */
    public function industries(IndustryPages $pages): Response
    {
        return $this->xml(view('sitemaps.industries', ['pages' => $pages->indexable()])->render());
    }

    private function xml(string $body): Response
    {
        // `application/xml` rather than `text/xml`: RFC 7303 §9.2 deprecates the
        // latter's implicit US-ASCII default, and the declaration below says
        // UTF-8. Industry nouns are ASCII today and the corpus is not.
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
