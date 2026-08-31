<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Industries\IndustryPages;
use Illuminate\Http\Response;

/**
 * `/robots.txt` — served by the application rather than sat in `public/`.
 *
 * ⛔ **IT WAS A STATIC FILE UNTIL NOW, AND THAT IS EXACTLY HOW IT WENT STALE.**
 * `public/robots.txt` was written on 2026-07-31, was two lines long, and never
 * named a sitemap. CC-3 then shipped `/sitemap.xml`, the production deploy of
 * 2026-08-18 turned it on, and it was reachable by nobody who did not already
 * know the address — a crawler finds a sitemap two ways, this file naming it or
 * a Search Console submission, and neither had happened. **A file in `public/`
 * belongs to no slice**, so no slice updates it; the routes it should describe
 * are added by people who never open it.
 *
 * ⛔ **THE `Sitemap:` LINE IS CONDITIONAL ON THE SAME FLAG THAT SERVES THE
 * SITEMAP, AND THAT CONDITION IS THE WHOLE REASON THIS IS A ROUTE.** All three
 * sitemap addresses sit behind `features.industry_pages` and 404 while it is
 * off (`RequireIndustryPages`). A robots.txt naming a 404 is the one signal
 * `sitemaps/core.blade.php` exists to avoid — *this site's own map is
 * unreliable* — and a static file cannot ask. One reader answers for both:
 * `IndustryPages::enabled()`, the same "one column, two renders" discipline the
 * `noindex` tag and `sitemap-industries.xml` already share.
 *
 * ⚠️ **THE ADDRESS COMES FROM `route()` AND IS NEVER TYPED**, for the reason
 * {@see BotController} gives about its user-agent token: a hand-copied string
 * drifts from the thing it names and nothing fails.
 *
 * ⚠️ **`public/robots.txt` HAD TO BE DELETED FOR THIS TO BE REACHED AT ALL.**
 * The web server serves an existing file before the request reaches
 * `index.php`, so leaving the old one beside this would have made the whole
 * class dead code that every test passed against — 272's shape with an HTTP
 * status attached. `RobotsTest` asserts the file's *absence* as well as this
 * body, because that is the half a green suite would otherwise not see.
 *
 * ⚠️ **THE CRAWL POLICY IS UNCHANGED AND DELIBERATELY SO.** `Disallow:` with an
 * empty value is the old file's rule — everything may be crawled — and
 * narrowing it is a publication decision rather than a plumbing one. What is
 * *not* settled is whether the counsel drafts under `/legal/{doc}` should be
 * crawlable at all while they still carry `[COMPANY LEGAL NAME]`; that is the
 * owner's, it is recorded rather than acted on here, and the answer belongs on
 * those pages' `noindex` tag rather than in a path list here.
 */
final class RobotsController extends Controller
{
    public function __invoke(IndustryPages $pages): Response
    {
        $lines = ['User-agent: *', 'Disallow:'];

        if ($pages->enabled()) {
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap.index');
        }

        // `text/plain` explicitly: a robots.txt served as `text/html` is
        // honoured by Google and not by every crawler, and the static file this
        // replaces got the header from the web server's extension map for free.
        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
