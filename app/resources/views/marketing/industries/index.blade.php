{{--
    The hub — PIII-72 §A1, CC-2 §2.11.

    ⚠️ THE HEADER IS VERBATIM COPY AND IS NOT EDITORIAL. The H1 and the sub-line
    below are the program's own words, byte-for-byte, and
    `tests/Feature/IndustryPagesTest.php`'s *"the hub renders the program header
    verbatim"* asserts both — as does
    `tests/Feature/MarketingSiteTest.php` from the other branch. ⛔ **THIS CITED
    an `IndustryHubTest` AND NO FILE OF THAT NAME HAS EVER EXISTED — CORRECTED
    2026-08-25 (9662).** CC-2 ships the identical strings on its own branch; if that
    lands second, the two must still read the same.

    ⚠️ `noindex` IS DERIVED, NOT SET HERE. The hub is indexable exactly when at
    least one of its hundred children is — one column, a third render. A hub that
    invited crawling while every page it lists said `noindex` would be a hundred
    internal links to pages a crawler has been told to ignore, and a flag of its
    own would be a second thing that can disagree with `index_mode`
    (decision 5225). `sitemap-industries.xml` lists the hub under the identical
    condition.
--}}

<x-marketing.layout
    title="Who we answer for"
    description="A hundred kinds of local business, one front desk that never misses. Find yours — then text its demo and watch."
    :noindex="$noindex"
>
    <div class="mx-auto w-full max-w-4xl px-4 py-16">
        <h1 class="font-display text-3xl font-semibold tracking-tight text-ink">Who we answer for.</h1>

        <p class="mt-4 text-lg text-ink-2">
            A hundred kinds of local business, one front desk that never misses. Find yours —
            then text its demo and watch.
        </p>

        @foreach ($families as $family)
            @php($pages = $sections[$family->value])

            <section class="mt-14" aria-labelledby="family-{{ $family->value }}">
                <h2
                    id="family-{{ $family->value }}"
                    class="font-display text-xl font-semibold tracking-tight text-ink"
                >{{ $family->label() }}</h2>

                {{--
                    Anchor text is the industry noun and nothing else — PIII-72
                    §A2.2. A list rather than a grid of cards: a hundred links
                    read as a directory, and a directory is what somebody
                    scanning for their own trade wants.
                --}}
                <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
                    @foreach ($pages as $page)
                        <li>
                            <a
                                href="{{ route('industries.show', $page->slug) }}"
                                class="text-base text-ink-2 underline underline-offset-2 hover:text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                            >{{ $page->noun() }}</a>
                        </li>
                    @endforeach
                </ul>

                {{--
                    The section's demo door — PIII-72 §A2.1's "each section
                    closing with its family demo door".

                    ⛔ RENDERED ONLY WHEN BOTH HALVES EXIST. The number is the
                    owner's to state and is unset; see IndustryDemoDoors for why
                    a placeholder is worse than an absence.
                --}}
                @php($keyword = $doors->keywordFor($family))

                @if ($demoNumber !== null && $keyword !== null)
                    <p class="mt-4 text-base text-ink">
                        Text <span class="font-mono">{{ $keyword }}</span> to
                        <span class="font-mono">{{ $demoNumber }}</span> and watch your phone.
                    </p>
                @endif
            </section>
        @endforeach
    </div>
</x-marketing.layout>
