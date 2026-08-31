@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp

{{--
    The sitemap index — PIII-72 §A3.

    ⚠️ THE DECLARATION IS ECHOED FROM PHP RATHER THAN TYPED. `<?xml` typed into a
    Blade file is a PHP open tag the moment `short_open_tag` is on anywhere this
    ever runs, and the symptom is a parse error in a compiled view rather than
    anything naming this file.

    No `lastmod` on either child. A sitemap index may carry one and it would have
    to mean "when did the child last change", which for the core file is a deploy
    date and for the industries file is the newest row's `updated_at` — one is
    fiction and the other is already stated inside the child itself.
--}}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>{{ route('sitemap.core') }}</loc>
    </sitemap>
    <sitemap>
        <loc>{{ route('sitemap.industries') }}</loc>
    </sitemap>
</sitemapindex>
