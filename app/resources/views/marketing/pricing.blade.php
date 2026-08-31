{{--
    Pricing (`29` §6.1, CC-2 §2.2).

    ⛔ NOT ONE FIGURE ON THIS PAGE IS TYPED HERE. Every amount arrives already
    formatted from `MarketingController::pricing()`, which reads the registry
    through `PlanCharges` — so an operator moving a price in Ops moves this page
    with it, and the page that sells the plan cannot go on quoting a number
    nobody is charged (decision 512, whose one real violation was this page's
    ancestor on the home).

    ⚠️ THE LIMITED TIER IS ABSENT, DELIBERATELY, AND THAT IS DECISION 262's RULE
    ONE PAGE OVER. Its contents are decided and its price is not; `CLAUDE.md` is
    explicit that the unset figures may not be guessed, and a card reading "price
    to be confirmed" is a placeholder where the honest answer is silence.
--}}

<x-marketing.layout
    title="Pricing"
    description="Simple plans, honest math, and a guarantee in plain words. Cancel in about a minute, any time."
>
    <section class="mx-auto w-full max-w-3xl px-4 pt-16 pb-8 text-center">
        <h1 class="font-display text-4xl font-semibold tracking-tight text-balance text-ink sm:text-5xl">
            What it costs.
        </h1>

        <p class="mx-auto mt-5 max-w-xl text-lg text-ink-2">
            Simple plans, honest math, and a guarantee in plain words. Cancel in about
            a minute, any time.
        </p>
    </section>

    <section class="mx-auto w-full max-w-3xl px-4 pb-8" aria-labelledby="plans">
        <h2 id="plans" class="sr-only">Plans</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-[--radius-card] border border-rule bg-card p-6 text-left">
                <h3 class="font-display text-lg font-semibold text-ink">Free</h3>
                <p class="mt-1 font-display text-3xl font-semibold tabular-nums text-ink">{{ $free }}</p>
                <p class="mt-3 text-base text-ink-2">
                    The watching, without the doing. Your score, your alerts, one location.
                </p>
            </div>

            <div class="rounded-[--radius-card] border border-rule-strong bg-card p-6 text-left">
                <h3 class="font-display text-lg font-semibold text-ink">Everything</h3>
                <p class="mt-1 font-display text-3xl font-semibold tabular-nums text-ink">
                    {{ $monthly }}<span class="text-base font-normal text-ink-2">/month</span>
                </p>
                <p class="mt-1 text-sm text-ink-3 tabular-nums">
                    or {{ $annual }}/year · each extra location {{ $locationMonthly }}/month
                    or {{ $locationAnnual }}/year
                </p>
                <p class="mt-3 text-base text-ink-2">
                    The whole system, working on your business while you reply to the odd text.
                    One location is included; add more when you open them.
                </p>
            </div>
        </div>

        {{-- CC-2 §2.2's visitor sentence, VERBATIM. 3443's ruling in one line. --}}
        <p class="mt-6 text-base text-ink-2">
            Already with us? Nothing about your plan changes.
        </p>

        @if ($founderWindowClosed)
            {{--
                CC-2 §2.2's founder-history line, VERBATIM — and rendered only once
                the window has actually closed (decision 5198). It is written in
                the past tense, so beside a page quoting the founder rate it would
                announce the end of the offer it was selling.
            --}}
            <p class="mt-3 text-base text-ink-2">
                Founder pricing existed, it closed exactly when we said it would, and everyone who took it keeps it forever.
            </p>
        @endif

        <div class="mt-8 flex justify-center">
            <x-ui.button :href="route('start')">Start free</x-ui.button>
        </div>
    </section>

    @if ($guarantee !== null)
        {{--
            The R39 canonical, rendered from `legal.guarantee_sentence` and never
            typed. `/guarantee` renders the identical row, and a feature test
            asserts the two byte-match it — a guarantee that is almost the same on
            two pages is two guarantees, and which one we are held to is decided
            by whichever one the customer screenshotted.
        --}}
        <section class="border-y border-rule bg-card" aria-labelledby="guarantee">
            <div class="mx-auto w-full max-w-3xl px-4 py-12">
                <h2 id="guarantee" class="font-display text-2xl font-semibold text-ink">
                    The guarantee
                </h2>

                <p class="mt-4 text-lg text-ink">{{ $guarantee }}</p>

                <p class="mt-4 text-base text-ink-2">
                    <a
                        href="{{ route('guarantee') }}"
                        class="underline underline-offset-2 hover:text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                    >How the make-good works</a>
                </p>
            </div>
        </section>
    @endif

    <section class="mx-auto w-full max-w-3xl px-4 py-16" aria-labelledby="pricing-questions">
        <h2 id="pricing-questions" class="font-display text-2xl font-semibold text-ink sm:text-3xl">
            The three we get asked
        </h2>

        <dl class="mt-8 space-y-6">
            <div>
                <dt class="font-display text-lg font-semibold text-ink">How do I cancel?</dt>
                <dd class="mt-2 text-base text-ink-2">
                    In the app, in about a minute. No call, no retention queue, no form to
                    request one.
                </dd>
            </div>

            <div>
                <dt class="font-display text-lg font-semibold text-ink">Am I in a contract?</dt>
                <dd class="mt-2 text-base text-ink-2">
                    Month to month.
                    @if ($annualSaving !== null)
                        Paying for a year up front saves {{ $annualSaving }} against twelve
                        monthly payments, and you can still leave.
                    @else
                        Paying for a year up front is an option, and you can still leave.
                    @endif
                </dd>
            </div>

            <div>
                <dt class="font-display text-lg font-semibold text-ink">When do you ask for a card?</dt>
                <dd class="mt-2 text-base text-ink-2">
                    Not to start. The trial runs {{ $trialDays }} days and asks for nothing;
                    you add a card when you decide to stay.
                </dd>
            </div>
        </dl>
    </section>
</x-marketing.layout>
