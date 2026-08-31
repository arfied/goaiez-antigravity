@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp

{{--
    `sitemap-industries.xml` — CC-3 §4, PIII-72 §A3.

    ⛔ ONE COLUMN, AND THE SAME COLUMN THE PAGE READS. `$pages` is
    `IndustryPages::indexable()`, which is `where('index_mode', true)` and
    nothing else. Every row whose `index_mode` is false renders `noindex` on its
    own page and is absent here, and neither statement can be made without the
    other.

    ⚠️ AN EMPTY `<urlset>` IS THE CORRECT ANSWER TODAY and is valid against the
    schema. Every row seeds `index_mode` false, so until the owner flips the
    column this file lists nothing — which is exactly what it should say about a
    cluster nobody has published.

    ⚠️ HONEST FIELDS ONLY: `lastmod` is the row's real `updated_at`, `changefreq`
    is monthly, and `priority` is omitted. A `<priority>` identical on a hundred
    URLs carries no information and is what "flat priority — no games" means in
    practice; the value a crawler assumes when it is absent is the same one.
--}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @if ($pages->isNotEmpty())
        {{--
            The hub rides with its cluster rather than with the core pages,
            because its own indexability is derived from exactly this list.
            No `lastmod`: the hub is a Blade template listing rows, and the
            newest row's timestamp is an inference rather than a fact about it.
        --}}
        <url>
            <loc>{{ route('industries.index') }}</loc>
            <changefreq>monthly</changefreq>
        </url>
    @endif
    @foreach ($pages as $page)
        <url>
            <loc>{{ route('industries.show', $page->slug) }}</loc>
            <lastmod>{{ $page->updated_at?->toAtomString() }}</lastmod>
            <changefreq>monthly</changefreq>
        </url>
    @endforeach
</urlset>
