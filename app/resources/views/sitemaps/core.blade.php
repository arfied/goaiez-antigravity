@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp

{{--
    `sitemap-core.xml` — the public site, PIII-72 §A3.

    ⛔ IT LISTED FOUR URLS UNTIL 2026-08-18 AND ITS OWN COMMENT PREDICTED WHY.
    That comment read *"CC-2 builds eleven marketing pages; four of them exist
    today … whoever lands the rest adds them here"* — and CC-2 merged after this
    file was written, so ten live, indexable pages were absent from the map and
    nothing failed. The existing lint — `tests/Feature/IndustryPagesTest.php`'s
    *"every core sitemap entry is a route
    that exists and answers"* — only asserts every `<loc>`
    resolves; it is one-directional, so an entry cannot outlive its page and a
    page could outlive the map indefinitely. ⛔ **THIS CITED a `SitemapTest` AND
    NO FILE OF THAT NAME HAS EVER EXISTED — CORRECTED 2026-08-25 (9662).** That direction is now linted too,
    in `Architecture/MarketingTest.php`, which walks the router rather than
    reading this file — decision 5381.

    ⛔ IT STILL LISTS ONLY ROUTES THAT EXIST, AND THAT RULE IS UNCHANGED. A
    sitemap entry for a URL that 404s is the one signal telling a crawler this
    site's own map is unreliable.

    ⚠️ `/industries` IS NOT HERE — IT IS IN `sitemap-industries.xml` WITH ITS
    CLUSTER, and only while that cluster has an indexable member. The hub is
    `noindex` until the corpus is, so listing it here would be the exact
    contradiction this file's sibling exists to make impossible: a URL asking to
    be crawled that tells the crawler not to index it.

    ⛔ `/privacy` AND `/sms-terms` ARE NOT HERE AND MUST NOT BE ADDED. They are
    `/legal/{doc}` at a second address (routes/web.php, the 10DLC block), and
    the rendered page's `<link rel="canonical">` points back at `/legal/privacy`
    and `/legal/sms-terms`. A sitemap naming a URL that names a different URL as
    its canonical asks a crawler to index the copy we told it to ignore. The
    short paths exist for a person retyping them off a carrier form or a printed
    card, which is not a crawler.

    ⛔ NO `/legal/{doc}` IS HERE EITHER, AND THE REASON IS NOT THE CANONICAL ONE.
    Every one of those documents is counsel's draft and every one of them still
    renders its brackets — `Effective: [DATE]`, `[COMPANY LEGAL NAME], a [STATE]
    [ENTITY TYPE]`. 5361's rule is that a bracket is a placeholder for a ruling
    and never the ruling, and asking Google to index one is the loudest possible
    way to publish it. ✅ AND THEY NO LONGER MERELY GO UNASKED — the owner ruled
    on 2026-08-18 and a placeholder document now carries `noindex` in its own
    right (5386, closing 5383). This clause read *"this is not a claim they are
    uncrawlable — they carry no noindex"* until then, and the two halves are
    still different rules: this file refuses to **ask**, the tag refuses to be
    **indexed**, and a page can want the second without the first.

    ⚠️ NO `lastmod`, NO `priority`, NO `changefreq`. These are Blade templates:
    their modification date is a deploy timestamp rather than a fact about the
    page, and PIII-72 §A3's rule is "honest fields only — we don't write fiction
    into XML".
--}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    {{-- The signed-out funnel, in the order a visitor meets it. --}}
    <url>
        <loc>{{ route('home') }}</loc>
    </url>
    <url>
        <loc>{{ route('features') }}</loc>
    </url>
    <url>
        <loc>{{ route('pricing') }}</loc>
    </url>
    <url>
        <loc>{{ route('compare') }}</loc>
    </url>
    <url>
        <loc>{{ route('guarantee') }}</loc>
    </url>
    <url>
        <loc>{{ route('customers') }}</loc>
    </url>
    <url>
        <loc>{{ route('faq') }}</loc>
    </url>
    <url>
        <loc>{{ route('start') }}</loc>
    </url>

    {{--
        The two partner pages. ⚠️ THEY ARE LISTED WITH A CAVEAT WRITTEN DOWN
        RATHER THAN HELD BACK: 5361 records that both are live with the offer
        removed, because five commercial numbers are withheld. Omitting them
        here would not have made them less indexable — they answer 200, carry no
        `noindex` and are linked from the footer — so an omission would have
        bought silence rather than correctness, which is 5361's own phrase for
        what fail-closed buys on those pages. The remedy for an incomplete page
        is a `noindex` tag or the five numbers, not an absence from the map.
    --}}
    <url>
        <loc>{{ route('affiliates') }}</loc>
    </url>
    <url>
        <loc>{{ route('agencies') }}</loc>
    </url>

    {{-- The two standing public utilities. --}}
    <url>
        <loc>{{ route('bot') }}</loc>
    </url>
    <url>
        <loc>{{ route('sms-optin') }}</loc>
    </url>
</urlset>
