{{--
    The affiliate programme (CC-2 §2.9).

    ✅ EVERY RATE ON THIS PAGE IS SEEDED AND THE DEAL BLOCK RENDERS (P-009, R245).

    ⚠️ THE FOUR FIGURES ARE ONE BLOCK. A commission rate with no payout floor is as
    unfinished a promise as no rate at all, so the block appears when all four are
    set and never on a subset.

    ⛔ THERE IS NO "GET YOUR LINK" BUTTON, AND ITS ABSENCE IS DELIBERATE (5201).
    CC-2 §2.9 names it, and nothing in this application enrols an affiliate or
    issues a link — so the button would be the dead action the empty-state
    component was hardened against, on the page where somebody has just decided to
    say yes. What is here instead are two destinations that exist.
--}}

<x-marketing.layout
    title="Get paid every month for every business you send."
    description="Refer a local business. When they pay, you get paid — on money that actually arrived, for the life of the account."
>
    <section class="mx-auto w-full max-w-3xl px-4 pt-16 pb-8">
        <h1 class="font-display text-4xl font-semibold tracking-tight text-balance text-ink sm:text-5xl">
            Get paid every month for every business you send.
        </h1>

        @if ($deal !== null)
            <div class="mt-8 rounded-[--radius-card] border border-rule-strong bg-card p-6">
                <h2 class="font-display text-xl font-semibold text-ink">The deal, plainly</h2>

                <p class="mt-3 text-lg text-ink">
                    {{ $deal['rateMonthly'] }} of every monthly payment,
                    {{ $deal['rateAnnual'] }} of annual plans, for the life of the account.
                </p>

                <p class="mt-3 text-base text-ink-2">
                    Your link remembers who you sent for {{ $deal['cookieDays'] }} days, and once
                    they sign up, they're yours for good. Payouts go out on the cycle after funds
                    clear, once you've reached {{ $deal['minimumPayout'] }}.
                </p>
            </div>
        @endif

        {{-- CC-2 §2.9's trust line, VERBATIM. --}}
        <p class="mt-8 text-lg text-ink">
            We pay on money that actually arrived — collected, not booked — so your number is never a maybe.
        </p>
    </section>

    <section class="mx-auto w-full max-w-3xl px-4 pb-8" aria-labelledby="who-for">
        <h2 id="who-for" class="font-display text-2xl font-semibold text-ink sm:text-3xl">
            Who this is for
        </h2>

        <p class="mt-4 text-base text-ink-2">
            Agencies, bookkeepers, web designers, suppliers — and happy customers. If you
            already talk to local business owners for a living, you are the whole audience.
        </p>
    </section>

    <section class="mx-auto w-full max-w-3xl px-4 pb-16" aria-labelledby="rules">
        <h2 id="rules" class="font-display text-2xl font-semibold text-ink sm:text-3xl">
            The three rules
        </h2>

        <p class="mt-4 text-base text-ink-2">
            One strike each, and they come straight out of the program terms. That is the
            whole list.
        </p>

        <ul class="mt-6 space-y-4">
            @foreach ([
                ['No self-referrals', 'Your own accounts, and accounts paid from your own card, do not earn. We check.'],
                ['No bidding on our brand terms', 'Buying ads against our own name is not a referral, it is a toll on somebody who was already coming.'],
                ['No spam', 'Every message you send about us is yours to stand behind, and the rules that apply to us apply to you.'],
            ] as [$rule, $body])
                <li class="rounded-[--radius-card] border border-rule bg-card p-5">
                    <h3 class="font-display text-lg font-semibold text-ink">{{ $rule }}</h3>
                    <p class="mt-2 text-base text-ink-2">{{ $body }}</p>
                </li>
            @endforeach
        </ul>

        <div class="mt-8 flex flex-wrap gap-3">
            <x-ui.button :href="route('legal.document', ['doc' => 'referral-affiliate'])">
                Read the program terms
            </x-ui.button>
            <x-ui.button :href="route('start')" variant="secondary">Start free</x-ui.button>
        </div>
    </section>
</x-marketing.layout>
