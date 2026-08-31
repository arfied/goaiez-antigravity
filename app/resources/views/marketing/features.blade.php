@php
    use App\Enums\MarketingCapability;
@endphp

{{--
    What GOAIEZ does, one section per capability (`29` §6.1's /what-it-does,
    CC-2 §2.3).

    ⛔ THE SECTIONS AND THE COMPARISON TABLE READ ONE SOURCE. `MarketingCapability`
    says which capabilities are live and which are dark; this page renders a
    section per live one and `/compare` renders a row. Two lists would drift, and
    they would drift towards claiming something we have not shipped.

    ⚠️ THE REVIEWS PARAGRAPH DESCRIBES THE GATE IN WORDING ONLY. The routing is
    `App\Services\Reviews\ReviewGating` and it is frozen — below the line the
    customer reaches the business privately, at or above it they are sent out to
    Google and the configured destinations. Nothing on this page changes that, and
    nothing on this page may be "corrected" into describing a different rule.
--}}

<x-marketing.layout
    title="What GOAIEZ does — every feature, plainly"
    description="Every feature, in plain words: missed calls texted back, reviews asked for at the right moment, and a front desk that quotes only your prices."
>
    <section class="mx-auto w-full max-w-3xl px-4 pt-16 pb-8 text-center">
        <h1 class="font-display text-4xl font-semibold tracking-tight text-balance text-ink sm:text-5xl">
            Everything it does, plainly.
        </h1>

        <p class="mx-auto mt-5 max-w-xl text-lg text-ink-2">
            One paragraph each, describing the mechanism rather than the promise. If a
            thing is not on this page, we do not do it yet.
        </p>
    </section>

    <section class="mx-auto w-full max-w-3xl px-4 pb-16" aria-labelledby="capabilities">
        <h2 id="capabilities" class="sr-only">Capabilities</h2>

        <div class="space-y-10">
            @foreach ([
                MarketingCapability::TextBack->value => 'The pipe bursts at 7:42 pm. Whoever answers first gets the job — and a text back in 60 seconds means that\'s you.',
                MarketingCapability::Reviews->value => 'Happy customers get asked at the right moment. Unhappy ones reach YOU privately first — so you fix it before it\'s public.',
                MarketingCapability::FrontDesk->value => 'It quotes only YOUR price list. It can\'t invent a number — that\'s not a promise, it\'s how it\'s built.',
                MarketingCapability::Commerce->value => 'Sell it. Book it. Gift it. Taxes and fees show before the button — the number your customer sees is the number they pay.',
                MarketingCapability::Campaigns->value => 'Twelve campaigns, already written. Preview the exact messages, then turn one on.',
                MarketingCapability::Inbox->value => 'One thread per human — every text, email, and call from the same person, in one story.',
                MarketingCapability::Websites->value => 'You talk. It builds. Your first look is never a blank page.',
                MarketingCapability::BoostScore->value => 'One honest number, from counted data. No data, no number — we don\'t do vanity scores.',
            ] as $key => $body)
                {{--
                    A dark capability renders nothing at all — no teaser, no "coming
                    soon". `29` §13 and the versioned-site rule both say the same
                    thing: we do not sell futures, and a greyed-out section is a
                    future being sold.
                --}}
                @if ($capabilities[$key])
                    <article>
                        <h3 class="font-display text-xl font-semibold text-ink">
                            {{ MarketingCapability::from($key)->heading() }}
                        </h3>
                        <p class="mt-2 text-base text-ink-2">{{ $body }}</p>
                    </article>
                @endif
            @endforeach
        </div>

        <p class="mt-12 text-base text-ink-2">
            We do not promise rankings, and nobody can. What we promise is that the work
            gets done, that you can see it, and that anything which makes things worse
            gets reversed.
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
            <x-ui.button :href="route('start')">Start free</x-ui.button>
            <x-ui.button :href="route('compare')" variant="secondary">See the comparison</x-ui.button>
        </div>
    </section>
</x-marketing.layout>
