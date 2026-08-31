{{--
    The guarantee (CC-2 §2.5).

    ⛔ THE PROMISE ITSELF IS ONE REGISTRY ROW AND IS NEVER TYPED HERE.
    `legal.guarantee_sentence` is the R39 canonical; `/pricing` renders the same
    row, and a feature test asserts the two pages byte-match it and each other. A
    guarantee that is almost the same in two places is two guarantees, and which
    one we are held to is decided by whichever one the customer screenshotted.

    ⚠️ WHILE THE ROW IS EMPTY THE PAGE RENDERS WITHOUT THE SENTENCE, rather than
    with a placeholder. This application may not draft a guarantee, and a plausible
    one would be a promise nobody made, published in the owner's name.

    ⚠️ WHAT IS BELOW THE SENTENCE IS NOT THE GUARANTEE. The make-good mechanics
    describe what happens when you tell us it is not working; the honesty footer
    describes the refund policy. Both are descriptions of behaviour this
    application already has, which is why they are prose and the promise is a row.
--}}

<x-marketing.layout
    title="The GOAIEZ guarantee — in plain words"
    description="What we promise, what happens if it is not working, and why cancelling in about a minute is the part that matters most."
>
    <section class="mx-auto w-full max-w-3xl px-4 pt-16 pb-8">
        <h1 class="font-display text-4xl font-semibold tracking-tight text-balance text-ink sm:text-5xl">
            The guarantee, in plain words.
        </h1>

        @if ($guarantee !== null)
            <p class="mt-6 text-lg text-ink">{{ $guarantee }}</p>
        @endif
    </section>

    <section class="mx-auto w-full max-w-3xl px-4 pb-16" aria-labelledby="make-good">
        <h2 id="make-good" class="font-display text-2xl font-semibold text-ink sm:text-3xl">
            What happens if it is not working
        </h2>

        <ol class="mt-8 space-y-6">
            @foreach ([
                ['You tell us', 'A text is enough. There is no ticket to raise and no queue to sit in — the same number you already reply to.'],
                ['We look at the record', 'Every automatic action is written down as it happens, so what ran, when it ran and what it changed is already there to read. Nobody has to reconstruct it.'],
                ['We put it right', 'If something we changed made things worse, it gets reversed — that is built in and measured, not a favour. If the work did not get done, we do it.'],
                ['You decide', 'If you would rather leave, you leave. Cancelling takes about a minute and does not go through us.'],
            ] as $index => [$step, $body])
                <li>
                    <span class="font-mono text-sm text-ink-3">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="mt-1 font-display text-lg font-semibold text-ink">{{ $step }}</h3>
                    <p class="mt-2 text-base text-ink-2">{{ $body }}</p>
                </li>
            @endforeach
        </ol>

        {{--
            CC-2 §2.5's honesty footer, VERBATIM. It says the uncomfortable half
            out loud on purpose: the refund policy is not the guarantee, and a page
            that let a reader assume otherwise would be making the promise by
            omission.
        --}}
        <p class="mt-10 rounded-[--radius-card] border border-rule bg-card p-6 text-base text-ink-2">
            No refunds is the policy; the guarantee is the promise — and canceling takes about a minute whenever you want, so you're never locked in.
        </p>

        <div class="mt-8 flex flex-wrap gap-3">
            <x-ui.button :href="route('start')">Start free</x-ui.button>
            <x-ui.button :href="route('pricing')" variant="secondary">See the prices</x-ui.button>
        </div>
    </section>
</x-marketing.layout>
