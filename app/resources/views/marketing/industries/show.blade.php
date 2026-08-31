{{--
    One industry lander — CC-3 §3, from PIII-64A–E's nine-slot grammar.

    ⚠️ EVERY WORD OF CONTENT ON THIS PAGE COMES FROM ITS ROW. THE VIEW LAW
    (PIII-64A's header): "an industry page RENDERS from the profile registry +
    the row below — edits happen in rows, never in HTML." A sentence typed into
    this file is a sentence that exists on one page of a hundred and in no
    authored source — so if a line here needs changing, change it in
    `database/seeders/industry-pages/source/` and re-run `industries:seed`.

    ⚠️ THE SHARED MARKETING SHELL, NOT A NO-NAV LANDER. PIII-64A's grammar cites
    the T240 lander's "no-nav · one CTA" and CC-3 §3 softens that to "light
    chrome"; this uses `x-marketing.layout` unchanged. Two reasons, and the
    second is the one that decided it: the shell is where row 1's LCP properties
    live (one stylesheet, text LCP, no Livewire runtime), and CC-2 is editing
    that file on another branch to add nav entries — a second public shell forked
    from it would inherit today's copy of those properties and quietly stop
    tracking them. Decision 5224.

    ⚠️ `noindex` FOLLOWS `index_mode` AND NOTHING ELSE. The same column feeds
    `sitemap-industries.xml`; see `SitemapController`.
--}}

<x-marketing.layout
    :title="$page->title"
    :description="$page->meta_desc"
    :noindex="! $page->index_mode"
>
    {{--
        BreadcrumbList, riding the page rather than a switch — PIII-72 §A2.5.

        ⚠️ THE ARRAY IS BUILT IN THE CONTROLLER, NOT HERE. `@json([...])` written
        inline is a Blade *directive* whose argument is parsed before PHP sees
        it, and a multi-line array literal breaks that parse with "Unclosed '['"
        — a 500 on a public page from a template that reads perfectly. One
        variable is what the directive is for.

        `@json` escapes for a script context, so a corpus row containing an
        apostrophe or an angle bracket cannot break out of it.
    --}}
    <script type="application/ld+json">@json($breadcrumbs)</script>

    <article class="mx-auto w-full max-w-2xl px-4 py-12">
        <nav aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-x-2 text-sm text-ink-2">
                <li>
                    <a
                        href="{{ route('home') }}"
                        class="underline underline-offset-2 hover:text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                    >Home</a>
                </li>
                <li aria-hidden="true">›</li>
                <li>
                    <a
                        href="{{ route('industries.index') }}"
                        class="underline underline-offset-2 hover:text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                    >Industries</a>
                </li>
                <li aria-hidden="true">›</li>
                <li aria-current="page">{{ $page->noun() }}</li>
            </ol>
        </nav>

        <h1 class="mt-8 font-display text-3xl font-semibold tracking-tight text-ink">{{ $page->h1 }}</h1>

        <p class="mt-4 text-lg text-ink-2">{{ $page->hook }}</p>

        <p class="mt-4 text-base text-ink-2">{{ $page->beat }}</p>

        <ul class="mt-8 flex flex-col gap-2">
            @foreach ($page->trio as $item)
                <li class="flex gap-3 text-base text-ink">
                    {{--
                        The marker is text, not colour — `22`'s "colour is
                        information, never decoration, and never the sole
                        indicator".
                    --}}
                    <span aria-hidden="true" class="text-ink-2">—</span>
                    <span>{{ $item }}</span>
                </li>
            @endforeach
        </ul>

        <p class="mt-8 text-base text-ink-2">{{ $page->trust }}</p>

        {{--
            The one CTA — CC-3 §3's demo door.

            ⛔ RENDERED ONLY WHEN THE NUMBER EXISTS. The keyword is this row's
            own; the number is the owner's to state and is unset, so today this
            block is absent rather than half-written. `IndustryDemoDoors` carries
            the argument for why a placeholder would be worse.
        --}}
        @if ($demoNumber !== null)
            <p class="mt-10 text-lg text-ink">
                Text <span class="font-mono">{{ $page->demo_keyword }}</span> to
                <span class="font-mono">{{ $demoNumber }}</span> and watch your phone.
            </p>
        @endif

        {{--
            The sibling strip — PIII-72 §A2.2. Same family only, anchors are the
            industry noun, and the rotation is seeded by this page's slug so a
            crawler sees the same six links on every fetch.

            Absent when the family has no other members: `medspa` has exactly one
            row in the authored hundred, and a heading over an empty list is a
            dead element on a public page.
        --}}
        @if ($siblings->isNotEmpty())
            <section class="mt-14 border-t border-rule pt-8" aria-labelledby="siblings">
                <h2 id="siblings" class="font-display text-lg font-semibold tracking-tight text-ink">
                    More {{ $page->family->label() }} businesses we answer for
                </h2>

                <ul class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
                    @foreach ($siblings as $sibling)
                        <li>
                            <a
                                href="{{ route('industries.show', $sibling->slug) }}"
                                class="text-base text-ink-2 underline underline-offset-2 hover:text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                            >{{ $sibling->noun() }}</a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </article>
</x-marketing.layout>
