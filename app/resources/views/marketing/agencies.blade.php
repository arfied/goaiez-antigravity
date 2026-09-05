{{--
    The agency programme (CC-2 §2.10).

    ✅ THE WHOLESALE RATES ARE SEEDED, SO THE DEAL SENTENCE RENDERS (P-008).

    ⚠️ THE HONESTY BLOCK IS THE POINT OF THE PAGE, not a caveat at the bottom of it.
    `29` §13 and the versioned-site rule agree: we do not sell futures, so this page
    describes multi-client switching and one invoice, and says in as many words that
    deeper tooling will announce itself when it ships.

    ⛔ THERE IS NO "START YOUR AGENCY ACCOUNT" BUTTON (5201). Nothing in this
    application provisions an agency account — `/start` opens an ordinary trial —
    so a button with that label would name a thing it does not do, which is worse
    than a button that is missing.

    ⛔ **AND THE HONESTY BLOCK ITSELF WAS THE ONE THING ON THIS PAGE NOT HELD TO
    THAT RULE, FOR AS LONG AS IT HAS EXISTED — CORRECTED 2026-08-28 (11804).**
    Under the heading *"What is live today"* it said **"Multi-client switching
    and consolidated billing"**, two paragraphs above *"we don't sell futures"*.
    `Enums/UserRole`'s own docblock says the `Agency` role **cannot yet span
    businesses** — `businesses.owner_user_id` is the only user-to-business link
    in the model and there is no membership pivot — so switching is unbuildable
    today rather than merely unbuilt, and `consolidated` occurs once in `app/`,
    about the Better Business Bureau. ⚠️ **A heading that says *today* is a
    factual assertion and not a boast**, which is what put this above the H1 and
    the three steps in the queue; those are aspirational framing and are
    reported, not rewritten. `Architecture/MarketingTest` pins the replacement
    and refuses the old sentence anywhere on the funnel.

    ⛔ **AND THE REPAIR MADE A SECOND PROBLEM LEGIBLE, WHICH ONLY READING THE
    RENDERED PAGE AS TEXT FOUND.** With the corrected block still in its authored
    position, a reader met three numbered present-tense steps — *"Your agency
    account: one signup, and one invoice for everything under it"* — and only
    afterwards learnt the account is not open. **Before the repair both blocks
    agreed and both were false; after it they disagreed**, which is worse to read
    even though it is more truthful. ✅ **The two sections are therefore swapped
    and nothing else about them is touched**: the state is met first, and the
    steps then read as the shape rather than as a door. ⚠️ **Steps 01 and 03 are
    still written in the present tense and are still owed** — rewriting three
    authored steps is a copy decision and reported rather than taken.
--}}

<x-marketing.layout
    title="Run your clients' front desks — at wholesale."
    description="One agency account, one consolidated invoice, and the full platform behind every client workspace. You bill your clients your way."
>
    <section class="mx-auto w-full max-w-3xl px-4 pt-16 pb-8">
        <h1 class="font-display text-4xl font-semibold tracking-tight text-balance text-ink sm:text-5xl">
            Run your clients' front desks — at wholesale.
        </h1>

        <p class="mt-6 text-lg text-ink-2">
            @if ($usageDiscount !== null && $voiceDiscount !== null)
                Agency accounts get {{ $usageDiscount }} off SMS, AI and lead usage, {{ $voiceDiscount }} off voice on every client
                workspace.
            @endif
            You bill your clients your way, at your price — the margin is yours. Every
            client gets the full platform: the sixty-second text-back, the front desk,
            reviews on autopilot, the works.
        </p>
    </section>

    <section class="mx-auto w-full max-w-3xl px-4 pb-8" aria-labelledby="honesty">
        <h2 id="honesty" class="font-display text-2xl font-semibold text-ink sm:text-3xl">
            What is live today
        </h2>

        <p class="mt-4 text-base text-ink-2">
            Every workspace you open is the full product — the same platform your client
            would get on their own, not a cut-down version of it. The agency account itself,
            with one login across your clients and one statement for all of them, is not open
            yet: today each client is their own account.
        </p>

        <p class="mt-4 text-base text-ink-2">
            Deeper agency tooling ships at its own flip and will announce itself — we don't sell futures.
        </p>
    </section>

    <section class="mx-auto w-full max-w-3xl px-4 pb-16" aria-labelledby="how-it-starts">
        <h2 id="how-it-starts" class="font-display text-2xl font-semibold text-ink sm:text-3xl">
            How it starts
        </h2>

        <ol class="mt-8 space-y-6">
            @foreach ([
                ['Your agency account', 'One signup, and one invoice for everything under it.'],
                ['Add a client in minutes', 'Every client gets the same first win: pick their industry, their workspace opens already filled in, and the first test call lands before the coffee is done.'],
                ['One statement', 'Wholesale rates, one document, every line item priced from the same rows the rest of the site quotes.'],
            ] as $index => [$step, $body])
                <li>
                    <span class="font-mono text-sm text-ink-3">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <h3 class="mt-1 font-display text-lg font-semibold text-ink">{{ $step }}</h3>
                    <p class="mt-2 text-base text-ink-2">{{ $body }}</p>
                </li>
            @endforeach
        </ol>

        <div class="mt-8 flex flex-wrap gap-3">
            <x-ui.button :href="route('legal.document', ['doc' => 'agency'])">
                Read the agency terms
            </x-ui.button>
            <x-ui.button :href="route('pricing')" variant="secondary">See the retail prices</x-ui.button>
        </div>
    </section>
</x-marketing.layout>
