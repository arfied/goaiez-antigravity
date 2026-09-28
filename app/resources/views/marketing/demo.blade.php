{{--
    One of the six family demo doors (CC-2 §2.7).

    ⛔ THE KEYWORD AND THE NUMBER ARE ONE BLOCK AND RENDER TOGETHER OR NOT AT ALL.
    `demo.number` has never been stated by the owner and carries no seed, so on
    every deployment today this page renders without the instruction — deliberately,
    and not as a degraded state. "Text TRADES to —" is the placeholder the whole
    rule exists to refuse.
    The title, description and h1 follow the same guard (wave 814): "live" is said only when the number exists.

    ⚠️ THE SAMPLE CHROME IS A SIGNAL COLOUR AND NEVER THE ONLY ONE. Every element
    that shows sample data also carries the word "Sample", so the page says what it
    means in monochrome, to a screen reader, and to somebody who cannot tell violet
    from grey — the impersonation banner's rule (`22`).
--}}

@php $demoIsLive = $demoNumber !== null && $demoKeyword !== null; @endphp
<x-marketing.layout
    :title="($demoIsLive ? 'Watch it answer ' : 'See how it answers ').$family->article().' '.$family->label().' business'"
    :description="$demoIsLive
        ? 'A live demo you run from your own phone: text a word and watch a '.$family->label().' front desk answer in seconds.'
        : 'What a '.$family->label().' front desk says when a customer texts: answers in seconds, every figure from a price list, and a hand-off to a person when it should not decide.'"
>
    <section class="mx-auto w-full max-w-3xl px-4 pt-16 pb-8 text-center">
        <h1 class="font-display text-4xl font-semibold tracking-tight text-balance text-ink sm:text-5xl">
            @if ($demoIsLive)
                Watch it answer {{ $family->article() }} {{ $family->label() }} business — live.
            @else
                See how it answers {{ $family->article() }} {{ $family->label() }} business.
            @endif
        </h1>

        @if ($demoNumber !== null && $demoKeyword !== null)
            <div class="mx-auto mt-10 max-w-xl rounded-[--radius-card] border border-rule-strong bg-card p-6 text-left">
                <p class="inline-flex items-center gap-1.5 rounded-full bg-sample-bg px-2.5 py-1 text-sm font-semibold text-sample">
                    <span aria-hidden="true">◆</span>
                    <span>Sample</span>
                </p>

                <p class="mt-4 text-lg text-ink">
                    Text <strong class="font-mono font-semibold">{{ $demoKeyword }}</strong>
                    to <strong class="font-mono font-semibold">{{ $demoNumber }}</strong>
                    and watch your phone.
                </p>

                <p class="mt-3 text-base text-ink-2">
                    Ask it anything a customer would ask. It answers from a price list and a
                    diary, the way yours would.
                </p>
            </div>
        @endif
    </section>

    <section class="mx-auto w-full max-w-3xl px-4 pb-16" aria-labelledby="what-you-see">
        <h2 id="what-you-see" class="font-display text-2xl font-semibold text-ink sm:text-3xl">
            What you will see
        </h2>

        <ul class="mt-8 space-y-6">
            @foreach ([
                ['It answers in seconds', 'No hold music and no callback promise. The reply lands while the question is still on the screen.'],
                ['It quotes from a list', 'Every figure it gives comes off a price list somebody wrote. It has no way to produce one that is not there.'],
                ['It knows when to stop', 'Anything it should not decide gets handed to a person, and it says so rather than guessing.'],
            ] as [$heading, $body])
                <li>
                    <div class="rounded-[--radius-card] border border-rule bg-card p-5">
                        <p class="inline-flex items-center gap-1.5 rounded-full bg-sample-bg px-2.5 py-1 text-sm font-semibold text-sample">
                            <span aria-hidden="true">◆</span>
                            <span>Sample</span>
                        </p>

                        <h3 class="mt-3 font-display text-lg font-semibold text-ink">{{ $heading }}</h3>
                        <p class="mt-2 text-base text-ink-2">{{ $body }}</p>
                    </div>
                </li>
            @endforeach
        </ul>

        {{-- CC-2 §2.7's honesty footer, VERBATIM. --}}
        <p class="mt-10 text-base text-ink-2">
            This is a sample business with sample data. Yours opens with your real information.
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
            <x-ui.button :href="route('start')">Start free</x-ui.button>
            <x-ui.button :href="route('features')" variant="secondary">See everything it does</x-ui.button>
        </div>
    </section>
</x-marketing.layout>
